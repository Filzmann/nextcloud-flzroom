<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Repository\BookingRepository;
use OCA\AdRoom\Repository\RoomRepository;
use OCA\AdRoom\Repository\TemporaryAdminAccessRepository;
use OCA\AdRoom\Service\RoomRetentionPolicyService;
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
        $limited = count($bookings) + count($adminHistory) > $request->pageLimit();
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
            if ($grant['grantedBy'] === $subjectUid) $roles[] = 'Freigebende Administration';
            if ($grant['revokedBy'] === $subjectUid) $roles[] = 'Widerrufende Administration';
            $actualEnd = $grant['revokedAt'] ?? $grant['endsAt'];
            $items[] = new PersonalDataEntry(
                categoryId: 'admin-access',
                categoryLabel: 'Zeitlich begrenzter Admin-Vollzugriff',
                reference: 'admin-access:' . (string)$grant['id'],
                summary: sprintf('%s bis %s', self::germanDateTime($grant['startsAt']->setTimezone($timezone)), self::germanDateTime($actualEnd->setTimezone($timezone))),
                purpose: 'Nachweis einer zeitlich begrenzten administrativen Fachfreigabe',
                source: 'App-lokale Freigabe im Nextcloud-Adminbereich',
                recipientCategories: ['Berechtigte Nextcloud-Administrator*innen und prüfberechtigte Stellen'],
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
        if ($limited) $items = array_slice($items, 0, $request->pageLimit());

        if ($items === []) {
            return new PersonalDataPage('not_applicable');
        }

        return new PersonalDataPage(
            $limited ? 'partial' : 'complete',
            $items,
            $limited ? ['Ausgabelimit erreicht; weitere Raumbuchungen können vorhanden sein.'] : [],
        );
    }

    private static function retentionFor(Booking $booking, array $policy, \DateTimeZone $timezone): string {
        if (!$policy['enabled']) return 'Keine feste Löschfrist festgelegt; die administrative Retention-Prüfung ist derzeit deaktiviert.';
        $reviewAt = $booking->endsAt()->modify('+' . $policy['reviewAfterDays'] . ' days');
        return sprintf('Keine feste Löschfrist festgelegt; ab %s zur administrativen Prüfung vorgesehen. Es erfolgt keine automatische Löschung.', self::germanDate($reviewAt->setTimezone($timezone)));
    }

    private static function germanDate(\DateTimeImmutable $date): string { return $date->format('d.m.y'); }

    private static function germanDateTime(\DateTimeImmutable $date): string {
        return self::germanDate($date) . ', ' . $date->format('H:i') . ' Uhr';
    }
}
