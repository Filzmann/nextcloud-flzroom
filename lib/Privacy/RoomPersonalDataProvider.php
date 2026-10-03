<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Repository\BookingRepository;
use OCA\AdRoom\Repository\BookingInterventionAuditRepository;
use OCA\AdRoom\Repository\InterventionNotificationQueueRepository;
use OCA\AdRoom\Repository\RoomRepository;
use OCA\AdRoom\Repository\TemporaryAdminAccessRepository;
use OCA\AdRoom\Service\RoomRetentionPolicyService;
use OCA\AdRoom\Service\RoomAdminLayoutService;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataEntry;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataPage;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataProvider;
use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataRequest;
use OCA\FilzmannDataProtection\PublicApi\V1\ProviderDescriptor;
use OCA\LocalBase\Calendar\CalendarContextSettingsService;
use InvalidArgumentException;

final class RoomPersonalDataProvider implements PersonalDataProvider {
    public function __construct(
        private BookingRepository $bookings,
        private RoomRepository $rooms,
        private RoomRetentionPolicyService $retentionPolicy,
        private CalendarContextSettingsService $calendarContext,
        private TemporaryAdminAccessRepository $adminAccess,
        private RoomAdminLayoutService $adminLayout,
        private ?BookingInterventionAuditRepository $interventionAudit = null,
        private ?InterventionNotificationQueueRepository $interventionQueue = null,
    ) {}
    public function descriptor(): ProviderDescriptor {
        return new ProviderDescriptor(
            AppId::VALUE,
            'AD Raumplaner',
            '1.0',
            ['nextcloud-user'],
            ['personal-data'],
            500,
        );
    }

