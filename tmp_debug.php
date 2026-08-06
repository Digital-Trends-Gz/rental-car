<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$controller = app()->make(App\Http\Controllers\SuperAdmin\LandingSettingsController::class);
$ref = new ReflectionClass($controller);
$method = $ref->getMethod('defaultTranslationRows');
$method->setAccessible(true);
$rows = $method->invoke($controller, 'en');
$target = [
    'api.task_types.pickup',
    'dashboard.admin.discount_requests.index.discount_requests',
    'dashboard.admin.discount_requests.statuses.pending',
];
foreach ($target as $key) {
    echo $key . ' => ' . (array_key_exists($key, $rows) ? $rows[$key] : '[MISSING]') . "\n";
}
echo '\nTotal keys: ' . count($rows) . "\n";
$sample = array_slice(array_keys($rows), 0, 30);
echo "Sample keys:\n";
foreach ($sample as $k) {
    echo $k . "\n";
}
