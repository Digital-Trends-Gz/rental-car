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
$search = array_filter(array_column($rows, 'key'), fn($key) => str_contains($key, 'discount') || str_contains($key, 'api.task_types.pickup'));
print_r(array_slice($search, 0, 50));
$foundCount = count($search);
echo "found count: $foundCount\n";
