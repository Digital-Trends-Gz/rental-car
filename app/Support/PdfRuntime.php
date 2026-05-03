<?php

namespace App\Support;

use Symfony\Component\Process\ExecutableFinder;
use Illuminate\Support\Str;

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
            if (Str::contains($configured, DIRECTORY_SEPARATOR) || is_file($configured)) {
                $binary = $configured;

                return $binary;
            }

            $finder = new ExecutableFinder();
            $binary = $finder->find($configured, null, self::binaryDirs())
                ?: $configured;

            return $binary;
        }

        $finder = new ExecutableFinder();
        $binary = $finder->find('node', null, self::binaryDirs())
            ?: $finder->find('nodejs', null, self::binaryDirs())
            ?: self::shellBinaryPath('node')
            ?: self::shellBinaryPath('nodejs')
            ?: '';

        return $binary ?: null;
    }

    public static function npmBinary(): ?string
    {
        static $binary = null;

        if ($binary !== null) {
            return $binary ?: null;
        }

        $configured = trim((string) config('laravel-pdf.browsershot.npm_binary', ''));
        if ($configured !== '') {
            if (Str::contains($configured, DIRECTORY_SEPARATOR) || is_file($configured)) {
                $binary = $configured;

                return $binary;
            }

            $finder = new ExecutableFinder();
            $binary = $finder->find($configured, null, self::binaryDirs())
                ?: $configured;

            return $binary;
        }

        $finder = new ExecutableFinder();
        $binary = $finder->find('npm', null, self::binaryDirs())
            ?: $finder->find('npm-cli.js', null, self::binaryDirs())
            ?: self::shellBinaryPath('npm')
            ?: '';

        return $binary ?: null;
    }

    public static function chromeBinary(): ?string
    {
        static $binary = null;

        if ($binary !== null) {
            return $binary ?: null;
        }

        $configured = trim((string) config('laravel-pdf.browsershot.chrome_path', ''));
        if ($configured !== '') {
            if (Str::contains($configured, DIRECTORY_SEPARATOR) || is_file($configured)) {
                $binary = $configured;

                return $binary;
            }

            $finder = new ExecutableFinder();
            $binary = $finder->find($configured, null, self::binaryDirs())
                ?: $configured;

            return $binary;
        }

        $finder = new ExecutableFinder();
        $binary = $finder->find('google-chrome', null, self::binaryDirs())
            ?: $finder->find('google-chrome-stable', null, self::binaryDirs())
            ?: $finder->find('chromium', null, self::binaryDirs())
            ?: $finder->find('chromium-browser', null, self::binaryDirs())
            ?: $finder->find('microsoft-edge', null, self::binaryDirs())
            ?: self::shellBinaryPath('google-chrome')
            ?: self::shellBinaryPath('google-chrome-stable')
            ?: self::shellBinaryPath('chromium')
            ?: self::shellBinaryPath('chromium-browser')
            ?: self::shellBinaryPath('microsoft-edge')
            ?: '';

        return $binary ?: null;
    }

    public static function hasNodeBinary(): bool
    {
        return self::nodeBinary() !== null;
    }

    /**
     * @return array<int, string>
     */
    private static function binaryDirs(): array
    {
        return [
            '/usr/bin',
            '/usr/local/bin',
            '/bin',
            '/opt/homebrew/bin',
            '/opt/cpanel/ea-nodejs16/bin',
            '/opt/cpanel/ea-nodejs18/bin',
            '/opt/cpanel/ea-nodejs20/bin',
            '/opt/cpanel/ea-nodejs22/bin',
            '/opt/plesk/node/16/bin',
            '/opt/plesk/node/18/bin',
            '/opt/plesk/node/20/bin',
            '/usr/local/alt-nodejs/bin',
        ];
    }

    private static function shellBinaryPath(string $binary): ?string
    {
        if (! \function_exists('exec')) {
            return null;
        }

        $commands = [
            'command -v '.escapeshellarg($binary),
            'which '.escapeshellarg($binary),
        ];

        foreach ($commands as $command) {
            $output = [];
            $exitCode = 1;

            @\exec('/bin/bash -lc '.escapeshellarg($command).' 2>/dev/null', $output, $exitCode);

            if ($exitCode === 0) {
                $path = trim((string) implode("\n", $output));
                if ($path !== '' && is_file($path)) {
                    return $path;
                }
            }
        }

        return null;
    }
}
