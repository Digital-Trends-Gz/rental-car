<?php

namespace App\Services\Contracts;

use App\Core\AiExtractionQuota;
use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use App\Support\CarDamageCatalog;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;

class ContractDamagePhotoExtractor
{
    /**
     * @param  array<int, array{view_side?: string, photo_type?: string, file_paths?: array<int, string>}>  $photoGroups
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   summary: string|null,
     *   vehicle_readings: array<string, mixed>,
     *   raw_output: array<string, mixed>,
     *   raw_text: string,
     *   confidence: float|null,
     *   provider: string,
     *   engine: string|null
     * }
     */
    public function extractFromPhotoGroups(array $photoGroups, string $reportType = 'before_delivery'): array
    {
        $photoGroups = array_values(array_filter($photoGroups, static fn ($group): bool => is_array($group)));

        $preparedPhotos = [];
        foreach ($photoGroups as $group) {
            $viewSide = $this->normalizeViewSide($group['view_side'] ?? null);
            $photoType = $this->normalizePhotoType($group['photo_type'] ?? null);
            $filePaths = [];

            foreach ((array) ($group['file_paths'] ?? []) as $path) {
                $resolved = $this->resolveAbsolutePath((string) $path);
                if ($resolved !== null) {
                    $filePaths[] = $resolved;
                }
            }

            $filePaths = array_values(array_unique(array_filter($filePaths, static fn (string $path): bool => $path !== '')));
            if ($filePaths === []) {
                continue;
            }

            $preparedPhotos[] = [
                'view_side' => $viewSide,
                'photo_type' => $photoType,
                'file_paths' => $filePaths,
            ];
        }

        if ($preparedPhotos === []) {
            throw new RuntimeException('No readable damage photos were provided for extraction.');
        }

        $settings = AiProviderSettings::load();
        $provider = (string) ($settings['provider'] ?? 'openai');

        if ($provider !== 'openai') {
            throw new RuntimeException('Current AI provider is not supported for damage photo extraction. Switch provider to OpenAI.');
        }

        if (!AiProviderSettings::isConfiguredForCurrentProvider()) {
            throw new RuntimeException('OpenAI provider is not fully configured in Super Admin settings.');
        }

        AiExtractionQuota::ensureAvailable(TenantContext::get());

        $items = [];
        $vehicleReadings = [
            'vehicle_odometer' => null,
            'vehicle_fuel_level' => null,
            'odometer_confidence' => null,
            'fuel_level_confidence' => null,
        ];
        $rawOutputs = [];
        $rawTexts = [];
        $summaries = [];
        $confidenceValues = [];

        foreach ($preparedPhotos as $index => $photoGroup) {
            $groupResult = $this->extractSinglePhotoGroup($photoGroup, $reportType, $index + 1);
            if ($groupResult === null) {
                continue;
            }

            $rawOutputs[] = $groupResult['raw_output'];
            $rawTexts[] = $groupResult['raw_text'];

            if (is_array($groupResult['vehicle_readings'] ?? null)) {
                $vehicleReadings = array_merge($vehicleReadings, array_filter(
                    $groupResult['vehicle_readings'],
                    static fn ($value): bool => $value !== null && $value !== ''
                ));
            }

            if (($groupResult['summary'] ?? null) !== null) {
                $summaries[] = (string) $groupResult['summary'];
            }
            if (isset($groupResult['confidence']) && is_numeric($groupResult['confidence'])) {
                $confidenceValues[] = (float) $groupResult['confidence'];
            }

            foreach (is_array($groupResult['items'] ?? null) ? $groupResult['items'] : [] as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $items[] = $this->normalizeItem($item, $reportType);
            }
        }

        return [
            'items' => $items,
            'summary' => $this->nullableString(implode("\n\n", array_filter($summaries, static fn (string $text): bool => trim($text) !== ''))),
            'vehicle_readings' => $vehicleReadings,
            'raw_output' => $rawOutputs,
            'raw_text' => trim(implode("\n\n----------------\n\n", array_filter($rawTexts, static fn (string $text): bool => trim($text) !== ''))),
            'confidence' => $confidenceValues === [] ? null : array_sum($confidenceValues) / count($confidenceValues),
            'provider' => 'openai',
            'engine' => (string) (($settings['openai'] ?? [])['model'] ?? 'gpt-4.1-mini'),
        ];
    }

