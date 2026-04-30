<?php

$executableFinder = new Symfony\Component\Process\ExecutableFinder();

$findExecutable = static function (string $name, array $dirs = []) use ($executableFinder): ?string {
    $resolved = $executableFinder->find($name, null, $dirs);

    return $resolved ?: null;
};

$linuxBinaryDirs = [
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

$defaultNodeBinary = $findExecutable('node', $linuxBinaryDirs)
    ?: $findExecutable('nodejs', $linuxBinaryDirs)
    ?: collect(array_merge($linuxBinaryDirs, [
        '/usr/local/bin/node',
        '/usr/bin/node',
        '/usr/bin/nodejs',
        '/usr/local/bin/nodejs',
    ]))->first(fn (string $path) => is_file($path));

$defaultNpmBinary = $findExecutable('npm', $linuxBinaryDirs)
    ?: $findExecutable('npm-cli.js', $linuxBinaryDirs)
    ?: collect(array_merge($linuxBinaryDirs, [
        '/usr/local/bin/npm',
        '/usr/bin/npm',
        '/usr/local/bin/npm-cli.js',
        '/usr/bin/npm-cli.js',
    ]))->first(fn (string $path) => is_file($path));

$defaultChromePath = collect([
    'C:\Program Files\Google\Chrome\Application\chrome.exe',
    'C:\Program Files (x86)\Google\Chrome\Application\chrome.exe',
    'C:\Program Files\Microsoft\Edge\Application\msedge.exe',
    'C:\Program Files (x86)\Microsoft\Edge\Application\msedge.exe',
    '/usr/bin/google-chrome',
    '/usr/bin/google-chrome-stable',
    '/usr/bin/chromium',
    '/usr/bin/chromium-browser',
    '/usr/bin/microsoft-edge',
    '/snap/bin/chromium',
])->first(fn (string $path) => is_file($path));

return [
    'driver' => env('LARAVEL_PDF_DRIVER', 'browsershot'),

    'job' => Spatie\LaravelPdf\Jobs\GeneratePdfJob::class,

    'browsershot' => [
        'node_binary' => env('LARAVEL_PDF_NODE_BINARY', $defaultNodeBinary),
        'npm_binary' => env('LARAVEL_PDF_NPM_BINARY', $defaultNpmBinary),
        'include_path' => env('LARAVEL_PDF_INCLUDE_PATH'),
        'chrome_path' => env('LARAVEL_PDF_CHROME_PATH', $defaultChromePath),
        'node_modules_path' => env('LARAVEL_PDF_NODE_MODULES_PATH', base_path('node_modules')),
        'bin_path' => env('LARAVEL_PDF_BIN_PATH'),
        'temp_path' => env('LARAVEL_PDF_TEMP_PATH', storage_path('app/pdf-temp')),
        'write_options_to_file' => env('LARAVEL_PDF_WRITE_OPTIONS_TO_FILE', true),
        'no_sandbox' => env('LARAVEL_PDF_NO_SANDBOX', false),
    ],

    'cloudflare' => [
        'api_token' => env('CLOUDFLARE_API_TOKEN'),
        'account_id' => env('CLOUDFLARE_ACCOUNT_ID'),
    ],

    'gotenberg' => [
        'url' => env('GOTENBERG_URL', 'http://localhost:3000'),
        'username' => env('GOTENBERG_USERNAME'),
        'password' => env('GOTENBERG_PASSWORD'),
    ],

    'dompdf' => [
        'is_remote_enabled' => env('LARAVEL_PDF_DOMPDF_REMOTE_ENABLED', false),
        'chroot' => env('LARAVEL_PDF_DOMPDF_CHROOT'),
    ],

    'weasyprint' => [
        'binary' => env('LARAVEL_PDF_WEASYPRINT_BINARY', 'weasyprint'),
        'timeout' => 10,
    ],
];
