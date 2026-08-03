<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$site = __('site');

function findKey(array $arr, string $path = ''): void
{
    foreach ($arr as $k => $v) {
        $p = $path === '' ? $k : "$path.$k";
        if ($k === 'maintenance_types' && is_array($v) && isset($v['index']['title'])) {
            echo "Found maintenance_types at: $p\n";
            echo "  title: {$v['index']['title']}\n";
        }
        if ($k === 'branches' && is_array($v) && isset($v['title']) && str_contains($p, 'dashboard')) {
            echo "Found branches at: $p\n";
            echo "  title: {$v['title']}\n";
        }
        if (is_array($v)) {
            findKey($v, $p);
        }
    }
}

findKey($site);

echo "\ndashboard.admin keys:\n";
echo implode(', ', array_keys($site['dashboard']['admin'] ?? [])) . "\n";
