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
        return ['navigation', 'locale_switcher', 'hero', 'features_section', 'getting_started', 'mobile_apps_section', 'applications_page', 'plans_comparison_page', 'plans_section', 'faq_section', 'contact_section', 'footer'];
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
                    ['label' => 'Application', 'href' => '/applications'],
                    ['label' => 'Clients', 'href' => '#clients'],
                    ['label' => 'Plans', 'href' => '/plans'],
                    ['label' => 'Contact', 'href' => '#contact'],
                ],
            ],
            'locale_switcher' => [
                'language_names' => [
                    'en' => 'English',
                    'ar' => 'Arabic',
                    'ur' => 'Urdu',
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
                'fleet_button_icon_url' => '',
            ],
            'features_section' => [
                'enabled' => true,
                'title' => 'Everything you need to move faster',
                'description' => 'Powerful features that replace your entire tool stack with one intuitive platform.',
                'cards' => [
                    [
                        'title' => 'Visual Workflow Builder',
                        'image_url' => '',
                        'icon_background_color' => '#f3f4f6',
                        'content' => 'Drag-and-drop automations that connect your tools.',
                    ],
                    [
                        'title' => 'AI-Powered Suggestions',
                        'image_url' => '',
                        'icon_background_color' => '#f3f4f6',
                        'content' => 'Smart recommendations that optimize your workflows.',
                    ],
                    [
                        'title' => 'Real-Time Analytics',
                        'image_url' => '',
                        'icon_background_color' => '#f3f4f6',
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
                        'image_url' => '',
                        'icon_background_color' => '#f3f4f6',
                        'description' => 'Link your tenant and bring your cars online.',
                    ],
                    [
                        'title' => 'Publish your fleet',
                        'image_url' => '',
                        'icon_background_color' => '#f3f4f6',
                        'description' => 'Add cars, pricing, and availability in one place.',
                    ],
                    [
                        'title' => 'Start receiving bookings',
                        'image_url' => '',
                        'icon_background_color' => '#f3f4f6',
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
            'applications_page' => [
                'enabled' => true,
                'hero_enabled' => true,
                'hero_eyebrow' => 'Car4u mobile ecosystem',
                'hero_title' => 'Three experiences. One rental platform.',
                'hero_highlight' => 'One rental platform.',
                'hero_description' => 'Give owners full business visibility, help employees complete daily operations, and offer renters a smooth mobile journey from vehicle discovery to rental follow-up.',
                'hero_image_url' => '',
                'primary_cta_label' => 'Explore the apps',
                'secondary_cta_label' => 'Compare experiences',
                'owner_employee_note' => 'Owner and employee experiences are delivered through the same management application with role-based permissions.',
                'apps_enabled' => true,
                'section_eyebrow' => 'Applications',
                'section_title' => 'Designed around every role',
                'section_description' => 'Each experience focuses on the tasks, information, and decisions that matter most to its user.',
                'store_ios_label' => 'App Store',
                'store_ios_caption' => 'Download on the',
                'store_android_label' => 'Google Play',
                'store_android_caption' => 'Get it on',
                'roles' => [
                    [
                        'enabled' => true,
                        'key' => 'owner',
                        'label' => 'Owner experience',
                        'title' => 'See the entire business from your phone',
                        'description' => 'The owner workspace brings together performance, fleet activity, branches, reservations, contracts, payments, and team oversight in one clear mobile dashboard.',
                        'image_url' => '',
                        'note_title' => 'Part of the management app:',
                        'note' => 'owners download the same application as employees. Their account automatically unlocks owner-level dashboards and controls.',
                        'floating_one_title' => 'Live revenue',
                        'floating_one_text' => 'Across all branches',
                        'floating_two_title' => 'Full control',
                        'floating_two_text' => 'Fleet, branches and team',
                        'screen_label' => 'Good morning',
                        'screen_title' => 'Owner dashboard',
                        'screen_stat_label' => 'Revenue this month',
                        'screen_stat_value' => '$42,860',
                        'features' => [
                            'Revenue and performance insights',
                            'Fleet availability and utilization',
                            'Reservations and contract activity',
                            'Multi-branch monitoring',
                            'Employee and permission management',
                            'Alerts and approval requests',
                        ],
                    ],
                    [
                        'enabled' => true,
                        'key' => 'employee',
                        'label' => 'Employee experience',
                        'title' => 'Complete daily rental operations anywhere',
                        'description' => 'The employee workspace is designed for fast field execution, from vehicle preparation and customer handover to inspections, returns, photos, payments, and contract updates.',
                        'image_url' => '',
                        'note_title' => 'Same management app:',
                        'note' => 'the employee sees only the tools and information permitted by their assigned role and branch.',
                        'floating_one_title' => 'Daily workflow',
                        'floating_one_text' => 'Tasks in one place',
                        'floating_two_title' => 'Role-based access',
                        'floating_two_text' => 'Only allowed actions',
                        'screen_label' => 'Employee workspace',
                        'screen_title' => 'Today operations',
                        'screen_stat_label' => 'Active tasks',
                        'screen_stat_value' => '12',
                        'features' => [
                            'Daily task and booking management',
                            'Vehicle handover and return flow',
                            'Digital vehicle inspections',
                            'Damage and condition photos',
                            'Payments and expense entries',
                            'Contracts and rental updates',
                        ],
                    ],
                    [
                        'enabled' => true,
                        'key' => 'renter',
                        'label' => 'Renter application',
                        'title' => 'A complete rental journey for your customers',
                        'description' => 'The renter app is a fully separate application with its own design, navigation, and services. It gives customers a simple way to discover cars, book, submit documents, pay, and follow their rental.',
                        'image_url' => '',
                        'note_title' => 'Independent customer app:',
                        'note' => 'this application has a separate APK and a completely different experience from the management app.',
                        'floating_one_title' => 'Easy booking',
                        'floating_one_text' => 'Browse and reserve',
                        'floating_two_title' => 'Separate app',
                        'floating_two_text' => 'Customer-first experience',
                        'screen_label' => 'Renter app',
                        'screen_title' => 'Choose your vehicle',
                        'screen_stat_label' => 'Available cars',
                        'screen_stat_value' => '120+',
                        'features' => [
                            'Browse available vehicles',
                            'Search and filter by category',
                            'Create and track bookings',
                            'Upload required documents',
                            'Payments and rental extension',
                            'Offers, notifications, and support',
                        ],
                    ],
                ],
                'comparison_enabled' => true,
                'compare_title' => 'Three experiences, two applications',
                'compare_description' => 'The owner and employee workspaces share one secure management application, while renters use a fully independent customer application.',
                'compare_badge' => 'Role-based access',
                'comparison' => [
                    [
                        'title' => 'Owner',
                        'description' => 'Strategic visibility and control.',
                        'items' => [
                            'Management application',
                            'Owner-level permissions',
                            'Business-wide dashboards',
                            'Branches, team, and approvals',
                        ],
                    ],
                    [
                        'title' => 'Employee',
                        'description' => 'Operational execution and field tasks.',
                        'items' => [
                            'Same management application',
                            'Role and branch permissions',
                            'Handover, return, and inspection',
                            'Contracts, photos, and payments',
                        ],
                    ],
                    [
                        'title' => 'Renter',
                        'description' => 'A customer-first rental journey.',
                        'items' => [
                            'Separate customer application',
                            'Independent design and APK',
                            'Browse, book, pay, and track',
                            'Documents, offers, and support',
                        ],
                    ],
                ],
                'ecosystem_enabled' => true,
                'ecosystem_title' => 'Connected to the same rental ecosystem',
                'ecosystem_description' => 'Every reservation, vehicle update, contract, payment, and customer action stays synchronized with the Car4u SaaS platform.',
                'ecosystem_cta_label' => 'Start with Car4u',
            ],
            'plans_comparison_page' => [
                'enabled' => true,
                'hero_enabled' => true,
                'summary_enabled' => true,
                'comparison_enabled' => true,
                'addons_enabled' => true,
                'policy_enabled' => true,
                'footer_enabled' => true,
                'hero_badge' => 'SaaS Pricing Plans for Car Rental Offices',
                'hero_title' => 'Subscription plans comparison for rental offices',
                'hero_description' => 'Clear subscription plans for car rental offices based on office size, number of cars, users, bookings, and required features. Current prices include a limited launch discount.',
                'monthly_label' => 'monthly',
                'current_price_label' => 'Now',
                'official_price_label' => 'Official price later',
                'launch_discount_label' => 'Launch discount around 25%',
                'most_value_label' => 'Most Value',
                'custom_price_label' => 'Custom',
                'custom_price_caption' => 'Pricing depends on contract and company size',
                'custom_price_badge' => 'Custom solution for companies',
                'unlimited_label' => 'Unlimited',
                'not_available_label' => 'No',
                'included_label' => 'Yes',
                'table_title' => 'Detailed comparison between plans',
                'table_description' => 'The table below explains the main differences between plans and the features available for each office size.',
                'table_note' => 'Launch Offer: save up to 25%',
                'feature_column_label' => 'Feature',
                'comparison_sections' => [
                    [
                        'title' => 'Basic limits',
                        'rows' => [
                            ['label' => 'Number of cars', 'values' => ['Up to 10', 'Up to 30', 'Up to 100', 'Unlimited / by contract'], 'tone' => 'custom'],
                            ['label' => 'Number of users', 'values' => ['Up to 2', 'Up to 5', 'Up to 15', 'Unlimited / by contract'], 'tone' => 'custom'],
                            ['label' => 'Monthly bookings', 'values' => ['Up to 100', 'Up to 500', 'Up to 2,000', 'Unlimited'], 'tone' => 'custom'],
                            ['label' => 'Branches', 'values' => ['One branch', 'One branch', 'Up to 3 branches', 'Multiple branches'], 'tone' => 'custom'],
                        ],
                    ],
                    [
                        'title' => 'Operations management',
                        'rows' => [
                            ['label' => 'Car management', 'values' => ['Yes', 'Yes', 'Yes', 'Yes'], 'tone' => 'yes'],
                            ['label' => 'Booking management', 'values' => ['Yes', 'Yes', 'Yes', 'Yes'], 'tone' => 'yes'],
                            ['label' => 'Customer management', 'values' => ['Yes', 'Yes', 'Yes', 'Yes'], 'tone' => 'yes'],
                            ['label' => 'Additional services management', 'values' => ['No', 'Yes', 'Yes', 'Yes'], 'tone' => 'mixed'],
                            ['label' => 'Handover and return workflow', 'values' => ['Basic', 'Yes', 'Yes', 'Yes'], 'tone' => 'mixed'],
                        ],
                    ],
                    [
                        'title' => 'Revenue and payments',
                        'rows' => [
                            ['label' => 'Revenue dashboard', 'values' => ['Limited', 'Yes', 'Advanced', 'Very advanced'], 'tone' => 'mixed'],
                            ['label' => 'Payment recording', 'values' => ['Basic', 'Yes', 'Yes', 'Yes'], 'tone' => 'mixed'],
                            ['label' => 'Partial payments', 'values' => ['No', 'Yes', 'Yes', 'Yes'], 'tone' => 'mixed'],
                            ['label' => 'Debt tracking', 'values' => ['Limited', 'Yes', 'Advanced', 'Very advanced'], 'tone' => 'mixed'],
                            ['label' => 'Payment providers', 'values' => ['No', 'Limited', 'Yes', 'Custom'], 'tone' => 'mixed'],
                        ],
                    ],
                    [
                        'title' => 'Reports and analytics',
                        'rows' => [
                            ['label' => 'Main dashboard', 'values' => ['Simple', 'Medium', 'Advanced', 'Very advanced'], 'tone' => 'mixed'],
                            ['label' => 'Booking reports', 'values' => ['Basic', 'Yes', 'Advanced', 'Very advanced'], 'tone' => 'mixed'],
                            ['label' => 'Car analytics', 'values' => ['No', 'Limited', 'Yes', 'Yes'], 'tone' => 'mixed'],
                            ['label' => 'Customer analytics', 'values' => ['No', 'Limited', 'Yes', 'Yes'], 'tone' => 'mixed'],
                            ['label' => 'PDF / Excel export', 'values' => ['No', 'Limited', 'Yes', 'Yes'], 'tone' => 'mixed'],
                        ],
                    ],
                    [
                        'title' => 'Damage and maintenance',
                        'rows' => [
                            ['label' => 'Damage management', 'values' => ['No', 'Limited', 'Yes', 'Advanced'], 'tone' => 'mixed'],
                            ['label' => 'Damage photos upload', 'values' => ['No', 'Limited', 'Yes', 'Yes'], 'tone' => 'mixed'],
                            ['label' => 'AI damage detection', 'values' => ['No', 'Add-on', 'Add-on / included by plan', 'By contract'], 'tone' => 'mixed'],
                            ['label' => 'Maintenance management', 'values' => ['No', 'No', 'Yes', 'Advanced'], 'tone' => 'mixed'],
                            ['label' => 'Maintenance alerts', 'values' => ['No', 'No', 'Yes', 'Yes'], 'tone' => 'mixed'],
                        ],
                    ],
                    [
                        'title' => 'Users and permissions',
                        'rows' => [
                            ['label' => 'User management', 'values' => ['No', 'Yes', 'Yes', 'Advanced'], 'tone' => 'mixed'],
                            ['label' => 'Role-based permissions', 'values' => ['No', 'Simple', 'Yes', 'Very advanced'], 'tone' => 'mixed'],
                            ['label' => 'Security access', 'values' => ['No', 'Limited', 'Yes', 'Advanced'], 'tone' => 'mixed'],
                            ['label' => 'Employee activity log', 'values' => ['No', 'Limited', 'Yes', 'Yes'], 'tone' => 'mixed'],
                        ],
                    ],
                    [
                        'title' => 'Settings and customization',
                        'rows' => [
                            ['label' => 'Branding', 'values' => ['Logo and name', 'Basic', 'Medium', 'Full customization'], 'tone' => 'mixed'],
                            ['label' => 'Languages', 'values' => ['Basic language', 'Limited multilingual', 'Multilingual', 'Fully multilingual'], 'tone' => 'mixed'],
                            ['label' => 'Emails', 'values' => ['Basic', 'Yes', 'Yes', 'Custom'], 'tone' => 'mixed'],
                            ['label' => 'SEO settings', 'values' => ['No', 'No', 'Limited', 'Yes'], 'tone' => 'mixed'],
                            ['label' => 'Custom domain', 'values' => ['No', 'No', 'Add-on', 'Yes'], 'tone' => 'mixed'],
                        ],
                    ],
                    [
                        'title' => 'Support and integration',
                        'rows' => [
                            ['label' => 'Support', 'values' => ['Email', 'Support tickets', 'Priority', 'Account manager'], 'tone' => 'mixed'],
                            ['label' => 'API integration', 'values' => ['No', 'No', 'No', 'Yes'], 'tone' => 'mixed'],
                            ['label' => 'Training session', 'values' => ['No', 'Add-on', 'Add-on', 'By contract'], 'tone' => 'mixed'],
                        ],
                    ],
                ],
                'addons_title' => 'Suggested paid add-ons',
                'addons' => [
                    'Additional car: from $1 to $2 monthly per car.',
                    'Additional user: $5 monthly per user.',
                    'Additional branch: from $15 to $25 monthly per branch.',
                    'AI Damage Detection: from $29 monthly or based on inspection volume.',
                    'Custom Branding: $49 monthly or one-time setup fee.',
                    'Custom Domain: $20 monthly or included in Enterprise.',
                    'SMS / WhatsApp: based on message volume and usage.',
                ],
                'trial_title' => 'Suggested free trial',
                'trial_items' => [
                    '14-day free trial.',
                    'Up to 5 cars.',
                    'Up to 2 users.',
                    'Up to 20 bookings.',
                    'Without payment providers.',
                    'Without API.',
                    'Without advanced reports export.',
                ],
                'policy_title' => 'Launch pricing policy',
                'policy_paragraphs' => [
                    'Current prices are launch prices with a discount of up to 25% for a limited period of 3 to 6 months.',
                    'After the launch period ends, official prices become $39 for Starter, $79 for Growth, and $129 for Professional monthly.',
                    'Customers who subscribe yearly during the launch period can lock the discounted price for the duration of the yearly subscription.',
                ],
                'footer_text' => 'Car Rental SaaS Pricing Comparison - Launch Pricing Version',
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
            'translations' => self::defaultTranslations($supportedLocales),
        ];
    }

    /**
     * @param array<int, string> $supportedLocales
     */
    private static function defaultTranslations(array $supportedLocales): array
    {
        $translations = array_fill_keys($supportedLocales, []);

        if (in_array('ar', $supportedLocales, true)) {
            $translations['ar'] = [
                'locale_switcher' => [
                    'language_names' => [
                        'en' => 'الإنجليزية',
                        'ar' => 'العربية',
                        'ur' => 'الأردية',
                    ],
                ],
            ];
        }

        if (in_array('ur', $supportedLocales, true)) {
            $translations['ur'] = [
                'locale_switcher' => [
                    'language_names' => [
                        'en' => 'انگریزی',
                        'ar' => 'عربی',
                        'ur' => 'اردو',
                    ],
                ],
            ];
        }

        return $translations;
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
        $settings['cars_section']['fleet_button_icon_url'] = (string) ($settings['cars_section']['fleet_button_icon_url'] ?? '');
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
            'applications_page.roles',
            'applications_page.comparison',
            'plans_comparison_page.comparison_sections',
            'plans_comparison_page.addons',
            'plans_comparison_page.trial_items',
            'plans_comparison_page.policy_paragraphs',
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
            if (is_array($link) && in_array(($link['href'] ?? null), ['/applications', '#application'], true)) {
                return array_values($links);
            }
        }

        $applicationLink = ['label' => 'Application', 'href' => '/applications'];
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
            $iconBackgroundColor = trim((string) ($item['icon_background_color'] ?? '#f3f4f6'));

            if (!preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $iconBackgroundColor)) {
                $iconBackgroundColor = '#f3f4f6';
            }

            if ($title === '' && $content === '' && $imageUrl === '') {
                continue;
            }

            $cards[] = [
                'title' => $title,
                'image_url' => $imageUrl,
                'icon_background_color' => $iconBackgroundColor,
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
            $imageUrl = trim((string) ($item['image_url'] ?? ''));
            $description = trim((string) ($item['description'] ?? ''));
            $iconBackgroundColor = trim((string) ($item['icon_background_color'] ?? '#f3f4f6'));

            if (!preg_match('/^#(?:[0-9a-fA-F]{3}){1,2}$/', $iconBackgroundColor)) {
                $iconBackgroundColor = '#f3f4f6';
            }

            if ($title === '' && $description === '' && $imageUrl === '') {
                continue;
            }

            $steps[] = [
                'title' => $title,
                'image_url' => $imageUrl,
                'icon_background_color' => $iconBackgroundColor,
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
