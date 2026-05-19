<?php

namespace App\Services\Contracts;

use App\Core\AiProviderSettings;
use App\Services\ClientDocuments\LocalClientDocumentExtractor;
use App\Services\ClientDocuments\OpenAiClientDocumentExtractor;
use RuntimeException;

class ContractDriverDocumentExtractor
{
    public function __construct(
        private LocalClientDocumentExtractor $localClientDocumentExtractor,
        private OpenAiClientDocumentExtractor $openAiClientDocumentExtractor
    ) {
    }

    /**
     * @param  array<int, string>  $tempFolders
     * @return array{
     *   fields: array<string, mixed>,
     *   raw_output: array<string, mixed>,
     *   raw_text: string,
     *   confidence: float|null,
     *   provider: string|null,
     *   engine: string|null
     * }
     */
    public function extractFromTempFolders(array $tempFolders, string $documentType): array
    {
        try {
            $result = $this->resolveExtractor()->extractFromTempFolders($tempFolders, $documentType);
        } catch (\Throwable $e) {
            if ($this->shouldFallbackToLocalOcr($e)) {
                $result = $this->localClientDocumentExtractor->extractFromTempFolders($tempFolders, $documentType);
            } else {
                throw $e;
            }
        }

        return [
            'fields' => $this->mapFields($result['fields'], $documentType),
            'raw_output' => $result['raw_output'],
            'raw_text' => $result['raw_text'],
            'confidence' => $result['confidence'],
            'provider' => $result['provider'],
            'engine' => $result['engine'],
        ];
    }

    /**
     * @param  array<int, string>  $filePaths
     * @return array{
     *   fields: array<string, mixed>,
     *   raw_output: array<string, mixed>,
     *   raw_text: string,
     *   confidence: float|null,
     *   provider: string|null,
     *   engine: string|null
     * }
     */
    public function extractFromFilePaths(array $filePaths, string $documentType): array
    {
        try {
            $result = $this->resolveExtractor()->extractFromFilePaths($filePaths, $documentType);
        } catch (\Throwable $e) {
            if ($this->shouldFallbackToLocalOcr($e)) {
                $result = $this->localClientDocumentExtractor->extractFromFilePaths($filePaths, $documentType);
            } else {
                throw $e;
            }
        }

        return [
            'fields' => $this->mapFields($result['fields'], $documentType),
            'raw_output' => $result['raw_output'],
            'raw_text' => $result['raw_text'],
            'confidence' => $result['confidence'],
            'provider' => $result['provider'],
            'engine' => $result['engine'],
        ];
    }

    private function resolveExtractor(): LocalClientDocumentExtractor|OpenAiClientDocumentExtractor
    {
        $settings = AiProviderSettings::load();
        $provider = (string) ($settings['provider'] ?? 'openai');

        if ($provider === 'openai' && AiProviderSettings::isConfiguredForCurrentProvider()) {
            return $this->openAiClientDocumentExtractor;
        }

        if (!config('local_ocr.enabled', true)) {
            throw new RuntimeException('OpenAI is not available for this extraction and local OCR is disabled.');
        }

        return $this->localClientDocumentExtractor;
    }

    private function shouldFallbackToLocalOcr(\Throwable $exception): bool
    {
        if (!config('local_ocr.enabled', true)) {
            return false;
        }

        $message = strtolower($exception->getMessage());

        return str_contains($message, 'rate limit')
            || str_contains($message, 'too many requests')
            || str_contains($message, '429')
            || str_contains($message, 'quota')
            || str_contains($message, 'curl error 28')
            || str_contains($message, 'operation timed out')
            || str_contains($message, 'timeout');
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function mapFields(array $fields, string $documentType): array
    {
        $normalized = [];

        $fullName = $this->nullableString($fields['full_name'] ?? null);
        if ($fullName !== null) {
            $normalized['full_name'] = $fullName;
        }

        $fullNameAr = $this->nullableString($fields['full_name_ar'] ?? null);
        if ($fullNameAr !== null) {
            $normalized['full_name_ar'] = $fullNameAr;
        }

        $nationality = $this->nullableString($fields['nationality'] ?? null);
        if ($nationality !== null) {
            $normalized['nationality'] = $nationality;
        }

        $placeOfIssue = $this->nullableString($fields['place_of_issue'] ?? null);
        if ($placeOfIssue !== null) {
            $normalized['place_of_issue'] = $placeOfIssue;
        }

        $dateOfBirth = $this->nullableString($fields['date_of_birth'] ?? null);
        if ($dateOfBirth !== null) {
            $normalized['date_of_birth'] = $dateOfBirth;
        }

        $documentNumber = $this->nullableString($fields['document_number'] ?? null);
        $expiryDate = $this->nullableString($fields['expiry_date'] ?? null);

        if ($documentType === 'driver_license') {
            if ($documentNumber !== null) {
                $normalized['license_number'] = $documentNumber;
            }
            if ($expiryDate !== null) {
                $normalized['license_expiry_date'] = $expiryDate;
            }
        } elseif ($documentType === 'visa') {
            if ($documentNumber !== null) {
                $normalized['visa_number'] = $documentNumber;
            }
            if ($expiryDate !== null) {
                $normalized['visa_expiry_date'] = $expiryDate;
            }
        } else {
            if (in_array($documentType, ['passport', 'id_card'], true) && $documentNumber !== null) {
                $normalized['identity_number'] = $documentNumber;
            }

            if ($documentType === 'residency_card' && $documentNumber !== null) {
                $normalized['residency_number'] = $documentNumber;
            }

            if ($expiryDate !== null) {
                $normalized['identity_expiry_date'] = $expiryDate;
            }
        }

        return $normalized;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}




