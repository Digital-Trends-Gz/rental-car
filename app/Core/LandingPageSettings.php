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
        return ['navigation', 'hero', 'features_section', 'getting_started', 'mobile_apps_section', 'plans_section', 'faq_section', 'contact_section', 'footer'];
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
                    ['label' => 'Application', 'href' => '#application'],
                    ['label' => 'Clients', 'href' => '#clients'],
                    ['label' => 'Plans', 'href' => '#pricing'],
                    ['label' => 'Contact', 'href' => '#contact'],
                ],
            ],
            'hero' => [
                'enabled' => true,
                'title' => 'Automate your workflows.',
                'description' => 'Streamline replaces scattered tools with one platform that automates repetitive tasks.',
                'features' => [
                    'Bank-level security',
                    '5-min setup',
                    'Cancel anytime',
                ],
                'image_url' => '',
                'localized_images' => array_fill_keys($supportedLocales, ''),
            ],
            'cars_section' => [
                'enabled' => true,
            ],
            'features_section' => [
                'enabled' => true,
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
                'enabled' => true,
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
            'mobile_apps_section' => [
                'enabled' => true,
                'eyebrow' => 'Mobile apps',
                'title' => 'Three apps. One connected platform.',
                'description' => 'A tailored mobile experience for every role in your rental business, built to work seamlessly together.',
                'ios_label' => 'iOS',
                'android_label' => 'Android',
                'apps' => [
                    [
                        'title' => 'Client App',
                        'subtitle' => 'For your customers',
                        'description' => 'Browse the fleet, book cars in seconds, and manage rentals from their pocket.',
                        'image_url' => '',
                        'icon_url' => '',
                        'app_store_url' => '',
                        'google_play_url' => '',
                        'features' => [
                            'Instant booking',
                            'Live availability',
                            'Trip history',
                        ],
                    ],
                    [
                        'title' => 'Employee App',
                        'subtitle' => 'For your team',
                        'description' => 'Assign tasks, handle handovers, and track daily operations without leaving the lot.',
                        'image_url' => '',
                        'icon_url' => '',
                        'app_store_url' => '',
                        'google_play_url' => '',
                        'features' => [
                            'Task assignments',
                            'Vehicle inspections',
                            'Shift handovers',
                        ],
                    ],
                    [
                        'title' => 'Tenant App',
                        'subtitle' => 'For fleet owners',
                        'description' => 'Real-time analytics, revenue insights, and full control over your entire fleet.',
                        'image_url' => '',
                        'icon_url' => '',
                        'app_store_url' => '',
                        'google_play_url' => '',
                        'features' => [
                            'Revenue analytics',
                            'Fleet overview',
                            'Multi-branch control',
                        ],
                    ],
                ],
            ],
            'clients_section' => [
                'enabled' => true,
            ],
            'plans_section' => [
                'enabled' => true,
                'title' => 'Simple, transparent pricing',
                'description' => 'Choose the plan that fits your team.',
            ],
            'faq_section' => [
                'enabled' => true,
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
            'contact_section' => [
                'enabled' => true,
                'title' => 'Contact form',
                'description' => 'Send us a note and our team will follow up by email.',
                'form_title' => 'Tell us what you need',
                'name_label' => 'Name',
                'name_placeholder' => 'Your name',
                'email_label' => 'Email',
                'email_placeholder' => 'you@example.com',
                'subject_label' => 'Subject',
                'subject_placeholder' => 'How can we help?',
                'message_label' => 'Message',
                'message_placeholder' => 'Tell us a bit about your fleet or the feature you want to launch.',
                'submit_label' => 'Send message',
                'sending_label' => 'Sending...',
                'success_message' => 'Thanks. We received your message and will review it shortly.',
                'error_message' => 'Please check the form and try again.',
                'direct_title' => 'Direct contact',
                'direct_email_label' => 'Email',
                'direct_email' => 'info@car4u.net',
                'direct_phone_label' => 'Phone',
                'direct_phone' => '+1 (555) 123-4567',
                'response_time_label' => 'Response time',
                'response_time' => 'Within one business day',
                'quick_links_title' => 'Quick links',
                'quick_links' => [
                    ['label' => 'Browse tenant cars', 'href' => '#cars'],
                    ['label' => 'View plans', 'href' => '#pricing'],
                    ['label' => 'Read the FAQ', 'href' => '#faq'],
                ],
            ],
            'footer' => [
                'enabled' => true,
                'title' => 'Ready to streamline your workflow?',
                'description' => 'Join teams who already save hours every week.',
                'copyright_text' => 'All rights reserved.',
                'show_social_links' => true,
                'show_app_buttons' => true,
                'android_label' => 'Android',
                'android_url' => '',
                'ios_label' => 'iOS',
                'ios_url' => '',
                'social_links' => [
                    ['label' => 'Facebook', 'platform' => 'facebook', 'href' => ''],
                    ['label' => 'Instagram', 'platform' => 'instagram', 'href' => ''],
                    ['label' => 'LinkedIn', 'platform' => 'linkedin', 'href' => ''],
                ],
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
        $data = is_array($data) ? $data : [];
        $settings = array_replace_recursive(self::defaults(), $data);

        foreach (self::replaceableListPaths() as $path) {
            $value = data_get($data, $path);
            if (is_array($value)) {
                data_set($settings, $path, $value);
            }
        }

        $settings['hero']['enabled'] = (bool) ($settings['hero']['enabled'] ?? true);
        $settings['hero']['features'] = self::normalizeStringList($settings['hero']['features'] ?? []);
        $settings['hero']['localized_images'] = self::normalizeLocalizedImages($settings['hero']['localized_images'] ?? []);

        $settings['cars_section']['enabled'] = (bool) ($settings['cars_section']['enabled'] ?? true);
        $settings['features_section']['enabled'] = (bool) ($settings['features_section']['enabled'] ?? true);
        $settings['features_section']['cards'] = self::normalizeCards($settings['features_section']['cards'] ?? []);
        $settings['getting_started']['enabled'] = (bool) ($settings['getting_started']['enabled'] ?? true);
        $settings['getting_started']['items'] = self::normalizeStepItems($settings['getting_started']['items'] ?? []);
        $settings['mobile_apps_section']['enabled'] = (bool) ($settings['mobile_apps_section']['enabled'] ?? true);
        $settings['mobile_apps_section']['apps'] = self::normalizeAppCards($settings['mobile_apps_section']['apps'] ?? []);
        $settings['navigation']['links'] = self::ensureApplicationNavigationLink($settings);
        $settings['clients_section']['enabled'] = (bool) ($settings['clients_section']['enabled'] ?? true);
        $settings['plans_section']['enabled'] = (bool) ($settings['plans_section']['enabled'] ?? true);
        $settings['faq_section']['enabled'] = (bool) ($settings['faq_section']['enabled'] ?? true);
        $settings['faq_section']['items'] = self::normalizeFaqItems($settings['faq_section']['items'] ?? []);
        $settings['contact_section']['enabled'] = (bool) ($settings['contact_section']['enabled'] ?? true);
        $settings['footer']['enabled'] = (bool) ($settings['footer']['enabled'] ?? true);
        $settings['footer']['show_social_links'] = (bool) ($settings['footer']['show_social_links'] ?? true);
        $settings['footer']['show_app_buttons'] = (bool) ($settings['footer']['show_app_buttons'] ?? true);
        $settings['footer']['social_links'] = self::normalizeSocialLinks($settings['footer']['social_links'] ?? []);
        $settings['enabled_locales'] = self::normalizeEnabledLocales($settings['enabled_locales'] ?? []);
        $settings['translations'] = self::normalizeTranslations($settings['translations'] ?? []);

        return $settings;
    }

    /**
     * Numeric lists must replace existing values instead of merging by index.
     *
     * @return array<int, string>
     */
    public static function replaceableListPaths(): array
    {
        return [
            'hero.features',
            'navigation.links',
            'features_section.cards',
            'getting_started.items',
            'mobile_apps_section.apps',
            'faq_section.items',
            'contact_section.quick_links',
            'footer.social_links',
        ];
    }

    private static function normalizeSocialLinks(mixed $items): array
    {
        $items = is_array($items) ? $items : [];

        return array_values(array_map(static function (mixed $item): array {
            $item = is_array($item) ? $item : [];

            return [
                'label' => trim((string) ($item['label'] ?? '')),
                'platform' => trim((string) ($item['platform'] ?? '')),
                'href' => trim((string) ($item['href'] ?? '')),
            ];
        }, $items));
    }

    public static function localize(array $settings, ?string $locale): array
    {
        $normalized = self::normalize($settings);
        $locale = trim((string) ($locale ?? ''));

        if ($locale === '' || !in_array($locale, $normalized['enabled_locales'] ?? [], true)) {
            return $normalized;
        }

        $defaultLocale = in_array('en', $normalized['enabled_locales'] ?? [], true)
            ? 'en'
            : (string) (($normalized['enabled_locales'] ?? [])[0] ?? 'en');
        $applyLocalizedHeroImage = static function (array $settings) use ($normalized, $locale, $defaultLocale): array {
            if ($locale === $defaultLocale) {
                return $settings;
            }

            $localizedImageUrl = trim((string) data_get($normalized, "hero.localized_images.$locale", ''));

            if ($localizedImageUrl !== '') {
                data_set($settings, 'hero.image_url', $localizedImageUrl);
            }

            return $settings;
        };

        $overrides = data_get($normalized, "translations.$locale", []);
        if (!is_array($overrides) || empty($overrides)) {
            return $applyLocalizedHeroImage($normalized);
        }

        $localized = array_replace_recursive($normalized, $overrides);

        return $applyLocalizedHeroImage($localized);
    }

    private static function normalizeLocalizedImages(mixed $value): array
    {
        $supported = self::supportedLocaleKeys();
        $images = is_array($value) ? $value : [];
        $normalized = [];

        foreach ($supported as $locale) {
            $normalized[$locale] = trim((string) ($images[$locale] ?? ''));
        }

        return $normalized;
    }

    private static function ensureApplicationNavigationLink(array $settings): array
    {
        $links = $settings['navigation']['links'] ?? [];

        if (!is_array($links)) {
            return [];
        }

        if (($settings['mobile_apps_section']['enabled'] ?? true) === false) {
            return array_values($links);
        }

        foreach ($links as $link) {
            if (is_array($link) && ($link['href'] ?? null) === '#application') {
                return array_values($links);
            }
        }

        $applicationLink = ['label' => 'Application', 'href' => '#application'];
        $featuresIndex = null;

        foreach ($links as $index => $link) {
            if (is_array($link) && ($link['href'] ?? null) === '#features') {
                $featuresIndex = $index;
                break;
            }
        }

        if ($featuresIndex === null) {
            $links[] = $applicationLink;
        } else {
            array_splice($links, $featuresIndex + 1, 0, [$applicationLink]);
        }

        return array_values($links);
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

    private static function normalizeAppCards(mixed $items): array
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
            $subtitle = trim((string) ($item['subtitle'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));
            $imageUrl = trim((string) ($item['image_url'] ?? ''));
            $iconUrl = trim((string) ($item['icon_url'] ?? ''));
            $appStoreUrl = trim((string) ($item['app_store_url'] ?? ''));
            $googlePlayUrl = trim((string) ($item['google_play_url'] ?? ''));
            $features = self::normalizeStringList($item['features'] ?? []);

            if ($title === '' && $subtitle === '' && $description === '' && $imageUrl === '' && $appStoreUrl === '' && $googlePlayUrl === '' && empty($features)) {
                continue;
            }

            $cards[] = [
                'title' => $title,
                'subtitle' => $subtitle,
                'description' => $description,
                'image_url' => $imageUrl,
                'icon_url' => $iconUrl,
                'app_store_url' => $appStoreUrl,
                'google_play_url' => $googlePlayUrl,
                'features' => $features,
            ];
        }

        return $cards;
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
