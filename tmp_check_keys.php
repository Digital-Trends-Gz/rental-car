<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$keys = [
    'dashboard.admin.maintenance_types.index.title',
    'dashboard.admin.branches.title',
    'dashboard.admin.damage_reports.index.title',
    'dashboard.admin.maintenance_records.index.title',
    'dashboard.admin.employees.title',
    'dashboard.maintenance_types.index.title',
    'dashboard.branches.title',
    'dashboard.damage_reports.index.title',
];

$site = __('site');
foreach ($keys as $key) {
    echo $key . ': ' . (data_get($site, $key, 'MISSING')) . PHP_EOL;
}

echo PHP_EOL . 'Has maintenance_types under dashboard.admin: ' . (isset($site['dashboard']['admin']['maintenance_types']) ? 'yes' : 'no') . PHP_EOL;
echo 'Keys under dashboard.admin: ' . implode(', ', array_slice(array_keys($site['dashboard']['admin'] ?? []), 0, 20)) . PHP_EOL;
