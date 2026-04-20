<?php

namespace App\Core;

class LandingPageSettings
{
    public const KEY = 'landing_page';

    /**
     * Default landing page settings used when database value is empty.
     */
    public static function defaults(): array
    {
        return [
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

        return $settings;
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
}
