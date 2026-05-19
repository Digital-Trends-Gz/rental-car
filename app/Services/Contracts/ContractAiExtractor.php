<?php

namespace App\Services\Contracts;

use App\Core\AiExtractionQuota;
use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Models\TempFile;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Smalot\PdfParser\Parser;

class ContractAiExtractor
{
    /**
     * @param  array<int, string>  $tempFolders
     * @return array{
     *   fields: array<string, mixed>,
     *   raw_output: array<string, mixed>|null,
     *   text_preview: string
     * }
     */
    public function extractFromTempFolders(array $tempFolders): array
    {
        $folders = array_values(array_unique(array_filter(array_map(
            fn ($folder) => trim((string) $folder),
            $tempFolders
        ))));

        if (empty($folders)) {
            throw new RuntimeException('No uploaded files were provided for AI extraction.');
        }

        $tempFiles = TempFile::query()
            ->whereIn('folder', $folders)
            ->orderByDesc('id')
            ->get();

        if ($tempFiles->isEmpty()) {
            throw new RuntimeException('Uploaded files were not found. Please upload again.');
        }

        $chunks = [];
        $imageDataUris = [];
        foreach ($tempFiles as $file) {
            $text = $this->extractTextFromTempFile($file);
            if ($text !== '') {
                $chunks[] = "File: {$file->original_name}\n{$text}";
                continue;
            }

            $imageDataUri = $this->extractImageDataUriFromTempFile($file);
            if ($imageDataUri !== null) {
                $imageDataUris[] = $imageDataUri;
            }
        }

        $mergedText = trim(implode("\n\n----------------\n\n", $chunks));
        if ($mergedText === '' && empty($imageDataUris)) {
            throw new RuntimeException('Could not read content from uploaded files. Upload a text-based PDF or an image file.');
        }

        // Keep prompt size smaller to reduce token-per-minute rate-limit pressure.
        $mergedText = mb_substr($mergedText, 0, 15000);
        $raw = $this->extractStructuredDataWithOpenAi($mergedText, $imageDataUris);
        $fields = $this->normalizeFields($raw);

        return [
            'fields' => $fields,
            'raw_output' => $raw,
            'text_preview' => mb_substr($mergedText, 0, 1200),
        ];
    }

    private function extractTextFromTempFile(TempFile $file): string
    {
        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        if (!$disk->exists($file->path)) {
            return '';
        }

        $absolutePath = $disk->path($file->path);
        $mime = strtolower((string) ($file->mime_type ?? ''));
        $extension = strtolower((string) pathinfo((string) $file->original_name, PATHINFO_EXTENSION));

        if (str_contains($mime, 'pdf') || $extension === 'pdf') {
            try {
                $parser = new Parser();
                $pdf = $parser->parseFile($absolutePath);
                return $this->normalizeText($pdf->getText());
            } catch (\Throwable) {
                return '';
            }
        }

        if (str_starts_with($mime, 'text/') || in_array($extension, ['txt', 'csv', 'json'], true)) {
            try {
                $content = file_get_contents($absolutePath);
                return $this->normalizeText(is_string($content) ? $content : '');
            } catch (\Throwable) {
                return '';
            }
        }

        return '';
    }

