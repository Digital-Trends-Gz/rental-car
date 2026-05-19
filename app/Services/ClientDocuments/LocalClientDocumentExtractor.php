<?php

namespace App\Services\ClientDocuments;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Models\TempFile;
use RuntimeException;

class LocalClientDocumentExtractor
{
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
        $folders = array_values(array_unique(array_filter(array_map(
            static fn ($folder) => trim((string) $folder),
            $tempFolders
        ))));

        if ($folders === []) {
            throw new RuntimeException('No uploaded files were provided for OCR extraction.');
        }

        $tempFiles = TempFile::query()
            ->whereIn('folder', $folders)
            ->orderByDesc('id')
            ->get();

        $paths = [];
        foreach ($tempFiles as $file) {
            if ($file->path !== '') {
                $paths[] = $file->path;
            }
        }

        return $this->extractFromFilePaths($paths, $documentType);
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
        if (!config('local_ocr.enabled', true)) {
            throw new RuntimeException('Local OCR is disabled. Set LOCAL_OCR_ENABLED=true to use document extraction.');
        }

        $paths = [];

        foreach ($filePaths as $path) {
            $resolved = $this->resolvePath((string) $path);
            if ($resolved === null) {
                continue;
            }

            $paths[] = $resolved;
        }

        if ($paths === []) {
            throw new RuntimeException('Uploaded files are missing on disk. Please upload the document again.');
        }

        $pythonBinary = trim((string) config('local_ocr.python_binary', 'python'));
        $scriptPath = (string) config('local_ocr.script_path');
        $timeout = (int) config('local_ocr.timeout', 90);
        $engine = trim((string) config('local_ocr.engine', 'auto'));
        $ollamaModel = trim((string) config('local_ocr.ollama_model', 'llava'));
        $tesseractBinary = trim((string) config('local_ocr.tesseract_binary', ''));
        $maxImageSide = max(0, (int) config('local_ocr.max_image_side', 1600));
        $grayscale = (bool) config('local_ocr.grayscale', true);
        $autocontrast = (bool) config('local_ocr.autocontrast', true);

        if ($scriptPath === '' || !is_file($scriptPath)) {
            throw new RuntimeException('Local OCR script was not found. Expected file: '.$scriptPath);
        }

        $command = [$pythonBinary, $scriptPath, '--document-type', $documentType];

        if ($engine !== '') {
            $command[] = '--engine';
            $command[] = $engine;
        }

        if ($ollamaModel !== '') {
            $command[] = '--ollama-model';
            $command[] = $ollamaModel;
        }

        if ($tesseractBinary !== '') {
            $command[] = '--tesseract-cmd';
            $command[] = $tesseractBinary;
        }

        if ($maxImageSide > 0) {
            $command[] = '--max-side';
            $command[] = (string) $maxImageSide;
        }

        if ($grayscale) {
            $command[] = '--grayscale';
        }

        if ($autocontrast) {
            $command[] = '--autocontrast';
        }

        $result = Process::timeout($timeout)->run(array_merge($command, $paths));

        if ($result->failed()) {
            $message = trim($result->errorOutput() ?: $result->output());
            if ($message === '') {
                $message = 'Local OCR process failed.';
            }

            if (str_contains(strtolower($message), 'no module named')
                || str_contains(strtolower($message), 'not recognized')
                || str_contains(strtolower($message), 'cannot find')
                || str_contains(strtolower($message), 'failed to run')
                || str_contains(strtolower($message), 'tesseract is not installed')
                || str_contains(strtolower($message), 'ollama')
            ) {
                $message .= ' Configure LOCAL_OCR_PYTHON_BINARY and install the selected engine dependencies. For Ollama mode, install the Python package ollama, run the Ollama service, and download the configured model.';
            }

            throw new RuntimeException($message);
        }

        $rawOutput = $this->sanitizeJsonOutput($result->output());
        $payload = json_decode($rawOutput, true);
        if (!is_array($payload)) {
            throw new RuntimeException('Local OCR returned an invalid response.');
        }

        $fields = $payload['fields'] ?? [];

        return [
            'fields' => is_array($fields) ? $this->normalizeFields($fields) : [],
            'raw_output' => $payload,
            'raw_text' => trim((string) ($payload['raw_text'] ?? '')),
            'confidence' => isset($payload['confidence']) && is_numeric($payload['confidence'])
                ? max(0.0, min(1.0, (float) $payload['confidence']))
                : null,
            'provider' => $this->nullableString($payload['provider'] ?? null),
            'engine' => $this->nullableString($payload['engine'] ?? null),
        ];
    }

    private function resolvePath(string $path): ?string
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

    /**
     * @param  array<string, mixed>  $fields
     * @return array<string, mixed>
     */
    private function normalizeFields(array $fields): array
    {
        $allowedKeys = [
            'document_number',
            'full_name',
            'date_of_birth',
            'expiry_date',
            'issue_date',
            'nationality',
            'license_class',
            'address',
            'place_of_issue',
        ];

        $normalized = [];

        foreach ($allowedKeys as $key) {
            if (!array_key_exists($key, $fields)) {
                continue;
            }

            $value = $fields[$key];
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function sanitizeJsonOutput(string $output): string
    {
        $output = trim($output);
        $output = preg_replace('/^\xEF\xBB\xBF/', '', $output) ?? $output;

        if ($output === '') {
            return $output;
        }

        $decoded = json_decode($output, true);
        if (is_array($decoded)) {
            return $output;
        }

        $objectStart = strpos($output, '{');
        $objectEnd = strrpos($output, '}');
        if ($objectStart !== false && $objectEnd !== false && $objectEnd > $objectStart) {
            $candidate = substr($output, $objectStart, $objectEnd - $objectStart + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $candidate;
            }
        }

        $arrayStart = strpos($output, '[');
        $arrayEnd = strrpos($output, ']');
        if ($arrayStart !== false && $arrayEnd !== false && $arrayEnd > $arrayStart) {
            $candidate = substr($output, $arrayStart, $arrayEnd - $arrayStart + 1);
            $decoded = json_decode($candidate, true);
            if (is_array($decoded)) {
                return $candidate;
            }
        }

        return $output;
    }

    private function nullableString(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));

        return $text === '' ? null : $text;
    }
}
