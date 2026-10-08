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
    interface IUser { public function getUID(): string; }
    interface IUserSession { public function getUser(): ?IUser; }
    interface IGroupManager { public function isInGroup(string $uid, string $gid): bool; }
}
namespace OCP\Config {
    interface IUserConfig {
        public function getValueArray(string $userId, string $appId, string $key, array $default = [], bool $lazy = false): array;
        public function setValueArray(string $userId, string $appId, string $key, array $value, bool $lazy = false): void;
    }
}
namespace OCP\AppFramework\Utility { interface ITimeFactory { public function getTime(): int; public function now(): \DateTimeImmutable; } }
namespace Psr\Log {
    interface LoggerInterface { public function warning(string $message, array $context = []): void; }
}

namespace OCA\FlzRoom\Repository {
    use OCA\FlzRoom\Model\Booking;
    use OCA\FlzRoom\Model\Room;
    class BookingRepository {
        /** @var list<Booking> */ public array $items = [];
        public int $deleteCalls = 0;
        public function findByUserUid(string $uid, int $limit): array {
            return array_slice(array_values(array_filter($this->items, static fn(Booking $booking): bool => $booking->userUid() === $uid)), 0, $limit);
        }
        public function findEndedByUserUid(string $uid, \DateTimeImmutable $cutoff, int $limit): array {
            return array_slice(array_values(array_filter($this->items, static fn(Booking $booking): bool => $booking->userUid() === $uid && $booking->endsAt() <= $cutoff)), 0, $limit);
        }
        public function findEndedBefore(\DateTimeImmutable $cutoff, int $limit, int $offset = 0): array {
            $items = array_values(array_filter($this->items, static fn(Booking $booking): bool => $booking->endsAt() <= $cutoff));
            usort($items, static fn(Booking $left, Booking $right): int => [$left->endsAt(), $left->id()] <=> [$right->endsAt(), $right->id()]);
            return array_slice($items, $offset, $limit);
        }
        public function delete(int $id): void { $this->deleteCalls++; }
    }
    class RoomRepository {
        /** @return list<Room> */
        public function findAll(): array { return [Room::get(['id' => 2, 'name' => 'Besprechung 1', 'description' => '', 'sortOrder' => 1])]; }
    }
    class TemporaryAdminAccessRepository {
        public array $items = [];
        public array $previewRequests = [];
        public function historyForUid(string $uid, int $limit): array {
            if ($uid !== 'user-17') return [];
            return [[
                'id'=>9,'targetUid'=>'user-17','grantedBy'=>'admin-other',
                'startsAt'=>new \DateTimeImmutable('2026-08-05T10:00:00+00:00'),
                'endsAt'=>new \DateTimeImmutable('2026-08-05T11:00:00+00:00'),
                'revokedAt'=>null,'revokedBy'=>null,
            ]];
        }
        public function endedBefore(\DateTimeImmutable $cutoff, int $limit, int $offset): array {
            $this->previewRequests[] = [$cutoff->format(DATE_ATOM), $limit, $offset];
            return array_slice($this->items, $offset, $limit);
        }
    }
}