    private function extractImageDataUriFromTempFile(TempFile $file): ?string
    {
        $disk = Storage::disk(config('vilt-filepond.storage_disk'));
        if (!$disk->exists($file->path)) {
            return null;
        }

        $mime = strtolower((string) ($file->mime_type ?? ''));
        $extension = strtolower((string) pathinfo((string) $file->original_name, PATHINFO_EXTENSION));
        $isImage = str_starts_with($mime, 'image/') || in_array($extension, ['jpg', 'jpeg', 'png', 'webp', 'gif', 'bmp'], true);
        if (!$isImage) {
            return null;
        }

        try {
            $content = $disk->get($file->path);
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

    private function normalizeText(string $text): string
    {
        $text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
        $text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
        return trim($text);
    }

    /**
     * @return array<string, mixed>
     */
    /**
     * @param  array<int, string>  $imageDataUris
     * @return array<string, mixed>
     */
    private function extractStructuredDataWithOpenAi(string $contractText, array $imageDataUris = []): array
    {
        $settings = AiProviderSettings::load();
        $provider = (string) ($settings['provider'] ?? 'openai');
        if ($provider !== 'openai') {
            throw new RuntimeException('Current AI provider is not supported for instant form auto-fill yet. Please switch provider to OpenAI.');
        }
        $openAi = $settings['openai'] ?? [];

        $model = (string) ($openAi['model'] ?? 'gpt-4.1-mini');
        $temperature = (float) ($openAi['temperature'] ?? 0.1);
        $maxOutputTokens = (int) ($openAi['max_output_tokens'] ?? 1200);
        $maxOutputTokens = max(200, min($maxOutputTokens, 500));
        $systemPrompt = trim((string) ($openAi['system_prompt'] ?? ''));
        AiExtractionQuota::ensureAvailable(TenantContext::get());
        if ($systemPrompt === '') {
            $systemPrompt = 'Extract rental contract fields from Arabic/English text and return strict JSON only.';
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'contract_number' => ['type' => ['string', 'null']],
                'status' => ['type' => ['string', 'null']],
                'contract_date' => ['type' => ['string', 'null']],
                'renter_name' => ['type' => ['string', 'null']],
                'renter_id_number' => ['type' => ['string', 'null']],
                'renter_phone' => ['type' => ['string', 'null']],
                'car_details' => ['type' => ['string', 'null']],
                'plate_number' => ['type' => ['string', 'null']],
                'start_date' => ['type' => ['string', 'null']],
                'end_date' => ['type' => ['string', 'null']],
                'total_amount' => ['type' => ['number', 'string', 'null']],
                'currency' => ['type' => ['string', 'null']],
                'notes' => ['type' => ['string', 'null']],
            ],
            'required' => [
                'contract_number',
                'status',
                'contract_date',
                'renter_name',
                'renter_id_number',
                'renter_phone',
                'car_details',
                'plate_number',
                'start_date',
                'end_date',
                'total_amount',
                'currency',
                'notes',
            ],
        ];

        $userContent = [
            [
                'type' => 'input_text',
                'text' => 'Extract values for this car rental contract and return JSON only.',
            ],
        ];

        if ($contractText !== '') {
            $userContent[] = [
                'type' => 'input_text',
                'text' => "Contract text:\n\n".$contractText,
            ];
        }

        foreach ($imageDataUris as $imageDataUri) {
            $userContent[] = [
                'type' => 'input_image',
                'image_url' => $imageDataUri,
                'detail' => 'low',
            ];
        }

        $response = $this->createResponseWithRetry([
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
                    'name' => 'contract_extraction',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        $outputText = trim((string) ($response->outputText ?? ''));
        if ($outputText === '') {
            throw new RuntimeException('AI returned an empty extraction response.');
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

        throw new RuntimeException('AI response is not valid JSON.');
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function createResponseWithRetry(array $payload): object
    {
        try {
            return OpenAI::responses()->create($payload);
        } catch (\Throwable $e) {
            $message = strtolower((string) $e->getMessage());
            $isRateLimit = str_contains($message, 'rate limit')
                || str_contains($message, 'too many requests')
                || str_contains($message, '429')
                || str_contains($message, 'quota');

            if ($isRateLimit) {
                throw new RuntimeException('Request rate limit has been exceeded. Please wait 30-60 seconds and retry.');
            }

            throw new RuntimeException('AI extraction failed: '.$e->getMessage());
        }
    }

    /**
     * @param  array<string, mixed>  $raw
     * @return array<string, mixed>
     */
    private function normalizeFields(array $raw): array
    {
        $result = [
            'contract_number' => $this->normalizeString($raw['contract_number'] ?? null),
            'status' => $this->normalizeStatus($raw['status'] ?? null),
            'contract_date' => $this->normalizeDate($raw['contract_date'] ?? null),
            'renter_name' => $this->normalizeString($raw['renter_name'] ?? null),
            'renter_id_number' => $this->normalizeString($raw['renter_id_number'] ?? null),
            'renter_phone' => $this->normalizeString($raw['renter_phone'] ?? null),
            'car_details' => $this->normalizeString($raw['car_details'] ?? null),
            'plate_number' => $this->normalizeString($raw['plate_number'] ?? null),
            'start_date' => $this->normalizeDate($raw['start_date'] ?? null),
            'end_date' => $this->normalizeDate($raw['end_date'] ?? null),
            'total_amount' => $this->normalizeNumber($raw['total_amount'] ?? null),
            'currency' => $this->normalizeCurrency($raw['currency'] ?? null),
            'notes' => $this->normalizeString($raw['notes'] ?? null),
        ];

        return array_filter($result, fn ($value) => $value !== null && $value !== '');
    }

    private function normalizeString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        return $text === '' ? null : $text;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $text = $this->normalizeString($value);
        if ($text === null) {
            return null;
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeStatus(mixed $value): ?string
    {
        $text = strtolower((string) ($value ?? ''));
        if ($text === '') {
            return null;
        }

        if (in_array($text, ['pending', 'active', 'completed', 'cancelled'], true)) {
            return $text;
        }

        return null;
    }

    private function normalizeNumber(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^\d.\-]/', '', (string) $value);
        if ($cleaned === null || $cleaned === '' || !is_numeric($cleaned)) {
            return null;
        }

        return (float) $cleaned;
    }

    private function normalizeCurrency(mixed $value): ?string
    {
        $text = strtoupper((string) ($value ?? ''));
        $text = preg_replace('/[^A-Z]/', '', $text) ?? '';
        if (strlen($text) !== 3) {
            return null;
        }

        return $text;
    }
}
