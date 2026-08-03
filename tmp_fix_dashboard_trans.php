<?php

/**
 * Move admin page translations from dashboard.* to dashboard.admin.*
 */

$files = [
    __DIR__ . '/lang/en/site.php',
    __DIR__ . '/lang/ar/site.php',
];

$keysToMove = [
    'car_violations',
    'damage_reports',
    'branches',
    'maintenance_types',
    'maintenance_records',
    'violation_types',
    'employees',
    'roles',
];

foreach ($files as $file) {
    /** @var array<string, mixed> $site */
    $site = include $file;

    $dashboard = $site['dashboard'] ?? [];
    $admin = $dashboard['admin'] ?? [];

    foreach ($keysToMove as $key) {
        if (!isset($dashboard[$key]) || !is_array($dashboard[$key])) {
            echo basename(dirname($file)) . ": missing dashboard.$key\n";
            continue;
        }

        if ($key === 'employees' && isset($admin['employees']) && is_array($admin['employees'])) {
            $admin['employees'] = array_replace_recursive($admin['employees'], $dashboard[$key]);
        } else {
            $admin[$key] = $dashboard[$key];
        }

        unset($dashboard[$key]);
    }

    $dashboard['admin'] = $admin;
    $site['dashboard'] = $dashboard;

    $export = var_export($site, true);
    $content = "<?php\n\nreturn {$export};\n";

    file_put_contents($file, $content);
    echo "Updated {$file}\n";
}

// Verify
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$checks = [
    'dashboard.admin.maintenance_types.index.title',
    'dashboard.admin.branches.title',
    'dashboard.admin.employees.title',
];

foreach ($checks as $key) {
    $value = data_get(__('site'), $key, 'MISSING');
    echo "$key => $value\n";
}
