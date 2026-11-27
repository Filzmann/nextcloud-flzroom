<?php

declare(strict_types=1);

// This entrypoint only accepts the disposable SQLite clone owned by the lifecycle runner.
if (PHP_SAPI !== 'cli')
{
    http_response_code(404);
    exit(1);
}
$root = realpath($argv[1] ?? '');
if ($root === false || !preg_match('~^/tmp/flzroom-job-lifecycle\.[A-Za-z0-9]+/server$~D', $root))
{
    throw new RuntimeException('Run this test through the isolated lifecycle runner.');
}
define('OC_CONSOLE', true);
require $root . '/lib/base.php';
set_exception_handler(static function (Throwable $error): never
{
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
});

use OCA\FlzDataProtection\PublicApi\V1\ScopeAuthorizationQueryEvent;
use OCA\FlzDataProtection\Service\RiskScopeAuthorizationService;
use OCA\FlzOrganization\Organization\OrganizationDefinition;
use OCA\FlzOrganization\Service\OrganizationAdminService;
use OCA\FlzRoom\Controller\ApiController;
use OCA\FlzRoom\Controller\InterventionAuditController;
use OCA\FlzRoom\BackgroundJob\InterventionNotificationJob;
use OCA\FlzRoom\Repository\BookingInterventionAuditRepository;
use OCA\FlzRoom\Repository\InterventionNotificationQueueRepository;
use OCA\FlzRoom\Service\BookingService;
use OCA\FlzRoom\Service\InterventionNotificationDeliveryService;
use OCP\Notification\IApp;
use OCP\Notification\IManager;
use OCP\Notification\INotification;
use OCP\Server;

/** Observes the public notification fan-out while the real native app still persists it. */
final class SyntheticInterventionNotificationObserver implements IApp
{
    public static array $delivered = [];

    public function notify(INotification $notification): void
    {
        if ($notification->getApp() === 'flzroom')
        {
            self::$delivered[] = clone $notification;
        }
    }

    public function markProcessed(INotification $notification): void
    {
    }

    public function getCount(INotification $notification): int
    {
        return 0;
    }
}

$assert = static function (bool $condition, string $message): void
{
    if (!$condition)
    {
        throw new RuntimeException($message);
    }
};
$config = Server::get(OCP\IConfig::class);
$assert($config->getSystemValue('dbtype') === 'sqlite3', 'The fixture must use its own SQLite database.');
$assert(str_starts_with($config->getSystemValue('datadirectory'), dirname($root) . '/'), 'Fixture data escaped isolation.');
$org = Server::get(OrganizationAdminService::class);
$definition = OrganizationDefinition::defaults();
$saved = $org->saveOrganizationDefinition($definition->toArray());
$assert($saved['success'] === true, 'The synthetic organization could not be initialized.');

$users = Server::get(OCP\IUserManager::class);
$groups = Server::get(OCP\IGroupManager::class);
$session = Server::get(OCP\IUserSession::class);
$actors = [];
foreach (['owner', 'secretariat', 'outsider', 'dpo'] as $role)
{
    $user = $users->createUser('synthetic-intervention-' . $role, bin2hex(random_bytes(24)));
    $assert($user instanceof OCP\IUser, 'Synthetic account creation failed.');
    $assert(($user->getEMailAddress() ?? '') === '', 'Synthetic test accounts must have no email address.');
    $actors[$role] = $user;
}
$membership = [
    'owner' => $definition->roles()['pfk']['groupId'],
    'secretariat' => $definition->roles()['secretariat']['groupId'],
    'outsider' => $definition->roles()['pfk']['groupId'],
    'dpo' => RiskScopeAuthorizationService::CONFIGURATOR_GROUP,
];
foreach ($membership as $role => $groupId)
{
    $group = $groups->get($groupId) ?? $groups->createGroup($groupId);
    $assert($group instanceof OCP\IGroup, 'Synthetic role group creation failed.');
    $group->addUser($actors[$role]);
}

