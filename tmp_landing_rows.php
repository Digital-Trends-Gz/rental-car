<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = Illuminate\Http\Request::create('/superadmin/settings/landing-translations', 'GET');
$request->setLaravelSession(app('session.store'));
$controller = app()->make(App\Http\Controllers\SuperAdmin\LandingSettingsController::class);
$response = $controller->translations($request);
$httpResponse = $response->toResponse($request);
$content = $httpResponse->getContent();
$data = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) { echo "JSON ERROR: " . json_last_error_msg() . "\n"; var_export($content); exit(1); }
$rows = $data['props']['rows'] ?? [];
$keys = array_column($rows, 'key');
$target = [
    'api.task_types.pickup',
    'dashboard.admin.discount_requests.index.discount_requests',
    'dashboard.admin.discount_requests.statuses.pending',
];
foreach ($target as $key) {
    echo $key . ' => ' . (in_array($key, $keys, true) ? 'YES' : 'NO') . "\n";
}
echo 'rows count: ' . count($rows) . "\n";
$first = array_slice($keys, 0, 60);
echo "first 60 keys:\n";
foreach ($first as $i => $key) { echo ($i+1) . ": $key\n"; }
