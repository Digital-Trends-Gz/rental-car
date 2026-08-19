<?php

namespace App\Providers;

use App\Auth\TenantAwareUserProvider;
use App\Core\AiProviderSettings;
use App\Core\LocalizationSettings;
use App\Core\SocialLoginSettings;
use App\Core\StripeSettings;
use App\Models\SiteSetting;
use App\Support\DatabaseTranslationLoader;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Sanctum\Sanctum;
use Throwable;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->extend('translation.loader', function (Loader $loader): Loader {
            return new DatabaseTranslationLoader($loader);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Password::defaults(static fn () => Password::min(8)->mixedCase()->numbers());

        // Avoid stale/locked public/hot forcing broken dev-server URLs in production-like runs.
        Vite::useHotFile(storage_path('framework/vite.hot'));
        Vite::createAssetPathsUsing(static fn (string $path) => '/'.ltrim($path, '/'));

        if ($this->app->environment('production', 'staging') || str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        $this->applyStripeSettingsFromDatabase();
        $this->applyOpenAiSettingsFromDatabase();
        $this->applyLocalizationSettingsFromDatabase();
        $this->applySocialLoginSettingsFromDatabase();

        // Register a tenant-aware user provider that ignores tenant scopes during auth lookups.
        Auth::provider('eloquent-tenant-aware', function ($app, array $config) {
            return new TenantAwareUserProvider(
                $app['hash'],
                $config['model'] ?? \App\Models\User::class
            );
        });

        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);
    }

    private function applyStripeSettingsFromDatabase(): void
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return;
            }

            $stored = SiteSetting::query()
                ->where('key', StripeSettings::KEY)
                ->value('value');

            $settings = StripeSettings::normalize(is_array($stored) ? $stored : null);

            config([
                'cashier.key' => $settings['key'] !== '' ? $settings['key'] : config('cashier.key'),
                'cashier.secret' => $settings['secret'] !== '' ? $settings['secret'] : config('cashier.secret'),
                'cashier.webhook.secret' => $settings['webhook_secret'] !== '' ? $settings['webhook_secret'] : config('cashier.webhook.secret'),
                'cashier.webhook.tolerance' => $settings['webhook_tolerance'],
                'cashier.currency' => $settings['currency'],
                'cashier.currency_locale' => $settings['currency_locale'],
                'cashier.path' => $settings['path'],
                'cashier.logger' => $settings['logger'] !== '' ? $settings['logger'] : config('cashier.logger'),
            ]);
        } catch (Throwable) {
            // Do not block app boot if settings table is not ready yet.
        }
    }

    private function applyOpenAiSettingsFromDatabase(): void
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return;
            }

            $settings = AiProviderSettings::load();
            $openAi = $settings['openai'] ?? [];

            config([
                'openai.api_key' => ($openAi['api_key'] ?? '') !== '' ? $openAi['api_key'] : config('openai.api_key'),
                'openai.organization' => ($openAi['organization'] ?? '') !== '' ? $openAi['organization'] : config('openai.organization'),
                'openai.project' => ($openAi['project'] ?? '') !== '' ? $openAi['project'] : config('openai.project'),
                'openai.base_uri' => ($openAi['base_uri'] ?? '') !== '' ? $openAi['base_uri'] : config('openai.base_uri'),
            ]);
        } catch (Throwable) {
            // Do not block app boot if settings table is not ready yet.
        }
    }

    private function applyLocalizationSettingsFromDatabase(): void
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return;
            }

            $settings = LocalizationSettings::load();
            $supportedLocales = LocalizationSettings::toLaravelLocalizationConfig($settings);
            $localeCodes = LocalizationSettings::localeCodes($settings);
            $defaultLocale = LocalizationSettings::defaultLocale($settings);

            config([
                'laravellocalization.supportedLocales' => $supportedLocales,
                'laravellocalization.localesOrder' => $localeCodes,
                'app.available_locales' => $localeCodes,
                'app.locale' => $defaultLocale,
            ]);
        } catch (Throwable) {
            // Do not block app boot if settings are not ready yet.
        }
    }

    private function applySocialLoginSettingsFromDatabase(): void
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return;
            }

            $settings = SocialLoginSettings::load();
            $google = $settings['google'] ?? [];
            $apple = $settings['apple'] ?? [];

            $baseDomain = config('app.url');

            if ($google['enabled']) {
                $googleClientId = ($google['client_id'] ?? '') !== '' ? $google['client_id'] : config('services.google.client_id');
                $googleClientSecret = ($google['client_secret'] ?? '') !== '' ? $google['client_secret'] : config('services.google.client_secret');
                config([
                    'services.google.client_id' => $googleClientId,
                    'services.google.client_secret' => $googleClientSecret,
                    'services.google.redirect' => "{$baseDomain}/auth/google/callback",
                ]);
            }

            if ($apple['enabled']) {
                $appleClientId = ($apple['client_id'] ?? '') !== '' ? $apple['client_id'] : config('services.apple.client_id');
                $appleClientSecret = ($apple['client_secret'] ?? '') !== '' ? $apple['client_secret'] : config('services.apple.client_secret');
                config([
                    'services.apple.client_id' => $appleClientId,
                    'services.apple.client_secret' => $appleClientSecret,
                    'services.apple.redirect' => "{$baseDomain}/auth/apple/callback",
                ]);
            }
        } catch (Throwable) {
            // Do not block app boot if settings are not ready yet.
        }
    }
}
