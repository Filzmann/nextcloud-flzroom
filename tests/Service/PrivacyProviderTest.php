<?php

declare(strict_types=1);

namespace OCP\EventDispatcher {
    class Event { public function __construct() {} }
    interface IEventListener { public function handle(Event $event): void; }
}
namespace OCP {
    interface IAppConfig {
        public function getValueString(string $appId, string $key, string $default = ''): string;
        public function setValueString(string $appId, string $key, string $value): void;
    }
}
namespace OCP\Config {
    interface IUserConfig {
        public function getValueArray(string $userId, string $appId, string $key, array $default = [], bool $lazy = false): array;
        public function setValueArray(string $userId, string $appId, string $key, array $value, bool $lazy = false): void;
    }
}
namespace OCP\AppFramework\Utility { interface ITimeFactory { public function getTime(): int; } }
namespace Psr\Log {
    interface LoggerInterface { public function warning(string $message, array $context = []): void; }
}

namespace OCA\AdRoom\Repository {
    use OCA\AdRoom\Model\Booking;
    use OCA\AdRoom\Model\Room;
    class BookingRepository {
        /** @var list<Booking> */ public array $items = [];
        public int $deleteCalls = 0;
        public function findByUserUid(string $uid, int $limit): array {
            return array_slice(array_values(array_filter($this->items, static fn(Booking $booking): bool => $booking->userUid() === $uid)), 0, $limit);
        }
        public function findEndedByUserUid(string $uid, \DateTimeImmutable $cutoff, int $limit): array {
            return array_slice(array_values(array_filter($this->items, static fn(Booking $booking): bool => $booking->userUid() === $uid && $booking->endsAt() <= $cutoff)), 0, $limit);
        }
        public function delete(int $id): void { $this->deleteCalls++; }
    }
    class RoomRepository {
        /** @return list<Room> */
        public function findAll(): array { return [Room::get(['id' => 2, 'name' => 'Besprechung 1', 'description' => '', 'sortOrder' => 1])]; }
    }
    class TemporaryAdminAccessRepository {
        public function historyForUid(string $uid, int $limit): array {
            if ($uid !== 'user-17') return [];
            return [[
                'id'=>9,'targetUid'=>'user-17','grantedBy'=>'admin-other',
                'startsAt'=>new \DateTimeImmutable('2026-08-05T10:00:00+00:00'),
                'endsAt'=>new \DateTimeImmutable('2026-08-05T11:00:00+00:00'),
                'revokedAt'=>null,'revokedBy'=>null,
            ]];
        }
    }
}

namespace {
    use OCA\AdRoom\Model\Booking;
    use OCA\AdRoom\Privacy\RoomPersonalDataProvider;
    use OCA\AdRoom\Privacy\RoomPersonalDataProviderListener;
    use OCA\AdRoom\Privacy\RoomPrivacyProviderListener;
    use OCA\AdRoom\Privacy\RoomRetentionProvider;
    use OCA\AdRoom\Repository\BookingRepository;
    use OCA\AdRoom\Repository\RoomRepository;
    use OCA\AdRoom\Repository\TemporaryAdminAccessRepository;
    use OCA\AdRoom\Service\RoomAdminLayoutService;
    use OCA\AdRoom\Service\RoomRetentionPolicyService;
    use OCA\LocalBase\Calendar\CalendarContextSettingsService;
    use OCA\LocalBase\Privacy\RetentionPreviewRequest;
    use OCA\LocalBase\Privacy\RetentionProviderRegistryEvent;
    use OCA\LocalBase\Privacy\PersonalDataSubject;
    use OCA\FilzmannDataProtection\PublicApi\V1\DataSubjectRef;
    use OCA\FilzmannDataProtection\PublicApi\V1\PersonalDataRequest;
    use OCA\FilzmannDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;

