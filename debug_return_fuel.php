<?php

require __DIR__.'/vendor/autoload.php';

$app = require __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Core\TenantContext;
use App\Models\Contract;
use App\Services\Contracts\ContractDamagePhotoExtractor;

$contract = Contract::query()->withoutTenantScope()->with('tenant')->find(7);
if (!$contract) {
    fwrite(STDERR, "Contract 7 not found.\n");
    exit(1);
}

if ($contract->tenant) {
    TenantContext::set($contract->tenant);
}

$path = 'C:\\laragon\\www\\real-rent-car-main\\storage\\app\\public\\temp-files\\handover-0a83267e-a349-46fc-9612-e3ec2782433b\\9d89518a-7ba1-4b82-ad21-fd466430b0a4.png';

echo "file_exists=".((file_exists($path)) ? 'yes' : 'no').PHP_EOL;
echo "mime=".(file_exists($path) ? (mime_content_type($path) ?: 'null') : 'null').PHP_EOL;
echo "size=".(file_exists($path) ? (string) filesize($path) : 'null').PHP_EOL;

$extractor = $app->make(ContractDamagePhotoExtractor::class);
$result = $extractor->extractFromPhotoGroups([
    [
        'view_side' => 'front',
        'photo_type' => 'fuel',
        'file_paths' => [$path],
    ],
], 'after_return');

echo json_encode([
    'vehicle_readings' => $result['vehicle_readings'] ?? null,
    'summary' => $result['summary'] ?? null,
    'confidence' => $result['confidence'] ?? null,
    'raw_text' => $result['raw_text'] ?? null,
    'raw_output' => $result['raw_output'] ?? null,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE).PHP_EOL;
