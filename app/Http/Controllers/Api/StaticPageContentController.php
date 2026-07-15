<?php

namespace App\Http\Controllers\Api;

use App\Core\LocalizationSettings;
use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaticPageContentController extends Controller
{
    private const SETTINGS_KEY = 'main_static_pages_content';

    private const SECTIONS = [
        'support' => [
            'titles' => [
                'en' => 'Support',
                'ar' => 'الدعم',
                'ur' => 'مدد',
            ],
        ],
        'privacy_policy' => [
            'titles' => [
                'en' => 'Privacy Policy',
                'ar' => 'سياسة الخصوصية',
                'ur' => 'رازداری کی پالیسی',
            ],
        ],
        'terms_conditions' => [
            'titles' => [
                'en' => 'Terms and Conditions',
                'ar' => 'الشروط والأحكام',
                'ur' => 'شرائط و ضوابط',
            ],
        ],
        'security_policy' => [
            'titles' => [
                'en' => 'Security Policy',
                'ar' => 'Security Policy',
                'ur' => 'Security Policy',
            ],
        ],
    ];

    public function index(Request $request): JsonResponse
    {
        $localization = LocalizationSettings::load();
        $locale = $this->resolveLocale($request, $localization);
        $settings = $this->settings();

        return response()->json([
            'status' => 'success',
            'message' => 'Static page content fetched successfully.',
            'data' => [
                'locale' => $locale,
                'direction' => $this->localeDirection($locale, $localization),
                'pages' => collect(array_keys(self::SECTIONS))
                    ->map(fn (string $section): array => $this->pagePayload($section, $settings, $locale, $localization))
                    ->values()
                    ->all(),
            ],
            'code' => 200,
        ]);
    }

    public function support(Request $request): JsonResponse
    {
        return $this->show($request, 'support');
    }

    public function privacyPolicy(Request $request): JsonResponse
    {
        return $this->show($request, 'privacy_policy');
    }

    public function termsConditions(Request $request): JsonResponse
    {
        return $this->show($request, 'terms_conditions');
    }

    public function securityPolicy(Request $request): JsonResponse
    {
        return $this->show($request, 'security_policy');
    }

    private function show(Request $request, string $section): JsonResponse
    {
        $localization = LocalizationSettings::load();
        $locale = $this->resolveLocale($request, $localization);

        return response()->json([
            'status' => 'success',
            'message' => 'Static page content fetched successfully.',
            'data' => $this->pagePayload($section, $this->settings(), $locale, $localization),
            'code' => 200,
        ]);
    }

    private function pagePayload(string $section, array $settings, string $locale, array $localization): array
    {
        return [
            'section' => $section,
            'title' => $this->localizedTitle($section, $locale),
            'locale' => $locale,
            'direction' => $this->localeDirection($locale, $localization),
            'content_html' => $this->localizedContent($settings[$section] ?? null, $locale, $localization),
        ];
    }

    private function settings(): array
    {
        $stored = SiteSetting::query()
            ->where('key', self::SETTINGS_KEY)
            ->value('value');

        return is_array($stored) ? $stored : [];
    }

    private function localizedContent(mixed $content, string $locale, array $localization): string
    {
        if (!is_array($content)) {
            return trim((string) ($content ?? ''));
        }

        $defaultLocale = LocalizationSettings::defaultLocale($localization);
        $value = trim((string) ($content[$locale] ?? ''));

        if ($value !== '') {
            return $value;
        }

        $defaultValue = trim((string) ($content[$defaultLocale] ?? ''));

        if ($defaultValue !== '') {
            return $defaultValue;
        }

        foreach ($content as $fallbackValue) {
            $fallbackValue = trim((string) $fallbackValue);
            if ($fallbackValue !== '') {
                return $fallbackValue;
            }
        }

        return '';
    }

    private function localizedTitle(string $section, string $locale): string
    {
        $localizedTitles = [
            'support' => [
                'ar' => 'الدعم',
            ],
            'privacy_policy' => [
                'ar' => 'سياسة الخصوصية',
            ],
            'terms_conditions' => [
                'ar' => 'الشروط والأحكام',
            ],
            'security_policy' => [
                'ar' => 'سياسة الأمان',
            ],
        ];
        $titles = array_replace(
            self::SECTIONS[$section]['titles'] ?? [],
            $localizedTitles[$section] ?? []
        );
        $baseLocale = strtolower(explode('-', $locale)[0] ?? $locale);

        return (string) ($titles[$locale] ?? $titles[$baseLocale] ?? $titles['en'] ?? $section);
    }

    private function localeDirection(string $locale, array $localization): string
    {
        foreach (($localization['locales'] ?? []) as $row) {
            if (($row['code'] ?? null) === $locale && in_array(($row['direction'] ?? null), ['ltr', 'rtl'], true)) {
                return (string) $row['direction'];
            }
        }

        return str_starts_with($locale, 'ar') || str_starts_with($locale, 'ur') ? 'rtl' : 'ltr';
    }

    private function resolveLocale(Request $request, array $localization): string
    {
        $localeCodes = LocalizationSettings::localeCodes($localization);
        $defaultLocale = LocalizationSettings::defaultLocale($localization);
        $header = trim((string) $request->header('Accept-Language', ''));

        if ($header === '') {
            return $defaultLocale;
        }

        foreach ($this->acceptedLocales($header) as $candidate) {
            if (in_array($candidate, $localeCodes, true)) {
                return $candidate;
            }

            $baseCandidate = strtolower(explode('-', $candidate)[0] ?? $candidate);
            foreach ($localeCodes as $localeCode) {
                if (strtolower(explode('-', $localeCode)[0] ?? $localeCode) === $baseCandidate) {
                    return $localeCode;
                }
            }
        }

        return $defaultLocale;
    }

    /**
     * @return list<string>
     */
    private function acceptedLocales(string $header): array
    {
        $locales = [];

        foreach (explode(',', $header) as $part) {
            $locale = trim(explode(';', $part)[0] ?? '');
            $locale = $this->normalizeLocaleCode($locale);

            if ($locale !== '') {
                $locales[] = $locale;
            }
        }

        return array_values(array_unique($locales));
    }

    private function normalizeLocaleCode(string $code): string
    {
        $code = trim(str_replace('_', '-', $code));

        if ($code === '') {
            return '';
        }

        $parts = array_values(array_filter(explode('-', $code), fn (string $part): bool => $part !== ''));

        if (empty($parts)) {
            return '';
        }

        $language = strtolower($parts[0]);

        if (!preg_match('/^[a-z]{2,3}$/', $language)) {
            return '';
        }

        if (count($parts) === 1) {
            return $language;
        }

        $region = strtoupper($parts[1]);

        return preg_match('/^[A-Z]{2}$/', $region) ? $language.'-'.$region : $language;
    }
}