namespace {
    use OCA\FlzRoom\Model\Booking;
    use OCA\FlzRoom\Privacy\RoomPersonalDataProvider;
    use OCA\FlzRoom\Privacy\RoomPersonalDataProviderListener;
    use OCA\FlzRoom\Privacy\RoomPrivacyProviderListener;
    use OCA\FlzRoom\Privacy\RoomRetentionProvider;
    use OCA\FlzRoom\Repository\BookingRepository;
    use OCA\FlzRoom\Repository\RoomRepository;
    use OCA\FlzRoom\Repository\TemporaryAdminAccessRepository;
    use OCA\FlzRoom\Service\RoomAdminLayoutService;
    use OCA\FlzRoom\Service\RoomRetentionPolicyService;
    use OCA\LocalBase\Calendar\CalendarContextSettingsService;
    use OCA\FlzDataProtection\PublicApi\V1\RetentionPreviewRequest;
    use OCA\FlzDataProtection\PublicApi\V1\RegisterRetentionProvidersEvent;
    use OCA\FlzDataProtection\PublicApi\V1\DataSubjectRef;
    use OCA\FlzDataProtection\PublicApi\V1\PersonalDataRequest;
    use OCA\FlzDataProtection\PublicApi\V1\RegisterPersonalDataProvidersEvent;

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
    $config->userValues['user-17']['flzroom']['admin_dashboard_layout'] = ['version'=>1,'scopes'=>['main'=>['order'=>['demo','rooms','retention'],'collapsed'=>['retention']]],'organigram'=>['zoom'=>100]];
    $config->userValues['foreign-user']['flzroom']['admin_dashboard_layout'] = ['version'=>1,'scopes'=>['main'=>['order'=>['retention','rooms','demo'],'collapsed'=>['rooms']]],'organigram'=>['zoom'=>100]];
    $logger = new class implements Psr\Log\LoggerInterface { public function warning(string $message, array $context = []): void {} };
    $groups = new class implements OCP\IGroupManager { public function isInGroup(string $uid, string $gid): bool { return $uid === 'user-17' && $gid === 'Datenschutzbeauftragte'; } };
    $session = new class implements OCP\IUserSession { public function getUser(): ?OCP\IUser { return new class implements OCP\IUser { public function getUID(): string { return 'user-17'; } }; } };
    $clock = new class implements OCP\AppFramework\Utility\ITimeFactory {
        public function getTime(): int { return strtotime('2026-08-12T12:00:00+00:00'); }
        public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-08-12T12:00:00+00:00'); }
    };
    $policy = new RoomRetentionPolicyService($config, $groups, $session, $clock);
    $policy->save(['durationPeriod' => 'P30D', 'adminHistoryDurationPeriod' => 'P6M', 'expectedRevision' => 0]);
    $personal = new RoomPersonalDataProvider($repository, new RoomRepository(), $policy, new CalendarContextSettingsService($config), new TemporaryAdminAccessRepository(), new RoomAdminLayoutService($config, $logger));
    $report = $personal->collect(new PersonalDataRequest($subject, 'de', 'access-report', 20, []));
    if (count($report->entries()) !== 4 || $report->status() !== 'complete') throw new RuntimeException('Provider lässt Buchungen, Admin-Freigabehistorie, persönliche Adminlayouts oder Policybearbeitungen der betroffenen UID aus.');
    $item = $report->entries()[0]->toArray();
    if ($item['reference'] !== 'booking:1' || isset($item['attributes']['userUid']) || str_contains(json_encode($item, JSON_THROW_ON_ERROR), 'Fremd')) throw new RuntimeException('Providerbericht ist nicht referenzierbar, datensparsam oder nicht subjectgebunden.');
    if (($item['attributes']['Titel'] ?? null) !== '[Freitext mit möglichen Drittpersonenangaben entfernt]' || str_contains(json_encode($item, JSON_THROW_ON_ERROR), 'Team')) throw new RuntimeException('Mögliche Drittpersonenangaben im freien Titel wurden nicht kontextbewahrend entfernt.');
    foreach (['Raum', 'Zweck', 'Titel', 'Beginn', 'Ende'] as $label) if (!array_key_exists($label, $item['attributes'])) throw new RuntimeException("Deutsche Detailbezeichnung fehlt: {$label}");
    foreach (['purpose', 'title', 'startsAt', 'endsAt'] as $technical) if (array_key_exists($technical, $item['attributes'])) throw new RuntimeException("Technischer Feldname ist sichtbar: {$technical}");
    foreach (['02.08.26', '03:05 bis 03:10 Uhr', 'Planung der Raumnutzung'] as $expected) {
        if (!str_contains(json_encode($item, JSON_THROW_ON_ERROR), $expected)) throw new RuntimeException("Menschenlesbare Raumbuchung fehlt: {$expected}");
    }
    if (!str_contains($item['retention'] ?? '', '01.09.26')) throw new RuntimeException('Datensatzbezogenes Retention-Datum fehlt.');
    if (!in_array('Alle angemeldeten Nutzer*innen der Instanz', $item['recipientCategories'], true)
        || !str_contains($item['retention'], '01.09.26')
        || !str_contains($item['automatedDecision'], 'Kollisionsprüfung')
        || !str_contains((string)$item['thirdPartyContentNotice'], 'Freitext')) {
        throw new RuntimeException('Art.-15-Verarbeitungsangaben des Raumplaners fehlen oder sind unzutreffend.');
    }
    $adminAudit = $report->entries()[1]->toArray();
    if ($adminAudit['reference'] !== 'admin-access:9' || str_contains(json_encode($adminAudit, JSON_THROW_ON_ERROR), 'admin-other')) throw new RuntimeException('Admin-Freigabeaudit fehlt oder legt eine Drittpersonen-UID offen.');
    if (($adminAudit['source'] ?? null) !== 'App-lokale Freigabesteuerung im Filzmann Raumplaner'
        || !str_contains(json_encode($adminAudit['recipientCategories'], JSON_THROW_ON_ERROR), 'Datenschutz')) {
        throw new RuntimeException('Admin-Freigabeaudit projiziert die fachliche Freigaberolle oder Quelle nicht korrekt.');
    }
    $adminLayout = $report->entries()[2]->toArray();
    if ($adminLayout['reference'] !== 'admin-layout'
        || ($adminLayout['attributes']['Reihenfolge'] ?? null) !== 'Demo-Daten, Räume'
        || ($adminLayout['attributes']['Eingeklappt'] ?? null) !== 'Keine'
        || str_contains(json_encode($adminLayout, JSON_THROW_ON_ERROR), 'foreign-user')) {
        throw new RuntimeException('Das persönliche Adminlayout fehlt, ist nicht verständlich oder legt einen fremden UserConfig-Wert offen.');
    }
    $policyAudit = $report->entries()[3]->toArray();
    if ($policyAudit['reference'] !== 'retention-policy:1'
        || ($policyAudit['attributes']['Aufbewahrungsfrist Raumbuchungen'] ?? null) !== 'P30D'
        || ($policyAudit['attributes']['Aufbewahrungsfrist Adminfreigaben'] ?? null) !== 'P6M'
        || str_contains(json_encode($policyAudit, JSON_THROW_ON_ERROR), 'foreign-user')) {
        throw new RuntimeException('Die eigene Policybearbeitung fehlt oder legt eine fremde Kennung offen.');
    }
    unset($config->userValues['user-17']['flzroom']['admin_dashboard_layout']);
    $reportWithoutStoredLayout = $personal->collect(new PersonalDataRequest($subject, 'de', 'access-report', 20, []));
    foreach ($reportWithoutStoredLayout->entries() as $entry) {
        if ($entry->toArray()['reference'] === 'admin-layout') {
            throw new RuntimeException('Das nicht persistierte Standardlayout wurde als gespeicherte Personendate ausgegeben.');
        }
    }
    $config->userValues['user-17']['flzroom']['admin_dashboard_layout'] = ['version'=>2];
    $reportWithInvalidLayout = $personal->collect(new PersonalDataRequest($subject, 'de', 'access-report', 20, []));
    if ($reportWithInvalidLayout->status() !== 'partial'
        || $reportWithInvalidLayout->restrictions() !== ['Das gespeicherte persönliche Adminlayout konnte nicht sicher ausgegeben werden.']) {
        throw new RuntimeException('Ein ungültiges gespeichertes Adminlayout wird nicht als unvollständige Auskunft ausgewiesen.');
    }
    unset($config->userValues['user-17']['flzroom']['admin_dashboard_layout']);
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