    /**
     * @param  array{view_side: string, photo_type: string, file_paths: array<int, string>}  $photoGroup
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   summary: string|null,
     *   vehicle_readings: array<string, mixed>,
     *   raw_output: array<string, mixed>,
     *   raw_text: string,
     *   confidence: float|null
     * }|null
     */
    private function extractSinglePhotoGroup(array $photoGroup, string $reportType, int $groupNumber): ?array
    {
        $photoType = $this->normalizePhotoType($photoGroup['photo_type'] ?? null);

        if ($photoType === 'odometer') {
            return $this->extractOdometerPhotoGroup($photoGroup, $groupNumber);
        }

        if ($photoType === 'fuel') {
            return $this->extractFuelPhotoGroup($photoGroup, $groupNumber);
        }

        $imageDataUris = [];
        $rawParts = [];

        $label = strtoupper((string) ($photoGroup['view_side'] ?? 'front'));
        $rawParts[] = "Photo group {$groupNumber} ({$label})";

        foreach ($photoGroup['file_paths'] as $filePath) {
            $rawParts[] = ' - '.$this->fileLabelFromPath($filePath);
            $imageDataUri = $this->extractImageDataUriFromPath($filePath);
            if ($imageDataUri !== null) {
                $imageDataUris[] = [
                    'view_side' => (string) $photoGroup['view_side'],
                    'image_data_uri' => $imageDataUri,
                ];
            }
        }

        if ($imageDataUris === []) {
            return null;
        }

        $rawText = trim(implode("\n", $rawParts));
        $rawText = mb_substr($rawText, 0, 4000);

        $rawOutput = $this->extractStructuredDamageDataWithOpenAi($rawText, $imageDataUris, $reportType);
        $items = is_array($rawOutput['items'] ?? null) ? $rawOutput['items'] : [];

        return [
            'items' => $items,
            'summary' => $this->nullableString($rawOutput['summary'] ?? null),
            'vehicle_readings' => [
                'vehicle_odometer' => null,
                'vehicle_fuel_level' => null,
                'odometer_confidence' => null,
                'fuel_level_confidence' => null,
            ],
            'raw_output' => is_array($rawOutput) ? $rawOutput : [],
            'raw_text' => $rawText,
            'confidence' => isset($rawOutput['confidence']) && is_numeric($rawOutput['confidence'])
                ? (float) $rawOutput['confidence']
                : null,
        ];
    }

    /**
     * @param  array{view_side: string, photo_type: string, file_paths: array<int, string>}  $photoGroup
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   summary: string|null,
     *   vehicle_readings: array<string, mixed>,
     *   raw_output: array<string, mixed>,
     *   raw_text: string,
     *   confidence: float|null
     * }|null
     */
    private function extractOdometerPhotoGroup(array $photoGroup, int $groupNumber): ?array
    {
        $prepared = $this->prepareSinglePhotoGroupTextAndImages($photoGroup, $groupNumber, 'odometer');
        if ($prepared === null) {
            return null;
        }

        $rawOutput = $this->extractStructuredOdometerDataWithOpenAi($prepared['raw_text'], $prepared['image_data_uris']);
        $odometer = isset($rawOutput['vehicle_odometer']) && is_numeric($rawOutput['vehicle_odometer'])
            ? (int) $rawOutput['vehicle_odometer']
            : null;

        return [
            'items' => [],
            'summary' => $this->nullableString($rawOutput['notes'] ?? null),
            'vehicle_readings' => [
                'vehicle_odometer' => $odometer,
                'odometer_confidence' => isset($rawOutput['confidence']) && is_numeric($rawOutput['confidence'])
                    ? (float) $rawOutput['confidence']
                    : null,
            ],
            'raw_output' => is_array($rawOutput) ? $rawOutput : [],
            'raw_text' => $prepared['raw_text'],
            'confidence' => isset($rawOutput['confidence']) && is_numeric($rawOutput['confidence'])
                ? (float) $rawOutput['confidence']
                : null,
        ];
    }

