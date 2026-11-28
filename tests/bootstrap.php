<?php

declare(strict_types=1);

$appRoot = dirname(__DIR__);
$workspaceRoot = dirname($appRoot);

require_once $appRoot . '/vendor/flz-shared/php/vendor/autoload.php';

spl_autoload_register(static function (string $class) use ($appRoot, $workspaceRoot): void {
    $mappings = [
        'OCA\\FlzRoom\\' => $appRoot . '/lib/',
        // Contract tests exercise the real optional provider through this test-only boundary.
        'OCA\\FlzOrganization\\' => $workspaceRoot . '/flz_organization/lib/',
        'OCA\\FlzDataProtection\\' => $appRoot . '/tests/stubs/FlzDataProtection/',
        'OCA\\LocalBase\\Tests\\' => $workspaceRoot . '/localbase/tests/',
    ];

    foreach ($mappings as $prefix => $root) {
        if (!str_starts_with($class, $prefix)) {
            continue;
        }
        $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
        $path = $root . $relative . '.php';
        if (is_file($path)) {
            require_once $path;
        }
        return;
    }
});
