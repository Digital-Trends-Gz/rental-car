<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class FileUrl
{
    public static function fromStoragePath(?string $path): string
    {
        $normalized = ltrim(preg_replace('/^storage\\//', '', (string) $path) ?? (string) $path, '/');

        if ($normalized !== '' && preg_match('/^https?:\\/\\//i', $normalized)) {
            return $normalized;
        }

        return Storage::url($normalized);
    }
}
