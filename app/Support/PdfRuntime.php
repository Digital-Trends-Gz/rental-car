<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;

final class PdfRuntime
{
    public static function nodeBinary(): ?string
    {
        static $binary = null;

        if ($binary !== null) {
            return $binary ?: null;
        }

        $configured = trim((string) config('laravel-pdf.browsershot.node_binary', ''));
        if ($configured !== '') {
            $binary = $configured;

            return $binary;
        }

        $finder = new ExecutableFinder();
        $binary = $finder->find('node')
            ?: $finder->find('nodejs')
            ?: '';

        return $binary ?: null;
    }

    public static function hasNodeBinary(): bool
    {
        return self::nodeBinary() !== null;
    }
}
