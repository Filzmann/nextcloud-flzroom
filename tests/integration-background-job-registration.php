<?php

declare(strict_types=1);

// Read-only runtime probe; deliberately separate from the dependency-light suite.
if (PHP_SAPI !== 'cli')
{
    http_response_code(404);
    exit(1);
}

$nextcloudRoot = realpath($argv[1] ?? '');
if ($nextcloudRoot === false || !is_file($nextcloudRoot . '/lib/base.php'))
{
    throw new RuntimeException('Pass the local Nextcloud root as the first argument.');
}

define('OC_CONSOLE', true);
require_once $nextcloudRoot . '/lib/base.php';

// Nextcloud's web exception handler can finish CLI probes with exit code zero.
set_exception_handler(static function (\Throwable $error): never
{
    fwrite(STDERR, get_class($error) . ': ' . $error->getMessage() . "\n");
    exit(1);
});

$appManager = \OCP\Server::get(\OCP\App\IAppManager::class);
$jobList = \OCP\Server::get(\OCP\BackgroundJob\IJobList::class);
$jobClass = \OCA\FlzRoom\BackgroundJob\InterventionNotificationJob::class;
if (!$jobList->has($jobClass, null))
{
    throw new RuntimeException('The installed app has no intervention background job.');
}
$appInfo = $appManager->getAppInfo('flzroom');
if (!in_array($jobClass, $appInfo['background-jobs'] ?? [], true))
{
    throw new RuntimeException('Nextcloud does not discover the intervention job in app metadata.');
}
$query = \OCP\Server::get(\OCP\IDBConnection::class)->getQueryBuilder();
$count = (int)$query->selectAlias($query->func()->count('*'), 'job_count')
    ->from('jobs')
    ->where($query->expr()->eq('class', $query->createNamedParameter($jobClass)))
    ->executeQuery()->fetchOne();
if ($count !== 1)
{
    throw new RuntimeException('The intervention job must be registered exactly once.');
}

// Resolve the actual container graph without executing delivery or retention.
$job = \OCP\Server::get($jobClass);
if (!$job instanceof \OCP\BackgroundJob\TimedJob || $job->getInterval() !== 60)
{
    throw new RuntimeException('The registered intervention job cannot be scheduled correctly.');
}

echo "Native intervention background job registration passed (no job execution).\n";