    $repository = new BookingRepository();
    $repository->items = [
        Booking::get(['id' => 1, 'roomId' => 2, 'userUid' => 'user-17', 'purpose' => 'Sitzung', 'title' => 'Team', 'startsAt' => '2026-08-02T01:05:00+00:00', 'endsAt' => '2026-08-02T01:10:00+00:00']),
        Booking::get(['id' => 2, 'roomId' => 3, 'userUid' => 'foreign', 'purpose' => 'BQ', 'title' => 'Fremd', 'startsAt' => '2026-08-03T08:00:00+00:00', 'endsAt' => '2026-08-03T09:00:00+00:00']),
    ];
    $subject = new DataSubjectRef('nextcloud-user', 'user-17');
    $config = new class implements OCP\IAppConfig, OCP\Config\IUserConfig {
        public array $values = [];
        public array $userValues = [];
        public function getValueString(string $appId, string $key, string $default = ''): string { return $this->values[$appId][$key] ?? $default; }
        public function setValueString(string $appId, string $key, string $value): void { $this->values[$appId][$key] = $value; }
        public function getValueArray(string $userId, string $appId, string $key, array $default = [], bool $lazy = false): array { return $this->userValues[$userId][$appId][$key] ?? $default; }
        public function setValueArray(string $userId, string $appId, string $key, array $value, bool $lazy = false): void { $this->userValues[$userId][$appId][$key] = $value; }
    };
    $config->userValues['user-17']['adroom']['admin_dashboard_layout'] = ['version'=>1,'scopes'=>['main'=>['order'=>['demo','rooms','retention'],'collapsed'=>['retention']]],'organigram'=>['zoom'=>100]];
    $config->userValues['foreign-user']['adroom']['admin_dashboard_layout'] = ['version'=>1,'scopes'=>['main'=>['order'=>['retention','rooms','demo'],'collapsed'=>['rooms']]],'organigram'=>['zoom'=>100]];
    $logger = new class implements Psr\Log\LoggerInterface { public function warning(string $message, array $context = []): void {} };
    $policy = new RoomRetentionPolicyService($config);
    $policy->save(['enabled' => true, 'reviewAfterDays' => 5, 'action' => 'REVIEW']);
    $clock = new class implements OCP\AppFramework\Utility\ITimeFactory { public function getTime(): int { return strtotime('2026-08-12T12:00:00+00:00'); } };
    $personal = new RoomPersonalDataProvider($repository, new RoomRepository(), $policy, new CalendarContextSettingsService($config), new TemporaryAdminAccessRepository(), new RoomAdminLayoutService($config, $logger));
    $report = $personal->collect(new PersonalDataRequest($subject, 'de', 'access-report', 20, []));
    if (count($report->entries()) !== 3 || $report->status() !== 'complete') throw new RuntimeException('Provider lässt Buchungen, Admin-Freigabehistorie oder das persönliche Adminlayout der betroffenen UID aus.');
    $item = $report->entries()[0]->toArray();
    if ($item['reference'] !== 'booking:1' || isset($item['attributes']['userUid']) || str_contains(json_encode($item, JSON_THROW_ON_ERROR), 'Fremd')) throw new RuntimeException('Providerbericht ist nicht referenzierbar, datensparsam oder nicht subjectgebunden.');
    if (($item['attributes']['Titel'] ?? null) !== '[Freitext mit möglichen Drittpersonenangaben entfernt]' || str_contains(json_encode($item, JSON_THROW_ON_ERROR), 'Team')) throw new RuntimeException('Mögliche Drittpersonenangaben im freien Titel wurden nicht kontextbewahrend entfernt.');
    foreach (['Raum', 'Zweck', 'Titel', 'Beginn', 'Ende'] as $label) if (!array_key_exists($label, $item['attributes'])) throw new RuntimeException("Deutsche Detailbezeichnung fehlt: {$label}");
    foreach (['purpose', 'title', 'startsAt', 'endsAt'] as $technical) if (array_key_exists($technical, $item['attributes'])) throw new RuntimeException("Technischer Feldname ist sichtbar: {$technical}");
    foreach (['02.08.26', '03:05 bis 03:10 Uhr', 'Planung der Raumnutzung'] as $expected) {
        if (!str_contains(json_encode($item, JSON_THROW_ON_ERROR), $expected)) throw new RuntimeException("Menschenlesbare Raumbuchung fehlt: {$expected}");
    }
    if (!str_contains($item['retention'] ?? '', '07.08.26')) throw new RuntimeException('Datensatzbezogenes Retention-Datum fehlt.');
    if (!in_array('Alle angemeldeten Nutzer*innen der Instanz', $item['recipientCategories'], true)
        || !str_contains($item['retention'], '07.08.26')
        || !str_contains($item['automatedDecision'], 'Kollisionsprüfung')
        || !str_contains((string)$item['thirdPartyContentNotice'], 'Freitext')) {
        throw new RuntimeException('Art.-15-Verarbeitungsangaben des Raumplaners fehlen oder sind unzutreffend.');
    }
    $adminAudit = $report->entries()[1]->toArray();
    if ($adminAudit['reference'] !== 'admin-access:9' || str_contains(json_encode($adminAudit, JSON_THROW_ON_ERROR), 'admin-other')) throw new RuntimeException('Admin-Freigabeaudit fehlt oder legt eine Drittpersonen-UID offen.');
    $adminLayout = $report->entries()[2]->toArray();
    if ($adminLayout['reference'] !== 'admin-layout'
        || ($adminLayout['attributes']['Reihenfolge'] ?? null) !== 'Demo-Daten, Räume, Aufbewahrung'
        || ($adminLayout['attributes']['Eingeklappt'] ?? null) !== 'Aufbewahrung'
        || str_contains(json_encode($adminLayout, JSON_THROW_ON_ERROR), 'foreign-user')) {
        throw new RuntimeException('Das persönliche Adminlayout fehlt, ist nicht verständlich oder legt einen fremden UserConfig-Wert offen.');
    }
    unset($config->userValues['user-17']['adroom']['admin_dashboard_layout']);
    $reportWithoutStoredLayout = $personal->collect(new PersonalDataRequest($subject, 'de', 'access-report', 20, []));
    foreach ($reportWithoutStoredLayout->entries() as $entry) {
        if ($entry->toArray()['reference'] === 'admin-layout') {
            throw new RuntimeException('Das nicht persistierte Standardlayout wurde als gespeicherte Personendate ausgegeben.');
        }
    }
    $config->userValues['user-17']['adroom']['admin_dashboard_layout'] = ['version'=>2];
    $reportWithInvalidLayout = $personal->collect(new PersonalDataRequest($subject, 'de', 'access-report', 20, []));
    if ($reportWithInvalidLayout->status() !== 'partial'
        || $reportWithInvalidLayout->restrictions() !== ['Das gespeicherte persönliche Adminlayout konnte nicht sicher ausgegeben werden.']) {
        throw new RuntimeException('Ein ungültiges gespeichertes Adminlayout wird nicht als unvollständige Auskunft ausgewiesen.');
    }
    unset($config->userValues['user-17']['adroom']['admin_dashboard_layout']);
    $foreignSubjectReport = $personal->collect(new PersonalDataRequest(
        new DataSubjectRef('external-applicant', 'user-17'),
        'de',
        'access-report',
        20,
        [],
    ));
    if ($foreignSubjectReport->status() !== 'not_applicable' || $foreignSubjectReport->entries() !== []) {
        throw new RuntimeException('Ein nicht unterstützter Subject-Typ erhielt Nextcloud-Buchungsdaten.');
    }
    $repository->items[] = Booking::get(['id' => 3, 'roomId' => 2, 'userUid' => 'user-17', 'purpose' => 'Fortbildung', 'title' => 'Weitere Person', 'startsAt' => '2026-08-04T08:00:00+00:00', 'endsAt' => '2026-08-04T09:00:00+00:00']);
    $limitedReport = $personal->collect(new PersonalDataRequest($subject, 'de', 'access-report', 1, []));
    if ($limitedReport->status() !== 'partial' || $limitedReport->restrictions() === []) throw new RuntimeException('Begrenzter Raumbuchungsbericht behauptet Vollständigkeit oder begründet die Einschränkung nicht.');
    array_pop($repository->items);