// A raw CLI bootstrap does not boot the notification app as an HTTP request does.
\OC_App::loadApp('notifications');
$notifications = Server::get(IManager::class);
$notifications->registerApp(SyntheticInterventionNotificationObserver::class);
$api = Server::get(ApiController::class);
$audits = Server::get(BookingInterventionAuditRepository::class);
$queue = Server::get(InterventionNotificationQueueRepository::class);
$bookings = Server::get(BookingService::class);
$scopes = Server::get(RiskScopeAuthorizationService::class);
$scopeId = ScopeAuthorizationQueryEvent::FLZROOM_SECRETARIAT_FOREIGN_BOOKING_INTERVENTION;
$session->setUser($users->get('synthetic-lifecycle-admin'));
$roomResponse = $api->createRoom('Synthetic room A');
$assert($roomResponse->getStatus() === 201, 'Native administration could not create a test room.');
$roomA = $roomResponse->getData()['id'];
$roomB = $api->createRoom('Synthetic room B')->getData()['id'];
$session->setUser($actors['owner']);
$title = 'SYNTHETIC-PRIVATE-TITLE';
$purpose = 'SYNTHETIC-PRIVATE-PURPOSE';
$created = $api->createBooking($roomA, '2035-01-02T10:00', '2035-01-02T11:00', $purpose, $title);
$assert($created->getStatus() === 201, 'The owner could not create the synthetic booking.');
$bookingId = $created->getData()['id'];
$original = $bookings->existing($bookingId)->toArray();
$reason = 'Synthetic local scheduling test only';
$unchanged = static function () use ($assert, $bookings, $bookingId, $original, $audits, $queue, $actors): void
{
    $assert($bookings->existing($bookingId)->toArray() == $original, 'A denied request changed the booking.');
    $assert($audits->recent(10) === [], 'A denied request wrote an audit entry.');
    $assert($queue->byRecipient($actors['owner']->getUID(), 10) === [], 'A denied request enqueued a notification.');
    $assert(SyntheticInterventionNotificationObserver::$delivered === [], 'A denied request sent a notification.');
};
$update = static fn(string $why) => $api->updateBooking(
    $bookingId, $roomB, '2035-01-02T11:00', '2035-01-02T12:00', $purpose, $title, $why,
);
$session->setUser($actors['secretariat']);
$assert($update($reason)->getStatus() === 403, 'A missing scope must deny the foreign intervention.');
$unchanged();

$session->setUser($actors['dpo']);
$input = [
    'scopeId' => $scopeId,
    'enabled' => true,
    'expectedRevision' => 0,
    'policyRevision' => 'SYNTHETIC-TEST-ONLY',
    'authorizationReference' => 'SYNTHETIC-NO-REAL-DPO-APPROVAL',
    'effectiveAt' => (new DateTimeImmutable('-1 minute'))->format(DATE_ATOM),
    'expiresAt' => (new DateTimeImmutable('+1 hour'))->format(DATE_ATOM),
    'dpoConfirmed' => false,
];
try
{
    $scopes->save($input);
    throw new RuntimeException('A missing DPO fixture confirmation was accepted.');
}
catch (InvalidArgumentException)
{
    $assert($scopes->status()['scopes'][$scopeId]['status'] === 'configuration_missing', 'Rejected confirmation persisted a scope.');
}
// Simulates the confirmed branch ONLY in this disposable fixture, never a real approval.
$input['dpoConfirmed'] = true;
$scopes->save($input);
$assert($scopes->status()['scopes'][$scopeId]['authorized'] === true, 'The synthetic scope fixture is inactive.');
$session->setUser($actors['outsider']);
try
{
    $scopes->save([...$input, 'expectedRevision' => 1]);
    throw new RuntimeException('A non-DPO fixture account configured the risk scope.');
}
catch (DomainException)
{
    $assert($scopes->status()['scopes'][$scopeId]['configuration']['revision'] === 1, 'Denied scope configuration wrote history.');
}
$assert($update($reason)->getStatus() === 403, 'An unrelated role gained foreign-booking permission.');
$assert($api->deleteBooking($bookingId, $reason)->getStatus() === 403, 'An unrelated role deleted a foreign booking.');
$unchanged();
$session->setUser($actors['secretariat']);
$assert($update('short')->getStatus() === 400, 'An invalid reason was accepted.');
$unchanged();
$assert($update($reason)->getStatus() === 200, 'An authorized foreign update failed.');
$changed = $bookings->existing($bookingId);
$assert($changed->roomId() === $roomB && $changed->userUid() === $actors['owner']->getUID(), 'Updated booking ownership or room is wrong.');
$entries = $audits->recent(10);
$assert(count($entries) === 1 && $entries[0]['action'] === 'update', 'The update audit was not appended exactly once.');
$assert($entries[0]['actorUid'] === $actors['secretariat']->getUID(), 'The update audit actor is wrong.');
$assert($queue->byRecipient($actors['owner']->getUID(), 10) === [], 'Successful delivery retained the queue entry.');
$assert(count(SyntheticInterventionNotificationObserver::$delivered) === 1, 'The actual delivery fan-out was not observed.');
$notification = SyntheticInterventionNotificationObserver::$delivered[0];
$assert($notifications->getCount($notification) === 1, 'The native notification app did not persist the message.');
$payload = $notification->getSubjectParameters();
$assert(array_keys($payload) === ['action', 'oldRoom', 'newRoom', 'oldStartsAt', 'oldEndsAt', 'newStartsAt', 'newEndsAt', 'reason'], 'Notification fields exceed the minimized contract.');
$assert(!str_contains(json_encode($payload, JSON_THROW_ON_ERROR), $title) && !str_contains(json_encode($payload, JSON_THROW_ON_ERROR), $purpose), 'The notification leaked booking title or purpose.');
$assert($notification->getUser() === $actors['owner']->getUID(), 'The notification has the wrong recipient.');
$rendered = Server::get(OCA\FlzRoom\Notification\Notifier::class)->prepare(clone $notification, 'de');
$assert(str_contains($rendered->getParsedSubject(), $reason), 'The rendered notification omits the intervention reason.');
$assert(!str_contains($rendered->getParsedSubject(), $title), 'The rendered notification leaks the booking title.');

