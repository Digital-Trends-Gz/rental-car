<?php

namespace App\Support;

use App\Core\LandingPageSettings;
use App\Models\SiteSetting;
use Illuminate\Contracts\Translation\Loader;
use Illuminate\Support\Facades\Schema;
use Throwable;

class DatabaseTranslationLoader implements Loader
{
    public function __construct(
        private readonly Loader $loader,
    ) {
    }

    public function load($locale, $group, $namespace = null): array
    {
        $lines = $this->loader->load($locale, $group, $namespace);

        if ($namespace !== null && $namespace !== '*') {
            return $lines;
        }

        $overrides = $this->globalOverrides((string) $locale, (string) $group);

        return !empty($overrides)
            ? array_replace_recursive($lines, $overrides)
            : $lines;
    }

    public function addNamespace($namespace, $hint): void
    {
        $this->loader->addNamespace($namespace, $hint);
    }

    public function addJsonPath($path): void
    {
        $this->loader->addJsonPath($path);
    }

    public function namespaces(): array
    {
        return method_exists($this->loader, 'namespaces')
            ? $this->loader->namespaces()
            : [];
    }

    private function globalOverrides(string $locale, string $group): array
    {
        try {
            if (!Schema::hasTable('site_settings')) {
                return [];
            }

            $stored = SiteSetting::query()
                ->where('key', LandingPageSettings::KEY)
                ->value('value');

            $settings = LandingPageSettings::normalize(is_array($stored) ? $stored : null);
            $overrides = data_get($settings, "translations.{$locale}.{$group}", []);

            return is_array($overrides) ? $overrides : [];
        } catch (Throwable) {
            return [];
        }
    }
}
