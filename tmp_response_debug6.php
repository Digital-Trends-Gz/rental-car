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
for ($i = 40; $i < min(60, count($rows)); $i++) {
    echo "$i: " . $rows[$i]['key'] . "\n";
}
