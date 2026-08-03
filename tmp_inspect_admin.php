<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$admin = data_get(__('site'), 'dashboard.admin', []);

echo "Type of admin.employees: " . gettype($admin['employees'] ?? null) . PHP_EOL;
if (isset($admin['employees'])) {
    echo "admin.employees value: ";
    var_export($admin['employees']);
    echo PHP_EOL;
}

echo "\nTop-level dashboard keys (not in admin):\n";
$dashboard = data_get(__('site'), 'dashboard', []);
foreach (array_keys($dashboard) as $key) {
    if ($key !== 'admin') {
        echo "  dashboard.$key\n";
    }
}