$auditApi = Server::get(InterventionAuditController::class);
$assert($auditApi->index()->getStatus() === 403, 'Secretariat gained audit-read permission.');
$session->setUser($actors['dpo']);
$assert($auditApi->index()->getStatus() === 200, 'The DPO fixture cannot read the intervention audit.');
$session->setUser($actors['secretariat']);
$assert($api->deleteBooking($bookingId, $reason)->getStatus() === 200, 'An authorized foreign deletion failed.');
try
{
    $bookings->existing($bookingId);
    throw new RuntimeException('The deleted booking still exists.');
}
catch (OutOfBoundsException)
{
}
$assert(count($audits->recent(10)) === 2, 'The delete audit was not appended exactly once.');
$assert(count(SyntheticInterventionNotificationObserver::$delivered) === 2, 'The deletion did not deliver one notification.');
$assert($queue->byRecipient($actors['owner']->getUID(), 10) === [], 'Successful deletion delivery retained queue data.');

// Execute the real scheduler entry only against synthetic data in this disposable database.
$snapshot = $entries[0];
$expiredAuditId = $audits->append([
    ...$snapshot,
    'occurredAt' => new DateTimeImmutable('-2 years'),
]);
$retainedAuditId = $audits->append([
    ...$snapshot,
    'occurredAt' => new DateTimeImmutable('-6 months'),
]);
$queueSnapshot = [...$snapshot, 'recipientUid' => $actors['owner']->getUID(), 'createdAt' => new DateTimeImmutable('-10 minutes')];
$expiredFailureId = $queue->enqueue($queueSnapshot);
$queue->markPermanentlyFailed($expiredFailureId, 4, new DateTimeImmutable('-31 days'), 'synthetic_failure');
$retainedFailureId = $queue->enqueue($queueSnapshot);
$queue->markPermanentlyFailed($retainedFailureId, 4, new DateTimeImmutable('-29 days'), 'synthetic_failure');
$dueId = $queue->enqueue($queueSnapshot);
$jobs = Server::get(OCP\BackgroundJob\IJobList::class);
$registered = iterator_to_array($jobs->getJobsIterator(InterventionNotificationJob::class, 2, 0));
$assert(count($registered) === 1, 'The native job is missing or duplicated.');
$job = reset($registered);
$job->setLastRun(0);
$job->start($jobs);
$remainingIds = array_column($audits->recent(20), 'id');
$assert(!in_array($expiredAuditId, $remainingIds, true) && in_array($retainedAuditId, $remainingIds, true), 'The one-year audit retention boundary failed.');
$assert($queue->find($expiredFailureId) === null && $queue->find($retainedFailureId) !== null, 'The thirty-day failed-delivery retention boundary failed.');
$assert($queue->find($dueId) === null && count(SyntheticInterventionNotificationObserver::$delivered) === 3, 'The native job did not deliver and remove the due queue fixture.');
$job->setLastRun(0);
$job->start($jobs);
$assert(count(SyntheticInterventionNotificationObserver::$delivered) === 3, 'Repeated scheduler execution duplicated a notification.');