    /**
     * @param  array{view_side: string, photo_type: string, file_paths: array<int, string>}  $photoGroup
     * @return array{
     *   items: array<int, array<string, mixed>>,
     *   summary: string|null,
     *   vehicle_readings: array<string, mixed>,
     *   raw_output: array<string, mixed>,
     *   raw_text: string,
     *   confidence: float|null
     * }|null
     */
    private function extractFuelPhotoGroup(array $photoGroup, int $groupNumber): ?array
    {
        $prepared = $this->prepareSinglePhotoGroupTextAndImages($photoGroup, $groupNumber, 'fuel');
        if ($prepared === null) {
            return null;
        }

        $rawOutput = $this->extractStructuredFuelDataWithOpenAi($prepared['raw_text'], $prepared['image_data_uris']);
        $fuelLevel = $this->normalizeFuelLevel($rawOutput['vehicle_fuel_level'] ?? null);

        return [
            'items' => [],
            'summary' => $this->nullableString($rawOutput['notes'] ?? null),
            'vehicle_readings' => [
                'vehicle_fuel_level' => $fuelLevel,
                'fuel_level_confidence' => isset($rawOutput['confidence']) && is_numeric($rawOutput['confidence'])
                    ? (float) $rawOutput['confidence']
                    : null,
            ],
            'raw_output' => is_array($rawOutput) ? $rawOutput : [],
            'raw_text' => $prepared['raw_text'],
            'confidence' => isset($rawOutput['confidence']) && is_numeric($rawOutput['confidence'])
                ? (float) $rawOutput['confidence']
                : null,
        ];
    }

    /**
     * @param  array{view_side: string, photo_type: string, file_paths: array<int, string>}  $photoGroup
     * @return array{raw_text: string, image_data_uris: array<int, array{view_side: string, image_data_uri: string}>}|null
     */
    private function prepareSinglePhotoGroupTextAndImages(array $photoGroup, int $groupNumber, string $labelSuffix): ?array
    {
        $imageDataUris = [];
        $rawParts = [];

        $label = strtoupper((string) ($photoGroup['view_side'] ?? $labelSuffix));
        $rawParts[] = "Photo group {$groupNumber} ({$label})";

        foreach ($photoGroup['file_paths'] as $filePath) {
            $rawParts[] = ' - '.$this->fileLabelFromPath($filePath);
            $imageDataUri = $this->extractImageDataUriFromPath($filePath);
            if ($imageDataUri !== null) {
                $imageDataUris[] = [
                    'view_side' => (string) $photoGroup['view_side'],
                    'image_data_uri' => $imageDataUri,
                ];
            }
        }

        if ($imageDataUris === []) {
            return null;
        }

        return [
            'raw_text' => mb_substr(trim(implode("\n", $rawParts)), 0, 4000),
            'image_data_uris' => $imageDataUris,
        ];
    }

