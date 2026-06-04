<!DOCTYPE html>
<html
    lang="{{ str_replace('_', '-', app()->getLocale()) }}"
    dir="{{ \Mcamara\LaravelLocalization\Facades\LaravelLocalization::getCurrentLocaleDirection() }}"
    @class(['dark' => ($appearance ?? 'system') == 'dark'])
>
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        {{-- Inline script to detect system dark mode preference and apply it immediately --}}
        <script>
            (function() {
                const appearance = '{{ $appearance ?? "system" }}';

                if (appearance === 'system') {
                    const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;

                    if (prefersDark) {
                        document.documentElement.classList.add('dark');
                    }
                }
            })();
        </script>

        {{-- Inline style to set the HTML background color based on our theme in app.css --}}
        <style>
            html {
                background-color: oklch(1 0 0);
            }

            html.dark {
                background-color: oklch(0.145 0 0);
            }
        </style>

        <title inertia>{{ \App\Core\AppBrandingSettings::load()['app_name'] ?? config('app.name', 'Car4u') }}</title>

        @php
            $faviconUrl = null;
            $tenant = \App\Core\TenantContext::get();
            if ($tenant) {
                $tenant->loadMissing('siteSetting.files');
                $faviconUrl = \App\Models\TenantSiteSetting::normalize($tenant->siteSetting)['favicon_url'] ?? null;
            }
            if (!$faviconUrl) {
                $faviconUrl = \App\Core\AppBrandingSettings::load()['favicon_url'] ?? null;
            }
            if (!$faviconUrl) {
                $faviconUrl = '/favicon.ico';
            }
        @endphp
        <link rel="icon" href="{{ $faviconUrl }}" sizes="any">

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600" rel="stylesheet" />

        @vite('resources/js/app.ts')
        @inertiaHead
    </head>
    <body class="font-sans antialiased">
        @inertia
    </body>
</html>
