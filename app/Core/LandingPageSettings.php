<?php

namespace App\Core;

class LandingPageSettings
{
    public const KEY = 'landing_page';

    /**
     * @return array<int, string>
     */
    public static function supportedLocaleKeys(): array
    {
        $supported = array_values((array) config('app.available_locales', ['en']));
        $supported = array_values(array_unique(array_map('strval', $supported)));

        return empty($supported) ? ['en'] : $supported;
    }

    /**
     * @return array<int, string>
     */
    public static function contentKeys(): array
    {
        return ['navigation', 'hero', 'features_section', 'getting_started', 'plans_section', 'faq_section', 'footer'];
    }

    /**
     * Default landing page settings used when database value is empty.
     */
    public static function defaults(): array
    {
        $supportedLocales = self::supportedLocaleKeys();

        return [
            'navigation' => [
                'cta_label' => 'Start Free Trial',
                'links' => [
                    ['label' => 'Cars', 'href' => '#cars'],
                    ['label' => 'Features', 'href' => '#features'],
                    ['label' => 'Start in Minutes', 'href' => '#how-it-works'],
                    ['label' => 'Clients', 'href' => '#clients'],
                    ['label' => 'Plans', 'href' => '#pricing'],
                    ['label' => 'FAQ', 'href' => '#faq'],
                    ['label' => 'Contact', 'href' => '#contact'],
                ],
            ],
            'hero' => [
                'title' => 'Automate your workflows.',
                'description' => 'Streamline replaces scattered tools with one platform that automates repetitive tasks.',
                'features' => [
                    'Bank-level security',
                    '5-min setup',
                    'Cancel anytime',
                ],
                'image_url' => '',
            ],
            'features_section' => [
                'title' => 'Everything you need to move faster',
                'description' => 'Powerful features that replace your entire tool stack with one intuitive platform.',
                'cards' => [
                    [
                        'title' => 'Visual Workflow Builder',
                        'image_url' => '',
                        'content' => 'Drag-and-drop automations that connect your tools.',
                    ],
                    [
                        'title' => 'AI-Powered Suggestions',
                        'image_url' => '',
                        'content' => 'Smart recommendations that optimize your workflows.',
                    ],
                    [
                        'title' => 'Real-Time Analytics',
                        'image_url' => '',
                        'content' => 'Live dashboards to track performance instantly.',
                    ],
                ],
            ],
            'getting_started' => [
                'title' => 'Up and running in minutes',
                'description' => 'Three simple steps to launch your fleet operations quickly.',
                'items' => [
                    [
                        'title' => 'Connect your account',
                        'description' => 'Link your tenant and bring your cars online.',
                    ],
                    [
                        'title' => 'Publish your fleet',
                        'description' => 'Add cars, pricing, and availability in one place.',
                    ],
                    [
                        'title' => 'Start receiving bookings',
                        'description' => 'Track reservations and revenue from the dashboard.',
                    ],
                ],
            ],
            'plans_section' => [
                'title' => 'Simple, transparent pricing',
                'description' => 'Choose the plan that fits your team.',
            ],
            'faq_section' => [
                'title' => 'Frequently asked questions',
                'description' => 'Everything you need to know before getting started.',
                'items' => [
                    [
                        'question' => 'Is there a free trial?',
                        'answer' => 'Yes. Every plan includes a 14-day free trial.',
                    ],
                    [
                        'question' => 'Can I cancel anytime?',
                        'answer' => 'Yes. There are no long-term contracts.',
                    ],
                ],
            ],
            'footer' => [
                'title' => 'Ready to streamline your workflow?',
                'description' => 'Join teams who already save hours every week.',
            ],
            'enabled_locales' => $supportedLocales,
            'translations' => array_fill_keys($supportedLocales, []),
        ];
    }

    /**
     * Normalize incoming data to always match expected structure.
     */
    public static function normalize(?array $data): array
    {
        $settings = array_replace_recursive(self::defaults(), is_array($data) ? $data : []);

        $settings['hero']['features'] = self::normalizeStringList($settings['hero']['features'] ?? []);

        $settings['features_section']['cards'] = self::normalizeCards($settings['features_section']['cards'] ?? []);
        $settings['getting_started']['items'] = self::normalizeStepItems($settings['getting_started']['items'] ?? []);
        $settings['faq_section']['items'] = self::normalizeFaqItems($settings['faq_section']['items'] ?? []);
        $settings['enabled_locales'] = self::normalizeEnabledLocales($settings['enabled_locales'] ?? []);
        $settings['translations'] = self::normalizeTranslations($settings['translations'] ?? []);

        return $settings;
    }

    public static function localize(array $settings, ?string $locale): array
    {
        $normalized = self::normalize($settings);
        $locale = trim((string) ($locale ?? ''));

        if ($locale === '' || !in_array($locale, $normalized['enabled_locales'] ?? [], true)) {
            return $normalized;
        }

        $overrides = data_get($normalized, "translations.$locale", []);
        if (!is_array($overrides) || empty($overrides)) {
            return $normalized;
        }

        return array_replace_recursive($normalized, $overrides);
    }

    private static function normalizeStringList(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        return array_values(array_filter(array_map(static function ($item) {
            return trim((string) $item);
        }, $items), static fn ($item) => $item !== ''));
    }

    private static function normalizeCards(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $cards = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $content = trim((string) ($item['content'] ?? ''));
            $imageUrl = trim((string) ($item['image_url'] ?? ''));

            if ($title === '' && $content === '' && $imageUrl === '') {
                continue;
            }

            $cards[] = [
                'title' => $title,
                'image_url' => $imageUrl,
                'content' => $content,
            ];
        }

        return $cards;
    }

    private static function normalizeStepItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $steps = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $title = trim((string) ($item['title'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));

            if ($title === '' && $description === '') {
                continue;
            }

            $steps[] = [
                'title' => $title,
                'description' => $description,
            ];
        }

        return $steps;
    }

    private static function normalizeFaqItems(mixed $items): array
    {
        if (!is_array($items)) {
            return [];
        }

        $faqs = [];

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }

            $question = trim((string) ($item['question'] ?? ''));
            $answer = trim((string) ($item['answer'] ?? ''));

            if ($question === '' && $answer === '') {
                continue;
            }

            $faqs[] = [
                'question' => $question,
                'answer' => $answer,
            ];
        }

        return $faqs;
    }

    private static function normalizeEnabledLocales(mixed $value): array
    {
        $supported = self::supportedLocaleKeys();
        $enabled = is_array($value) ? array_map('strval', $value) : [];
        $enabled = array_values(array_unique(array_intersect($supported, $enabled)));

        return empty($enabled) ? $supported : $enabled;
    }

    private static function normalizeTranslations(mixed $translations): array
    {
        $supported = self::supportedLocaleKeys();
        $translations = is_array($translations) ? $translations : [];
        $normalized = [];

        foreach ($supported as $locale) {
            $normalized[$locale] = self::pruneTranslationTree($translations[$locale] ?? []);
        }

        return $normalized;
    }

    private static function pruneTranslationTree(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        $result = [];

        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $nested = self::pruneTranslationTree($item);
                if (!empty($nested)) {
                    $result[$key] = $nested;
                }

                continue;
            }

            $text = trim((string) ($item ?? ''));
            if ($text !== '') {
                $result[$key] = $text;
            }
        }

        return $result;
    }
}