    private function extractStructuredDamageDataWithOpenAi(string $rawText, array $imageDataUris, string $reportType): array
    {
        $settings = AiProviderSettings::load();
        $openAi = $settings['openai'] ?? [];
        $model = (string) ($openAi['model'] ?? 'gpt-4.1-mini');
        $temperature = (float) ($openAi['temperature'] ?? 0.1);
        $maxOutputTokens = max(300, min((int) ($openAi['max_output_tokens'] ?? 1200), 1600));
        $systemPrompt = trim((string) ($openAi['system_prompt'] ?? ''));

        if ($systemPrompt === '') {
            $systemPrompt = 'Inspect car photos and return JSON only with the detected exterior damage items.';
        }

        $zoneCodes = implode(', ', CarDamageCatalog::zoneCodes());
        $viewSides = implode(', ', array_map(static fn (array $item): string => $item['value'], CarDamageCatalog::viewSides()));
        $damageTypes = implode(', ', array_map(static fn (array $item): string => $item['value'], CarDamageCatalog::damageTypes()));
        $severityLevels = implode(', ', array_map(static fn (array $item): string => $item['value'], CarDamageCatalog::severityLevels()));

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'summary' => ['type' => ['string', 'null']],
                'confidence' => ['type' => ['number', 'null']],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'additionalProperties' => false,
                        'properties' => [
                            'zone_code' => ['type' => ['string', 'null']],
                            'view_side' => ['type' => ['string', 'null']],
                            'damage_type' => ['type' => ['string', 'null']],
                            'severity' => ['type' => ['string', 'null']],
                            'damage_timing' => ['type' => ['string', 'null']],
                            'quantity' => ['type' => ['integer', 'null']],
                            'marker_x' => ['type' => ['number', 'null']],
                            'marker_y' => ['type' => ['number', 'null']],
                            'estimated_cost' => ['type' => ['number', 'null']],
                            'notes' => ['type' => ['string', 'null']],
                            'confidence' => ['type' => ['number', 'null']],
                        ],
                        'required' => [
                            'zone_code',
                            'view_side',
                            'damage_type',
                            'severity',
                            'damage_timing',
                            'quantity',
                            'marker_x',
                            'marker_y',
                            'estimated_cost',
                            'notes',
                            'confidence',
                        ],
                    ],
                ],
            ],
            'required' => ['summary', 'confidence', 'items'],
        ];

        $damageTiming = $reportType === 'after_return' ? 'after_return' : 'before_pickup';

        $userContent = [[
            'type' => 'input_text',
            'text' => "Analyze the following car photos and detect visible damage. Return strict JSON only. Report timing: {$damageTiming}.",
        ], [
            'type' => 'input_text',
            'text' => "Allowed view sides: {$viewSides}. Allowed zone codes: {$zoneCodes}. Allowed damage types: {$damageTypes}. Allowed severity levels: {$severityLevels}.",
        ]];

        if ($rawText !== '') {
            $userContent[] = [
                'type' => 'input_text',
                'text' => "Photo group notes:\n\n".$rawText,
            ];
        }

        foreach ($imageDataUris as $imageData) {
            $userContent[] = [
                'type' => 'input_text',
                'text' => 'Photo view: '.strtoupper((string) ($imageData['view_side'] ?? 'front')),
            ];
            $userContent[] = [
                'type' => 'input_image',
                'image_url' => $imageData['image_data_uri'],
                'detail' => 'high',
            ];
        }

        $response = OpenAI::responses()->create([
            'model' => $model,
            'temperature' => $temperature,
            'max_output_tokens' => $maxOutputTokens,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => $systemPrompt],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'contract_damage_photo_extraction',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        $outputText = trim((string) ($response->outputText ?? ''));
        if ($outputText === '') {
            throw new RuntimeException('OpenAI returned an empty damage extraction response.');
        }

        $decoded = json_decode($outputText, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        if (preg_match('/\{.*\}/s', $outputText, $match) === 1) {
            $fallback = json_decode($match[0], true);
            if (is_array($fallback)) {
                return $fallback;
            }
        }

        throw new RuntimeException('OpenAI damage extraction response is not valid JSON.');
    }

    private function extractStructuredOdometerDataWithOpenAi(string $rawText, array $imageDataUris): array
    {
        $settings = AiProviderSettings::load();
        $openAi = $settings['openai'] ?? [];
        $model = (string) ($openAi['model'] ?? 'gpt-4.1-mini');
        $temperature = 0.0;
        $maxOutputTokens = 500;
        $systemPrompt = trim((string) ($openAi['system_prompt'] ?? ''));
        if ($systemPrompt === '') {
            $systemPrompt = 'Read the vehicle odometer value from the photo and return strict JSON only.';
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'vehicle_odometer' => ['type' => ['integer', 'null']],
                'confidence' => ['type' => ['number', 'null']],
                'notes' => ['type' => ['string', 'null']],
            ],
            'required' => ['vehicle_odometer', 'confidence', 'notes'],
        ];

        $userContent = [
            ['type' => 'input_text', 'text' => 'Extract the vehicle odometer reading from this photo. Return JSON only.'],
        ];

        if ($rawText !== '') {
            $userContent[] = ['type' => 'input_text', 'text' => 'Photo notes: '.$rawText];
        }

        foreach ($imageDataUris as $imageData) {
            $userContent[] = ['type' => 'input_image', 'image_url' => $imageData['image_data_uri'], 'detail' => 'low'];
        }

        $response = OpenAI::responses()->create([
            'model' => $model,
            'temperature' => $temperature,
            'max_output_tokens' => $maxOutputTokens,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => $systemPrompt],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'vehicle_odometer_extraction',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        $outputText = trim((string) ($response->outputText ?? ''));
        $decoded = json_decode($outputText, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        throw new RuntimeException('OpenAI odometer extraction response is not valid JSON.');
    }

    private function extractStructuredFuelDataWithOpenAi(string $rawText, array $imageDataUris): array
    {
        $settings = AiProviderSettings::load();
        $openAi = $settings['openai'] ?? [];
        $model = (string) ($openAi['model'] ?? 'gpt-4.1-mini');
        $temperature = 0.0;
        $maxOutputTokens = 500;
        $systemPrompt = trim((string) ($openAi['system_prompt'] ?? ''));
        if ($systemPrompt === '') {
            $systemPrompt = 'Read the vehicle fuel level from the photo and return strict JSON only.';
        }

        $allowedLevels = ['empty', '1/4', '1/2', '3/4', 'full'];

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'vehicle_fuel_level' => ['type' => ['string', 'null']],
                'confidence' => ['type' => ['number', 'null']],
                'notes' => ['type' => ['string', 'null']],
            ],
            'required' => ['vehicle_fuel_level', 'confidence', 'notes'],
        ];

        $userContent = [
            ['type' => 'input_text', 'text' => 'Extract the vehicle fuel level from this photo. Return only one of these exact values: '.implode(', ', $allowedLevels).'. Read only the small fuel gauge on the dashboard, usually the tiny dial with E on the left and F on the right. Ignore the speedometer, tachometer, trip meter, warning lights, and all other gauges. Use 1/4 when the needle is clearly close to E, 1/2 when it is around the middle, and 3/4 only when it is clearly above the middle and visibly closer to F than to E. If uncertain, choose the lower level. Return JSON only.'],
        ];

        if ($rawText !== '') {
            $userContent[] = ['type' => 'input_text', 'text' => 'Photo notes: '.$rawText];
        }

        foreach ($imageDataUris as $imageData) {
            $userContent[] = ['type' => 'input_image', 'image_url' => $imageData['image_data_uri'], 'detail' => 'low'];
        }

        $response = OpenAI::responses()->create([
            'model' => $model,
            'temperature' => $temperature,
            'max_output_tokens' => $maxOutputTokens,
            'input' => [
                [
                    'role' => 'system',
                    'content' => [
                        ['type' => 'input_text', 'text' => $systemPrompt],
                    ],
                ],
                [
                    'role' => 'user',
                    'content' => $userContent,
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => 'vehicle_fuel_extraction',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        $outputText = trim((string) ($response->outputText ?? ''));
        $decoded = json_decode($outputText, true);
        if (is_array($decoded)) {
            return $decoded;
        }

        throw new RuntimeException('OpenAI fuel extraction response is not valid JSON.');
    }

    private function normalizeItem(array $item, string $reportType): array
    {
        $viewSide = $this->normalizeViewSide($item['view_side'] ?? null);
        $zoneCode = $this->normalizeZoneCode($item['zone_code'] ?? null, $viewSide);
        $damageType = $this->normalizeDamageType($item['damage_type'] ?? null);
        $severity = $this->normalizeSeverity($item['severity'] ?? null);
        $damageTiming = $this->normalizeDamageTiming($item['damage_timing'] ?? null, $reportType);

        return [
            'zone_code' => $zoneCode,
            'view_side' => $viewSide,
            'damage_type' => $damageType,
            'severity' => $severity,
            'damage_timing' => $damageTiming,
            'quantity' => max(1, (int) ($item['quantity'] ?? 1)),
            'marker_x' => isset($item['marker_x']) && is_numeric($item['marker_x']) ? (float) $item['marker_x'] : null,
            'marker_y' => isset($item['marker_y']) && is_numeric($item['marker_y']) ? (float) $item['marker_y'] : null,
            'estimated_cost' => isset($item['estimated_cost']) && is_numeric($item['estimated_cost']) ? (float) $item['estimated_cost'] : null,
            'notes' => $this->nullableString($item['notes'] ?? null),
            'confidence' => isset($item['confidence']) && is_numeric($item['confidence']) ? (float) $item['confidence'] : null,
        ];
    }

    private function normalizeZoneCode(mixed $value, string $viewSide): string
    {
        $zone = strtolower(trim((string) ($value ?? '')));
        if ($zone !== '' && in_array($zone, CarDamageCatalog::zoneCodes(), true)) {
            return $zone;
        }

        return match ($viewSide) {
            'rear' => 'rear_bumper',
            'left' => 'left_front_door',
            'right' => 'right_front_door',
            'top' => 'roof',
            default => 'front_bumper',
        };
    }

    private function normalizeViewSide(mixed $value): string
    {
        $viewSide = strtolower(trim((string) ($value ?? '')));
        $allowed = array_column(CarDamageCatalog::viewSides(), 'value');

        return in_array($viewSide, $allowed, true) ? $viewSide : 'front';
    }

    private function normalizeDamageType(mixed $value): string
    {
        $damageType = strtolower(trim((string) ($value ?? '')));
        $allowed = array_column(CarDamageCatalog::damageTypes(), 'value');

        return in_array($damageType, $allowed, true) ? $damageType : 'other';
    }

    private function normalizeSeverity(mixed $value): string
    {
        $severity = strtolower(trim((string) ($value ?? '')));
        $allowed = array_column(CarDamageCatalog::severityLevels(), 'value');

        return in_array($severity, $allowed, true) ? $severity : 'minor';
    }

    private function normalizePhotoType(mixed $value): string
    {
        $photoType = strtolower(trim((string) ($value ?? 'damage')));
        $allowed = ['damage', 'odometer', 'fuel'];

        return in_array($photoType, $allowed, true) ? $photoType : 'damage';
    }

    private function normalizeFuelLevel(mixed $value): ?string
    {
        $fuelLevel = strtolower(trim((string) ($value ?? '')));
        $normalized = match ($fuelLevel) {
            'empty', '0', '0/4', '0%', 'empty tank' => 'empty',
            'quarter', '1/4', '1-4', '1 4', 'one-quarter', 'one quarter', '25', '25%', 'quarter tank' => '1/4',
            'half', '1/2', '1-2', '1 2', 'two-quarters', 'two quarters', '50', '50%', 'half tank' => '1/2',
            'three_quarters', '3/4', '3-4', '3 4', 'three-quarters', 'three quarters', '75', '75%', '3/4 tank' => '3/4',
            'full', '1', '100', '100%', 'full tank' => 'full',
            default => null,
        };

        return $normalized;
    }

    private function normalizeDamageTiming(mixed $value, string $reportType): string
    {
        $timing = strtolower(trim((string) ($value ?? '')));
        $allowed = array_column(CarDamageCatalog::damageTimings(), 'value');

        if (in_array($timing, $allowed, true)) {
            return $timing;
        }

        return $reportType === 'after_return' ? 'after_return' : 'before_pickup';
    }

    private function extractImageDataUriFromPath(string $absolutePath): ?string
    {
        if (!is_file($absolutePath)) {
            return null;
        }

        $mime = strtolower((string) @mime_content_type($absolutePath));
        $extension = strtolower((string) pathinfo($absolutePath, PATHINFO_EXTENSION));
        $isImage = str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true);
        if (!$isImage) {
            return null;
        }

        try {
            $content = file_get_contents($absolutePath);
            if (!is_string($content) || $content === '') {
                return null;
            }

            $resolvedMime = str_starts_with($mime, 'image/') ? $mime : match ($extension) {
                'jpg', 'jpeg' => 'image/jpeg',
                'png' => 'image/png',
                'webp' => 'image/webp',
                'gif' => 'image/gif',
                'bmp' => 'image/bmp',
                default => 'image/jpeg',
            };

            return 'data:'.$resolvedMime.';base64,'.base64_encode($content);
        } catch (\Throwable) {
            return null;
        }
    }

    private function resolveAbsolutePath(string $path): ?string
    {
        $path = trim($path);
        if ($path === '') {
            return null;
        }

        if (is_file($path)) {
            return $path;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        if ($normalized === '') {
            return null;
        }

        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        if (!$disk->exists($normalized)) {
            return null;
        }

        return $disk->path($normalized);
    }

    private function fileLabelFromPath(string $path): string
    {
        $path = trim($path);

        return $path === '' ? 'damage-photo' : basename($path);
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
