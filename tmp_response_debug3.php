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
$rows = $data['props']['rows'];
foreach (['api.task_types.pickup', 'dashboard.admin.discount_requests.index.discount_requests', 'dashboard.admin.discount_requests.statuses.pending'] as $key) {
    echo $key . ' => ' . (bool) in_array($key, array_column($rows, 'key'), true) . "\n";
}
$matches = array_filter($rows, fn($row) => in_array($row['key'], [
    'api.task_types.pickup',
    'dashboard.admin.discount_requests.index.discount_requests',
    'dashboard.admin.discount_requests.statuses.pending',
], true));
print_r($matches);
