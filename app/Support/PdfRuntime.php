<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;

final class PdfRuntime
{
    public static function hasNodeBinary(): bool
    {
        static $available = null;

        if ($available !== null) {
            return $available;
        }

        $finder = new ExecutableFinder();

        $available = (bool) ($finder->find('node') ?: $finder->find('nodejs'));

        return $available;
    }
}
