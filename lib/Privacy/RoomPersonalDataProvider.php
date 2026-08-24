<?php

declare(strict_types=1);

namespace OCA\AdRoom\Privacy;

use OCA\AdRoom\AppInfo\AppId;
use OCA\AdRoom\Model\Booking;
use OCA\AdRoom\Repository\BookingRepository;
use OCA\AdRoom\Repository\RoomRepository;
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
        $limited = count($bookings) > $request->pageLimit();
        if ($limited) {
            $bookings = array_slice($bookings, 0, $request->pageLimit());
        }
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
