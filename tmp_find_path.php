<?php

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

function findPath(array $array, string $target, string $prefix = ''): array {
    $paths = [];
    foreach ($array as $key => $value) {
        $path = $prefix === '' ? $key : "$prefix.$key";
        if ($key === $target) {
            $paths[] = $path;
        }
        if (is_array($value)) {
            $paths = array_merge($paths, findPath($value, $target, $path));
        }
    }
    return $paths;
}

$site = __('site');
$paths = findPath($site, 'maintenance_types');
echo implode(PHP_EOL, $paths) . PHP_EOL;
