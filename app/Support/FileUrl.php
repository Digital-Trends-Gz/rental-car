<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class FileUrl
{
    public static function fromStoragePath(?string $path): string
    {
        $path = (string) $path;

        // Return absolute URLs as-is
        if ($path !== '' && preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        // Strip any leading "storage/" prefix (the files table stores paths with this prefix)
        // so that Storage::url() doesn't double it to "/storage/storage/..."
        $normalized = ltrim(preg_replace('#^storage[/\\\\]+#', '', $path), '/\\');

        return Storage::url($normalized);
    }
}
