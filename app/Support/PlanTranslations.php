<?php

namespace App\Support;

use App\Core\LandingPageSettings;
use App\Models\Plan;
use App\Models\SiteSetting;
use Illuminate\Support\Collection;

class PlanTranslations
{
    public const ROOT_KEY = 'subscription_plans';

    private static ?array $settingsCache = null;

    public static function defaultTranslationTree(): array
    {
        $plans = Plan::query()
            ->orderBy('monthly_price')
            ->orderBy('id')
            ->get(['id', 'name', 'description', 'features']);

        $tree = [
            self::ROOT_KEY => [],
        ];

        foreach ($plans as $plan) {
            $tree[self::ROOT_KEY][(string) $plan->id] = [
                'name' => (string) $plan->name,
                'description' => (string) ($plan->description ?? ''),
                'features' => array_values(array_map('strval', $plan->features ?? [])),
            ];
        }

        return $tree;
    }

    public static function localizeCollection(Collection $plans, ?string $locale = null): Collection
    {
        $settings = self::landingSettings();

        return $plans->map(fn (Plan $plan) => self::localizePlan($plan, $locale, $settings));
    }

    public static function localizePlan(Plan $plan, ?string $locale = null, ?array $settings = null): Plan
    {
        $locale = trim((string) ($locale ?? app()->getLocale()));
        if ($locale === '') {
            return $plan;
        }

        $settings ??= self::landingSettings();
        $translationRoot = self::ROOT_KEY.'.'.$plan->getKey();
        $translations = data_get($settings, "translations.{$locale}.{$translationRoot}", []);

        if (!is_array($translations) || empty($translations)) {
            return $plan;
        }

        $name = trim((string) data_get($translations, 'name', ''));
        if ($name !== '') {
            $plan->setAttribute('name', $name);
        }

        $description = trim((string) data_get($translations, 'description', ''));
        if ($description !== '') {
            $plan->setAttribute('description', $description);
        }

        $translatedFeatures = data_get($translations, 'features', []);
        if (is_array($translatedFeatures)) {
            $features = array_values(array_map('strval', $plan->features ?? []));

            foreach ($features as $index => $feature) {
                $translatedFeature = trim((string) ($translatedFeatures[$index] ?? ''));
                if ($translatedFeature !== '') {
                    $features[$index] = $translatedFeature;
                }
            }

            $plan->setAttribute('features', $features);
        }

        return $plan;
    }

    private static function landingSettings(): array
    {
        if (self::$settingsCache !== null) {
            return self::$settingsCache;
        }

        $stored = SiteSetting::query()
            ->where('key', LandingPageSettings::KEY)
            ->value('value');

        return self::$settingsCache = LandingPageSettings::normalize(is_array($stored) ? $stored : null);
    }
}
