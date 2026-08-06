<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = Illuminate\Http\Request::create('/superadmin/settings/landing-translations', 'GET');
$request->headers->set('X-Inertia', 'true');
$controller = app()->make(App\Http\Controllers\SuperAdmin\LandingSettingsController::class);
$response = $controller->translations($request)->toResponse($request);
$content = $response->getContent();
$data = json_decode($content, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo 'JSON ERROR: ' . json_last_error_msg() . "\n";
    exit(1);
}
$rows = $data['props']['rows'] ?? [];
$keys = array_column($rows, 'key');
foreach (['api.task_types.pickup', 'dashboard.admin.discount_requests.index.discount_requests', 'dashboard.admin.discount_requests.statuses.pending'] as $key) {
    echo $key . ' => ' . (in_array($key, $keys, true) ? 'YES' : 'NO') . "\n";
}
$matches = array_filter($keys, fn($k) => str_contains($k, 'api.task_types.pickup') || str_contains($k, 'dashboard.admin.discount_requests.index.discount_requests') || str_contains($k, 'dashboard.admin.discount_requests.statuses.pending'));
print_r($matches);
