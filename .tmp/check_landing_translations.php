<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$landing = App\Models\SiteSetting::where('key', App\Core\LandingPageSettings::KEY)->value('value');

foreach (['ur', 'ar', 'en'] as $locale) {
    echo $locale . ': ' . json_encode(data_get($landing, "translations.$locale.dashboard.admin.clients.show.flag.types.blocked"), JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo $locale . ' plural: ' . json_encode(data_get($landing, "translations.$locale.dashboard.admin.clients.show.flags.types.blocked"), JSON_UNESCAPED_UNICODE) . PHP_EOL;
}

$tenant = App\Models\TenantSiteSetting::query()->first();
echo 'tenant id ' . ($tenant?->tenant_id ?? 'none') . PHP_EOL;

if ($tenant) {
    foreach (['ur', 'ar', 'en'] as $locale) {
        echo 'tenant ' . $locale . ': ' . json_encode(data_get($tenant->translations, "$locale.dashboard.admin.clients.show.flag.types.blocked"), JSON_UNESCAPED_UNICODE) . PHP_EOL;
        echo 'tenant ' . $locale . ' plural: ' . json_encode(data_get($tenant->translations, "$locale.dashboard.admin.clients.show.flags.types.blocked"), JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