    public function collect(PersonalDataRequest $request): PersonalDataPage {
        if ($request->subject()->subjectType() !== 'nextcloud-user') {
            return new PersonalDataPage('not_applicable');
        }
        if ($request->cursor() !== null) {
            throw new InvalidArgumentException('AD Raumplaner does not support cursor paging.');
        }

        $policy = $this->retentionPolicy->policy();
        $timezone = $this->calendarContext->context()->timezone();
        $roomNames = [];
        foreach ($this->rooms->findAll() as $room) $roomNames[$room->id()] = $room->name();
        $bookings = $this->bookings->findByUserUid($request->subject()->subjectId(), $request->pageLimit() + 1);
        $adminHistory = $this->adminAccess->historyForUid($request->subject()->subjectId(), $request->pageLimit() + 1);
        $policyHistory = array_values(array_filter(
            $this->retentionPolicy->history(),
            static fn(array $entry): bool => $entry['changedBy'] === $request->subject()->subjectId(),
        ));
        $interventionAudit = $this->interventionAudit?->byActor($request->subject()->subjectId(), $request->pageLimit() + 1) ?? [];
        $interventionQueue = $this->interventionQueue?->byRecipient($request->subject()->subjectId(), $request->pageLimit() + 1) ?? [];
        $restrictions = [];
        try {
            $adminLayout = $this->adminLayout->personalDataForUid($request->subject()->subjectId());
        } catch (InvalidArgumentException) {
            $adminLayout = null;
            $restrictions[] = 'Das gespeicherte persönliche Adminlayout konnte nicht sicher ausgegeben werden.';
        }
        $limited = count($bookings) + count($adminHistory) + count($policyHistory) + count($interventionAudit) + count($interventionQueue) + ($adminLayout === null ? 0 : 1) > $request->pageLimit();
        $items = array_map(
            static fn(Booking $booking): PersonalDataEntry => new PersonalDataEntry(
                categoryId: 'booking',
                categoryLabel: 'Raumbuchung',
                reference: 'booking:' . (string)$booking->id(),
                summary: sprintf(
                    '%s, %s bis %s Uhr – %s',
                    self::germanDate($booking->startsAt()->setTimezone($timezone)),
                    $booking->startsAt()->setTimezone($timezone)->format('H:i'),
                    $booking->endsAt()->setTimezone($timezone)->format('H:i'),
                    $roomNames[$booking->roomId()] ?? 'nicht mehr vorhandener Raum',
                ),
                purpose: 'Planung der Raumnutzung und Vermeidung von Doppelbelegungen',
                source: 'Eingaben der buchenden Person oder einer berechtigten administrierenden Person',
                recipientCategories: [
                    'Alle angemeldeten Nutzer*innen der Instanz',
                    'Nextcloud-Administrator*innen mit Verwaltungsrechten',
                ],
                retention: self::retentionFor($booking, $policy, $timezone),
                thirdCountryTransfer: 'Durch AD Raumplaner sind keine Drittlandübermittlungen vorgesehen.',
                automatedDecision: 'Die automatische Kollisionsprüfung verhindert Doppelbelegungen; sie trifft keine Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung.',
                thirdPartyContentNotice: 'Der Freitext des Buchungstitels wird nicht ausgegeben, weil er Angaben zu anderen Personen enthalten kann.',
                attributes: [
                    'Raum' => $roomNames[$booking->roomId()] ?? 'nicht mehr vorhandener Raum',
                    'Zweck' => $booking->purpose(),
                    'Titel' => '[Freitext mit möglichen Drittpersonenangaben entfernt]',
                    'Beginn' => self::germanDateTime($booking->startsAt()->setTimezone($timezone)),
                    'Ende' => self::germanDateTime($booking->endsAt()->setTimezone($timezone)),
                ],
            ),
            $bookings,
        );
        foreach ($adminHistory as $grant) {
            $subjectUid = $request->subject()->subjectId();
            $roles = [];
            if ($grant['targetUid'] === $subjectUid) $roles[] = 'Ziel der Vollzugriffsfreigabe';
            if ($grant['grantedBy'] === $subjectUid) $roles[] = 'Freigebendes Mitglied von Datenschutzbeauftragte';
            if ($grant['revokedBy'] === $subjectUid) $roles[] = 'Widerrufendes Mitglied von Datenschutzbeauftragte';
            $actualEnd = $grant['revokedAt'] ?? $grant['endsAt'];
            $items[] = new PersonalDataEntry(
                categoryId: 'admin-access',
                categoryLabel: 'Zeitlich begrenzter Admin-Vollzugriff',
                reference: 'admin-access:' . (string)$grant['id'],
                summary: sprintf('%s bis %s', self::germanDateTime($grant['startsAt']->setTimezone($timezone)), self::germanDateTime($actualEnd->setTimezone($timezone))),
                purpose: 'Nachweis einer zeitlich begrenzten administrativen Fachfreigabe',
                source: 'App-lokale Freigabesteuerung im AD Raumplaner',
                recipientCategories: ['Betroffene Person und ausdrücklich berechtigte Datenschutz-Prüfrolle'],
                retention: 'Keine feste Löschfrist festgelegt; die sicherheitsrelevante Freigabehistorie bleibt bis zu einer gesonderten Aufbewahrungsentscheidung erhalten.',
                thirdCountryTransfer: 'Durch AD Raumplaner sind keine Drittlandübermittlungen vorgesehen.',
                automatedDecision: 'Der Server beendet den Vollzugriff spätestens nach 24 Stunden automatisch; es findet keine Entscheidung mit rechtlicher oder vergleichbar erheblicher Wirkung statt.',
                thirdPartyContentNotice: 'Kennungen anderer beteiligter Administrator*innen werden in dieser subjectgebundenen Auskunft nicht ausgegeben.',
                attributes: [
                    'Eigene Rolle im Vorgang' => implode(', ', $roles),
                    'Beginn' => self::germanDateTime($grant['startsAt']->setTimezone($timezone)),
                    'Geplantes Ende' => self::germanDateTime($grant['endsAt']->setTimezone($timezone)),
                    'Tatsächliches Ende' => self::germanDateTime($actualEnd->setTimezone($timezone)),
                    'Status' => $grant['revokedAt'] === null ? 'planmäßig beendet oder noch aktiv' : 'widerrufen',
                ],
            );
        }
        if ($adminLayout !== null) {
            $labels = ['rooms' => 'Räume', 'demo' => 'Demo-Daten'];
            $order = array_map(static fn(string $id): string => $labels[$id], $adminLayout['scopes']['main']['order']);
            $collapsed = array_map(static fn(string $id): string => $labels[$id], $adminLayout['scopes']['main']['collapsed']);
            $items[] = new PersonalDataEntry(
                categoryId: 'admin-layout',
                categoryLabel: 'Persönliche Adminanordnung',
                reference: 'admin-layout',
                summary: 'Persönliche Anordnung der Administrationskarten im AD Raumplaner',
                purpose: 'Wiederherstellung der persönlichen Anordnung des app-eigenen Adminbereichs',
                source: 'Eigene Eingabe im AD-Raumplaner-Adminbereich',
                recipientCategories: ['Betroffene Person'],
                retention: 'Keine feste Löschfrist und kein app-eigener Resetpfad festgelegt.',
                thirdCountryTransfer: 'Durch AD Raumplaner sind keine Drittlandübermittlungen vorgesehen.',
                automatedDecision: 'Die Präferenz steuert nur die Darstellung und keine fachliche Entscheidung.',
                thirdPartyContentNotice: 'Die Layoutpräferenz enthält keine vorgesehenen Drittpersonenangaben.',
                attributes: [
                    'Reihenfolge' => implode(', ', $order),
                    'Eingeklappt' => $collapsed === [] ? 'Keine' : implode(', ', $collapsed),
                ],
            );
        }
        foreach ($policyHistory as $entry) {
            $items[] = new PersonalDataEntry(
                categoryId: 'retention-policy',
                categoryLabel: 'Aufbewahrungsregel',
                reference: 'retention-policy:' . (string)$entry['revision'],
                summary: $entry['event'] === 'configured' ? 'Aufbewahrungsregel konfiguriert' : 'Aufbewahrungsregel geprüft',
                purpose: 'Nachweis der Konfiguration und regelmäßigen Prüfung der Aufbewahrungsregel',
                source: 'Eigene Eingabe in der Datenschutzkonfiguration des AD Raumplaners',
                recipientCategories: ['Betroffene Person und ausdrücklich berechtigte Datenschutz-Prüfrolle'],
                retention: 'Keine feste Löschfrist für die Policyhistorie festgelegt.',
                thirdCountryTransfer: 'Durch AD Raumplaner sind keine Drittlandübermittlungen vorgesehen.',
                automatedDecision: 'Die Regel erzeugt ausschließlich eine manuelle Prüfungsvorschau und keine automatische Löschung.',
                thirdPartyContentNotice: 'Die Policyhistorie enthält in dieser Auskunft keine Kennungen anderer Personen.',
                attributes: [
                    'Aufbewahrungsfrist Raumbuchungen' => $entry['durationPeriod'],
                    'Aufbewahrungsfrist Adminfreigaben' => $entry['adminHistoryDurationPeriod'],
                    'Wirksam seit' => $entry['effectiveAt'] ?? 'noch nicht wirksam gesetzt',
                    'Zuletzt geprüft' => $entry['reviewedAt'] ?? 'noch nicht geprüft',
                ],
            );
        }
        foreach ($interventionAudit as $entry) {
            $items[] = new PersonalDataEntry(
                categoryId: 'secretariat-intervention-audit',
                categoryLabel: 'Sekretariatseingriff',
                reference: 'intervention-audit:' . $entry['id'],
                summary: ($entry['action'] === 'delete' ? 'Löschung' : 'Änderung') . ' einer fremden Raumbuchung am ' . self::germanDateTime($entry['occurredAt']->setTimezone($timezone)),
                purpose: 'Nachweis eines begründeten organisatorischen Eingriffs in eine fremde Raumbuchung',
                source: 'Eigene Eingabe als Mitglied von ad-Sekretariat',
                recipientCategories: ['Betroffene handelnde Person', 'Mitglieder der Nextcloud-Gruppe Datenschutzbeauftragte im autorisierten Prüfpfad'],
                retention: 'Zwölf Monate ab dem Eingriff; anschließend vollständige Löschung.',
                thirdCountryTransfer: 'Durch AD Raumplaner sind keine Drittlandübermittlungen vorgesehen.',
                automatedDecision: 'Es findet keine automatisierte Entscheidung statt.',
                thirdPartyContentNotice: 'Buchungstitel, Zweck, Eigentümerkennung und weitere Buchungsinhalte werden im Audit nicht gespeichert.',
                attributes: [
                    'Aktion' => $entry['action'] === 'delete' ? 'Löschen' : 'Ändern',
                    'Buchungsreferenz' => (string)$entry['bookingId'],
                    'Alter Raum und Zeitraum' => self::slot($entry['oldRoom'], $entry['oldStartsAt'], $entry['oldEndsAt'], $timezone),
                    'Neuer Raum und Zeitraum' => $entry['newRoom'] === null ? 'entfällt' : self::slot($entry['newRoom'], $entry['newStartsAt'], $entry['newEndsAt'], $timezone),
                    'Begründung' => $entry['reason'],
                ],
            );
        }
        foreach ($interventionQueue as $entry) {
            $items[] = new PersonalDataEntry(
                categoryId: 'intervention-notification-queue',
                categoryLabel: 'Benachrichtigung zu einer Raumbuchung',
                reference: 'intervention-notification:' . $entry['id'],
                summary: 'Noch gespeicherter Zustellnachweis zu einer ' . ($entry['action'] === 'delete' ? 'gelöschten' : 'geänderten') . ' Raumbuchung',
                purpose: 'Zustellung der Information über einen begründeten Sekretariatseingriff',
                source: 'Automatisch nach einem autorisierten Sekretariatseingriff erzeugt',
                recipientCategories: ['Betroffene buchende Person'],
                retention: $entry['state'] === 'failed' ? 'Dreißig Tage ab dauerhaft fehlgeschlagener Zustellung; anschließend vollständige Löschung.' : 'Bei erfolgreicher Zustellung sofort löschen.',
                thirdCountryTransfer: 'Durch AD Raumplaner sind keine Drittlandübermittlungen vorgesehen.',
                automatedDecision: 'Die Zustellung wird nach 5 Minuten, 1 Stunde und 24 Stunden wiederholt; es findet keine automatisierte Entscheidung über eine Person statt.',
                thirdPartyContentNotice: 'Buchungstitel, Zweck, handelnde UID und Angaben zu anderen Buchungen werden nicht gespeichert.',
                attributes: [
                    'Aktion' => $entry['action'] === 'delete' ? 'Löschen' : 'Ändern',
                    'Alter Raum und Zeitraum' => self::slot($entry['oldRoom'], $entry['oldStartsAt'], $entry['oldEndsAt'], $timezone),
                    'Neuer Raum und Zeitraum' => $entry['newRoom'] === null ? 'entfällt' : self::slot($entry['newRoom'], $entry['newStartsAt'], $entry['newEndsAt'], $timezone),
                    'Begründung' => $entry['reason'],
                    'Zustellstatus' => $entry['state'] === 'failed' ? 'dauerhaft fehlgeschlagen' : 'ausstehend',
                ],
            );
        }
        if ($limited) {
            $items = array_slice($items, 0, $request->pageLimit());
            $restrictions[] = 'Ausgabelimit erreicht; weitere Raumbuchungen können vorhanden sein.';
        }

        if ($items === [] && $restrictions === []) {
            return new PersonalDataPage('not_applicable');
        }

        return new PersonalDataPage(
            $restrictions === [] ? 'complete' : 'partial',
            $items,
            $restrictions,
        );
    }

    private static function retentionFor(Booking $booking, array $policy, \DateTimeZone $timezone): string {
        $reviewAt = $booking->endsAt()->add(new \DateInterval($policy['durationPeriod']));
        return sprintf('Löschfrist %s; automatische Löschung nur bei technischer Operatoraktivierung im Datenschutz-Center, sonst REVIEW.', self::germanDate($reviewAt->setTimezone($timezone)));
    }

    private static function germanDate(\DateTimeImmutable $date): string { return $date->format('d.m.y'); }

    private static function germanDateTime(\DateTimeImmutable $date): string {
        return self::germanDate($date) . ', ' . $date->format('H:i') . ' Uhr';
    }

    private static function slot(string $room, \DateTimeImmutable $start, \DateTimeImmutable $end, \DateTimeZone $timezone): string {
        return sprintf('%s, %s bis %s', $room, self::germanDateTime($start->setTimezone($timezone)), $end->setTimezone($timezone)->format('H:i') . ' Uhr');
    }
}
