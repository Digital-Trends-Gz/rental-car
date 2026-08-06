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
var_export(array_keys($data));
echo "\nprops exists? ".(isset($data['props']) ? 'YES' : 'NO')."\n";
if (isset($data['props'])) {
    var_export(array_keys($data['props']));
    echo "\n";
    echo 'rows exists? ' . (isset($data['props']['rows']) ? 'YES' : 'NO') . "\n";
}