// The current DPO revision deliberately applies to records created before the change.
$retentionApi = Server::get(OCA\FlzRoom\Controller\RetentionAdminController::class);
$retentionPolicy = Server::get(OCA\FlzRoom\Service\RoomRetentionPolicyService::class);
$beforePolicy = $retentionPolicy->policy();
$session->setUser($actors['secretariat']);
$assert($retentionApi->savePolicy('P1Y', 'P6M', $beforePolicy['revision'], 'P3Y', 'P365D')->getStatus() === 403, 'A non-DPO changed intervention retention.');
$assert($retentionPolicy->policy() === $beforePolicy, 'Rejected retention change mutated the policy.');
$session->setUser($actors['dpo']);
$assert($retentionApi->savePolicy('P1Y', 'P6M', $beforePolicy['revision'], 'P3Y', 'P365D')->getStatus() === 200, 'The DPO cannot extend intervention retention.');
$extendedAuditId = $audits->append([...$snapshot, 'occurredAt' => new DateTimeImmutable('-2 years')]);
$extendedFailureId = $queue->enqueue($queueSnapshot);
$queue->markPermanentlyFailed($extendedFailureId, 4, new DateTimeImmutable('-100 days'), 'synthetic_failure');
$job->setLastRun(0);
$job->start($jobs);
$assert(in_array($extendedAuditId, array_column($audits->recent(20), 'id'), true), 'Extended audit retention was not applied to an older record.');
$assert($queue->find($extendedFailureId) !== null, 'Extended failure retention was not applied to an older record.');
$longPolicy = $retentionPolicy->policy();
$assert($retentionApi->savePolicy('P1Y', 'P6M', $longPolicy['revision'], 'P29D', 'P7D')->getStatus() === 400, 'The DPO saved an invalid audit boundary.');
$assert($retentionPolicy->policy() === $longPolicy, 'Invalid retention changed the persisted policy.');
$assert($retentionApi->savePolicy('P1Y', 'P6M', $beforePolicy['revision'], 'P90D', 'P7D')->getStatus() === 409, 'An outdated policy revision overwrote current retention.');
$assert($retentionPolicy->policy() === $longPolicy, 'An outdated revision changed persisted retention.');
$youngAuditId = $audits->append([...$snapshot, 'occurredAt' => new DateTimeImmutable('-20 days')]);
$youngFailureId = $queue->enqueue($queueSnapshot);
$queue->markPermanentlyFailed($youngFailureId, 4, new DateTimeImmutable('-1 day'), 'synthetic_failure');
$assert($retentionApi->savePolicy('P1Y', 'P6M', $longPolicy['revision'], 'P90D', 'P7D')->getStatus() === 200, 'The DPO cannot shorten intervention retention.');
$job->setLastRun(0);
$job->start($jobs);
$remainingIds = array_column($audits->recent(20), 'id');
$assert(!in_array($extendedAuditId, $remainingIds, true) && !in_array($retainedAuditId, $remainingIds, true)
    && in_array($youngAuditId, $remainingIds, true), 'Shorter retention failed to delete older audit records or deleted a younger one.');
$assert($queue->find($extendedFailureId) === null && $queue->find($retainedFailureId) === null
    && $queue->find($youngFailureId) !== null, 'Shorter retention failed to delete older failure records or deleted a younger one.');
$assert(count(SyntheticInterventionNotificationObserver::$delivered) === 3, 'Retention changes caused additional notifications.');
foreach (SyntheticInterventionNotificationObserver::$delivered as $message)
{
    $assert($notifications->getCount($message) === 1, 'A native notification is missing.');
    $notifications->markProcessed($message);
    $assert($notifications->getCount($message) === 0, 'The native notification cleanup failed.');
}
echo "Synthetic foreign intervention passed: denied scope/role/reason, update/delete/audit, native notifications, minimized payload.\n";
echo "Native intervention job passed: due delivery, idempotency, one-year audit and thirty-day failure retention.\n";
echo "DPO retention settings passed: extension and shortening affect existing records, invalid/unauthorized/stale writes have no side effects.\n";
echo "DPO confirmation was a disposable test fixture; no real approval was asserted.\n";
