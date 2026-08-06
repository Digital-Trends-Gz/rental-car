<?php
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();
$request = Illuminate\Http\Request::create('/superadmin/settings/landing-translations', 'GET');
$request->headers->set('X-Inertia', 'true');
$controller = app()->make(App\Http\Controllers\SuperAdmin\LandingSettingsController::class);
$response = $controller->translations($request)->toResponse($request);
$data = json_decode($response->getContent(), true);
$rows = $data['props']['rows'];
echo 'count: ' . count($rows) . "\n";
for ($i = 0; $i < min(10, count($rows)); $i++) {
    echo "row $i:\n";
    var_export($rows[$i]);
    echo "\n\n";
}