    $repository->items[] = Booking::get(['id' => 4, 'roomId' => 2, 'userUid' => 'foreign', 'purpose' => 'Alt', 'title' => 'Nicht ausgeben', 'startsAt' => '2025-01-01T08:00:00+00:00', 'endsAt' => '2025-01-01T09:00:00+00:00']);
    $repository->items[] = Booking::get(['id' => 5, 'roomId' => 2, 'userUid' => 'user-17', 'purpose' => 'Alt', 'title' => 'Nicht ausgeben', 'startsAt' => '2025-01-05T08:00:00+00:00', 'endsAt' => '2025-01-05T09:00:00+00:00']);
    $adminHistory = new TemporaryAdminAccessRepository();
    $adminHistory->items = [[
        'id'=>11,'targetUid'=>'admin-target','grantedBy'=>'dpo',
        'startsAt'=>new DateTimeImmutable('2024-01-10T08:00:00+00:00'),
        'endsAt'=>new DateTimeImmutable('2024-01-10T12:00:00+00:00'),
        'revokedAt'=>new DateTimeImmutable('2024-01-10T09:00:00+00:00'),'revokedBy'=>'dpo',
    ]];
    $retention = new RoomRetentionProvider($repository, $adminHistory, $policy);
    $policies = $retention->policies();
    if (count($policies) !== 2
        || ($policies[0]->toArray()['version'] ?? null) !== '1.1'
        || ($policies[1]->toArray()['durationPeriod'] ?? null) !== 'P6M'
        || ($policies[1]->toArray()['version'] ?? null) !== '1.1') {
        throw new RuntimeException('Die beiden app-lokalen Retention-Policies sind nicht gemeinsam versioniert projiziert.');
    }
    $preview = $retention->preview(new RetentionPreviewRequest('room_booking_review', '2025-02-10T12:00:00+00:00', 1));
    if ($preview->status() !== 'partial' || count($preview->candidates()) !== 1 || $preview->candidates()[0]->toArray()['action'] !== 'REVIEW' || $preview->nextCursor() === null) throw new RuntimeException('Globale Retention-Vorschau oder Pagination fehlt.');
    $continued = $retention->preview(new RetentionPreviewRequest('room_booking_review', '2025-02-10T12:00:00+00:00', 1, $preview->nextCursor()));
    if ($continued->status() !== 'complete' || count($continued->candidates()) !== 1 || str_contains(json_encode($continued->candidates()[0]->toArray(), JSON_THROW_ON_ERROR), 'user-17')) throw new RuntimeException('Retention-Folgeseite ist unvollständig oder legt personenbezogene Inhalte offen.');
    if ($retention->preview(new RetentionPreviewRequest('unknown_policy', '2026-08-12T12:00:00+00:00', 20))->status() !== 'not_applicable') throw new RuntimeException('Eine unbekannte Retention-Policy wird nicht kontrolliert abgelehnt.');
    try {
        $retention->preview(new RetentionPreviewRequest('room_booking_review', '2025-02-10T12:00:00+00:00', 20, 'manipulated'));
        throw new RuntimeException('Ein manipulierter Provider-Cursor wurde akzeptiert.');
    } catch (InvalidArgumentException) {
    }
    if ($repository->deleteCalls !== 0) throw new RuntimeException('Retention-Preview verändert Buchungen.');
    $adminPreview = $retention->preview(new RetentionPreviewRequest('temporary_admin_access_history_review', '2025-02-10T12:00:00+00:00', 20));
    if (($adminHistory->previewRequests[0][0] ?? null) !== '2024-08-10T12:00:00+00:00'
        || $adminPreview->status() !== 'complete'
        || ($adminPreview->candidates()[0]->toArray()['occurredAt'] ?? null) !== '2024-01-10T09:00:00+00:00') {
        throw new RuntimeException('Die Adminfreigabehistorie wird nicht ab ihrem tatsächlichen Ende mit der aktuellen Frist neu bewertet.');
    }
    if (method_exists($retention, 'execute')) throw new RuntimeException('Der V1-Provider darf vor Abschluss von DP-07 keinen Ausführungspfad anbieten.');

    $personalListener = new RoomPersonalDataProviderListener($personal);
    $personalRegistry = new RegisterPersonalDataProvidersEvent();
    $personalListener->handle($personalRegistry);
    $retentionListener = new RoomPrivacyProviderListener($retention);
    $retentionRegistry = new RegisterRetentionProvidersEvent();
    $retentionListener->handle($retentionRegistry);
    if (array_keys($personalRegistry->providers()) !== ['flzroom'] || array_keys($retentionRegistry->providers()) !== ['flzroom']) throw new RuntimeException('Filzmann Raumplaner registriert seine Privacy-Provider nicht.');
    $application = (string)file_get_contents(dirname(__DIR__, 2) . '/lib/AppInfo/Application.php');
    if (!str_contains($application, 'registerEventListener(RegisterRetentionProvidersEvent::class, RoomPrivacyProviderListener::class)')
        || str_contains($application, 'RetentionProviderRegistryEvent')) {
        throw new RuntimeException('Der Bootstrap verwendet nicht ausschließlich den Standalone-V1-Retention-Vertrag.');
    }

    echo "Filzmann Raumplaner privacy provider test passed\n";
}