    $retention = new RoomRetentionProvider($repository, $policy, $clock);
    $retentionSubject = new PersonalDataSubject(PersonalDataSubject::NEXTCLOUD_USER, 'user-17');
    $preview = $retention->preview(new RetentionPreviewRequest($retentionSubject, 20));
    if (count($preview->candidates()) !== 1 || $preview->candidates()[0]->toArray()['action'] !== 'REVIEW') throw new RuntimeException('Retention-Dry-Run fehlt.');
    if ($repository->deleteCalls !== 0) throw new RuntimeException('Retention-Preview verändert Buchungen.');
    $policy->save(['enabled' => false, 'reviewAfterDays' => 0, 'action' => 'REVIEW']);
    if ($retention->preview(new RetentionPreviewRequest($retentionSubject, 20))->candidates() !== []) throw new RuntimeException('Deaktivierte Retention liefert Kandidaten.');

    $personalListener = new RoomPersonalDataProviderListener($personal);
    $personalRegistry = new RegisterPersonalDataProvidersEvent();
    $personalListener->handle($personalRegistry);
    $retentionListener = new RoomPrivacyProviderListener($retention);
    $retentionRegistry = new RetentionProviderRegistryEvent();
    $retentionListener->handle($retentionRegistry);
    if (array_keys($personalRegistry->providers()) !== ['adroom'] || array_keys($retentionRegistry->providers()) !== ['adroom']) throw new RuntimeException('AD Raumplaner registriert seine Privacy-Provider nicht.');

    echo "AD Raumplaner privacy provider test passed\n";
}
