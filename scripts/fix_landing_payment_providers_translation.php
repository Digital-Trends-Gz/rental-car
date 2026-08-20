<?php

use App\Core\LandingPageSettings;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$apply = in_array('--apply', $argv, true);
$locales = ['ar', 'ur'];

$setting = SiteSetting::query()
    ->where('key', LandingPageSettings::KEY)
    ->firstOrFail();

$value = is_array($setting->value) ? $setting->value : [];
$sections = data_get($value, 'plans_comparison_page.comparison_sections', []);
$targetSectionIndex = null;
$targetRowIndex = null;

foreach ($sections as $sectionIndex => $section) {
    foreach (($section['rows'] ?? []) as $rowIndex => $row) {
        if (($row['label'] ?? '') === 'Payment providers') {
            $targetSectionIndex = $sectionIndex;
            $targetRowIndex = $rowIndex;
            break 2;
        }
    }
}

if ($targetSectionIndex === null || $targetRowIndex === null) {
    fwrite(STDERR, "Payment providers row was not found.\n");
    exit(1);
}

$originalValuesPath = "plans_comparison_page.comparison_sections.$targetSectionIndex.rows.$targetRowIndex.values";
$originalValues = data_get($value, $originalValuesPath);

$report = [
    'mode' => $apply ? 'apply' : 'dry-run',
    'site_setting_id' => $setting->id,
    'row_path' => "plans_comparison_page.comparison_sections.$targetSectionIndex.rows.$targetRowIndex",
    'original_values_path' => $originalValuesPath,
    'original_values' => $originalValues,
    'translation_overrides' => [],
    'will_remove' => [],
];

foreach ($locales as $locale) {
    $overrideValuesPath = "translations.$locale.plans_comparison_page.comparison_sections.$targetSectionIndex.rows.$targetRowIndex.values";
    $overrideValues = data_get($value, $overrideValuesPath);

    $report['translation_overrides'][$locale] = [
        'path' => $overrideValuesPath,
        'values' => $overrideValues,
    ];

    if ($overrideValues !== null) {
        $report['will_remove'][] = $overrideValuesPath;
    }
}

echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL;

if (!$apply) {
    echo PHP_EOL."Dry run only. Re-run with --apply to create a backup and remove the listed override values.".PHP_EOL;
    exit(0);
}

if ($originalValues !== ['Yes', 'Yes', 'Yes', 'Custom']) {
    fwrite(STDERR, "Original Payment providers values are not expected. Refusing to modify.\n");
    exit(1);
}

if ($report['will_remove'] === []) {
    echo PHP_EOL."No translation override values found. Nothing to change.".PHP_EOL;
    exit(0);
}

$backupPath = 'backups/landing_page_before_payment_providers_fix_'.now()->format('Ymd_His').'.json';
Storage::disk('local')->put($backupPath, json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

foreach ($report['will_remove'] as $path) {
    data_forget($value, $path);
}

$setting->value = $value;
$setting->save();

Artisan::call('optimize:clear');

echo PHP_EOL."Applied successfully.".PHP_EOL;
echo "Backup: storage/app/$backupPath".PHP_EOL;
