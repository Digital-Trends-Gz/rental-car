<?php

namespace App\Services\ClientDocuments;

use App\Core\AiExtractionQuota;
use App\Core\AiProviderSettings;
use App\Core\TenantContext;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Models\TempFile;
use OpenAI\Laravel\Facades\OpenAI;
use RuntimeException;
use Smalot\PdfParser\Parser;

class OpenAiClientDocumentExtractor
{
    /**
     * @param  array<int, string>  $tempFolders
     * @return array{
     *   fields: array<string, mixed>,
     *   raw_output: array<string, mixed>,
     *   raw_text: string,
     *   confidence: float|null,
     *   provider: string,
     *   engine: string|null
     * }
     */
    public function extractFromTempFolders(array $tempFolders, string $documentType): array
    {
        $settings = AiProviderSettings::load();
        $provider = (string) ($settings['provider'] ?? 'openai');
        if ($provider !== 'openai') {
            throw new RuntimeException('Current AI provider is not supported for document extraction on this screen. Switch provider to OpenAI or use local OCR.');
        }

        if (!AiProviderSettings::isConfiguredForCurrentProvider()) {
            throw new RuntimeException('OpenAI provider is not fully configured in Super Admin settings.');
        }

        $folders = array_values(array_unique(array_filter(array_map(
            static fn ($folder) => trim((string) $folder),
            $tempFolders
        ))));

        if ($folders === []) {
            throw new RuntimeException('No uploaded files were provided for extraction.');
        }

        $tempFiles = TempFile::query()
            ->whereIn('folder', $folders)
            ->orderByDesc('id')
            ->get();

        if ($tempFiles->isEmpty()) {
            throw new RuntimeException('Uploaded files were not found. Please upload the document again.');
        }

        $chunks = [];
        $imageDataUris = [];

        foreach ($tempFiles as $file) {
            $text = $this->extractTextFromTempFile($file);
            if ($text !== '') {
                $chunks[] = "File: {$file->original_name}\n{$text}";
            }

            $imageDataUri = $this->extractImageDataUriFromTempFile($file);
            if ($imageDataUri !== null) {
                $imageDataUris[] = $imageDataUri;
            }
        }

        $rawText = trim(implode("\n\n----------------\n\n", $chunks));
        if ($rawText === '' && $imageDataUris === []) {
            throw new RuntimeException('Could not read content from uploaded files. Upload image or readable PDF files.');
        }

        $rawText = mb_substr($rawText, 0, 10000);
        $rawOutput = $this->extractStructuredDataWithOpenAi($documentType, $rawText, $imageDataUris);

        return [
            'fields' => $this->normalizeFields(is_array($rawOutput) ? $rawOutput : []),
            'raw_output' => is_array($rawOutput) ? $rawOutput : [],
            'raw_text' => $rawText,
            'confidence' => null,
            'provider' => 'openai',
            'engine' => (string) (($settings['openai'] ?? [])['model'] ?? 'gpt-4.1-mini'),
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
     * @param  array<int, string>  $imageDataUris
     * @return array<string, mixed>
     */
    private function extractStructuredDataWithOpenAi(string $documentType, string $rawText, array $imageDataUris): array
    {
        $settings = AiProviderSettings::load();
        $openAi = $settings['openai'] ?? [];
        $model = (string) ($openAi['model'] ?? 'gpt-4.1-mini');
        $temperature = (float) ($openAi['temperature'] ?? 0.1);
        $maxOutputTokens = max(200, min((int) ($openAi['max_output_tokens'] ?? 1200), 700));
        $systemPrompt = trim((string) ($openAi['system_prompt'] ?? ''));
        AiExtractionQuota::ensureAvailable(TenantContext::get());

        if ($systemPrompt === '') {
            $systemPrompt = 'Extract driver identity or license document fields from Arabic or English documents and return strict JSON only.';
        }

        $schema = [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'document_number' => ['type' => ['string', 'null']],
                'full_name' => ['type' => ['string', 'null']],
                'full_name_ar' => ['type' => ['string', 'null']],
                'date_of_birth' => ['type' => ['string', 'null']],
                'expiry_date' => ['type' => ['string', 'null']],
                'issue_date' => ['type' => ['string', 'null']],
                'nationality' => ['type' => ['string', 'null']],
                'license_class' => ['type' => ['string', 'null']],
                'address' => ['type' => ['string', 'null']],
                'place_of_issue' => ['type' => ['string', 'null']],
            ],
            'required' => [
                'document_number',
                'full_name',
                'full_name_ar',
                'date_of_birth',
                'expiry_date',
                'issue_date',
                'nationality',
                'license_class',
                'address',
                'place_of_issue',
            ],
        ];

        $documentLabel = match ($documentType) {
            'passport' => 'passport',
            'driver_license' => 'driver license',
            'residency_card' => 'residency card',
            default => 'identity card',
        };

        $userContent = [[
            'type' => 'input_text',
            'text' => "Extract fields from this {$documentLabel}. Return JSON only.",
        ]];

        if ($rawText !== '') {
            $userContent[] = [
                'type' => 'input_text',
                'text' => "Recognized text:\n\n".$rawText,
            ];
        }

        foreach ($imageDataUris as $imageDataUri) {
            $userContent[] = [
                'type' => 'input_image',
                'image_url' => $imageDataUri,
                'detail' => 'low',
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
                    'name' => 'driver_document_extraction',
                    'schema' => $schema,
                    'strict' => true,
                ],
            ],
        ]);

        $outputText = trim((string) ($response->outputText ?? ''));
        if ($outputText === '') {
            throw new RuntimeException('OpenAI returned an empty extraction response.');
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

        throw new RuntimeException('OpenAI response is not valid JSON.');
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function normalizeFields(array $fields): array
    {
        $normalized = [];

        foreach ([
            'document_number',
            'full_name',
            'full_name_ar',
            'nationality',
            'license_class',
            'address',
            'place_of_issue',
        ] as $key) {
            $value = $this->nullableString($fields[$key] ?? null);
            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        foreach (['date_of_birth', 'expiry_date', 'issue_date'] as $key) {
            $value = $this->normalizeDate($fields[$key] ?? null);
            if ($value !== null) {
                $normalized[$key] = $value;
            }
        }

        return $normalized;
    }

    private function normalizeDate(mixed $value): ?string
    {
        $text = $this->nullableString($value);
        if ($text === null) {
            return null;
        }

        try {
            return Carbon::parse($text)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}




