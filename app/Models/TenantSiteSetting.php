<?php

namespace App\Models;

use App\Core\ReservationSettings as ReservationSettingsCore;
use App\Core\PlateFormatSettings as PlateFormatSettingsCore;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use MohamedGaldi\ViltFilepond\Traits\HasFiles;

class TenantSiteSetting extends Model
{
    use HasFiles;

    protected $fillable = [
        'tenant_id',
        'site_name',
        'logo_url',
        'primary_color',
        'secondary_color',
        'tax_percentage',
        'document_extraction_daily_limit',
        'enabled_locales',
        'hero',
        'about',
        'contact',
        'contact_page',
        'seo',
        'pdf_header',
        'pdf_templates',
        'police_notice',
        'reservation_settings',
        'translations',
        'footer',
        'plate_formats',
    ];

    protected $casts = [
        'tax_percentage' => 'decimal:2',
        'document_extraction_daily_limit' => 'integer',
        'enabled_locales' => 'array',
        'hero' => 'array',
        'about' => 'array',
        'contact' => 'array',
        'contact_page' => 'array',
        'seo' => 'array',
        'pdf_header' => 'array',
        'pdf_templates' => 'array',
        'police_notice' => 'array',
        'reservation_settings' => 'array',
        'translations' => 'array',
        'footer' => 'array',
        'plate_formats' => 'array',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function defaults(): array
    {
        $supportedLocales = self::supportedLocales();

        return [
            'site_name' => null,
            'logo_url' => null,
            'primary_color' => '#f97316',
            'secondary_color' => '#ea580c',
            'tax_percentage' => 7.0,
            'document_extraction_daily_limit' => null,
            'enabled_locales' => $supportedLocales,
            'hero' => [
                'title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'description' => [
                    'en' => null,
                    'ar' => null,
                ],
                'button_text' => [
                    'en' => null,
                    'ar' => null,
                ],
                'button_link' => null,
            ],
            'about' => [
                'title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'subtitle' => [
                    'en' => null,
                    'ar' => null,
                ],
                'story_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'story_p1' => [
                    'en' => null,
                    'ar' => null,
                ],
                'story_p2' => [
                    'en' => null,
                    'ar' => null,
                ],
                'mission_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'mission_subtitle' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cta_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cta_subtitle' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cta_browse_text' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cta_contact_text' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'contact' => [
                'phone' => null,
                'email' => null,
                'address' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'contact_page' => [
                'title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'subtitle' => [
                    'en' => null,
                    'ar' => null,
                ],
                'form_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'info_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'hours' => [
                    'en' => null,
                    'ar' => null,
                ],
                'quick_links_title' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'seo' => [
                'defaults' => [
                    'title_suffix' => [
                        'en' => null,
                        'ar' => null,
                    ],
                    'default_description' => [
                        'en' => null,
                        'ar' => null,
                    ],
                    'og_image' => null,
                    'robots' => 'index,follow',
                ],
                'pages' => [
                    'home' => [
                        'title' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'description' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'canonical_url' => null,
                        'robots' => null,
                        'focus_keyword' => [
                            'en' => null,
                            'ar' => null,
                        ],
                    ],
                    'fleet' => [
                        'title' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'description' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'canonical_url' => null,
                        'robots' => null,
                        'focus_keyword' => [
                            'en' => null,
                            'ar' => null,
                        ],
                    ],
                    'about' => [
                        'title' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'description' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'canonical_url' => null,
                        'robots' => null,
                        'focus_keyword' => [
                            'en' => null,
                            'ar' => null,
                        ],
                    ],
                    'contact' => [
                        'title' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'description' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'canonical_url' => null,
                        'robots' => null,
                        'focus_keyword' => [
                            'en' => null,
                            'ar' => null,
                        ],
                    ],
                    'car' => [
                        'title' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'description' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'canonical_url' => null,
                        'robots' => null,
                        'focus_keyword' => [
                            'en' => null,
                            'ar' => null,
                        ],
                    ],
                    'booking_checkout' => [
                        'title' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'description' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'canonical_url' => null,
                        'robots' => null,
                        'focus_keyword' => [
                            'en' => null,
                            'ar' => null,
                        ],
                    ],
                    'booking_confirmation' => [
                        'title' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'description' => [
                            'en' => null,
                            'ar' => null,
                        ],
                        'canonical_url' => null,
                        'robots' => null,
                        'focus_keyword' => [
                            'en' => null,
                            'ar' => null,
                        ],
                    ],
                ],
                'technical' => [
                    'sitemap' => [
                        'pages' => [
                            ['path' => '/', 'priority' => 1.0, 'changeFreq' => 'weekly', 'lastmod' => null],
                            ['path' => '/fleet', 'priority' => 0.9, 'changeFreq' => 'weekly', 'lastmod' => null],
                            ['path' => '/about', 'priority' => 0.8, 'changeFreq' => 'monthly', 'lastmod' => null],
                            ['path' => '/contact', 'priority' => 0.8, 'changeFreq' => 'monthly', 'lastmod' => null],
                        ],
                    ],
                    'robots' => [
                        'allowAll' => true,
                        'disallowPaths' => ['/admin', '/private', '/api/internal'],
                        'crawlDelay' => 1,
                        'requestRate' => 30,
                        'sitemapUrl' => '/sitemap.xml',
                    ],
                    'redirects' => [
                        'items' => [],
                    ],
                ],
            ],
            'pdf_header' => [
                'company_name' => [
                    'en' => null,
                    'ar' => null,
                ],
                'cr_number' => null,
                'po_box' => null,
                'pc' => null,
                'country' => [
                    'en' => null,
                    'ar' => null,
                ],
                'gsm_1' => null,
                'gsm_2' => null,
                'gsm_3' => null,
                'registry_label' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'pdf_templates' => [
                'contract' => \App\Support\TenantPdfTemplateRegistry::DEFAULT_CONTRACT_TEMPLATE,
            ],
            'police_notice' => [
                'company_name' => [
                    'en' => null,
                    'ar' => null,
                ],
                'registry_label' => [
                    'en' => null,
                    'ar' => null,
                ],
                'subject' => [
                    'en' => null,
                    'ar' => null,
                ],
                'greeting' => [
                    'en' => null,
                    'ar' => null,
                ],
                'intro' => [
                    'en' => null,
                    'ar' => null,
                ],
                'office_line' => [
                    'en' => null,
                    'ar' => null,
                ],
                'company_address' => [
                    'en' => null,
                    'ar' => null,
                ],
                'company_phone' => [
                    'en' => null,
                    'ar' => null,
                ],
                'vehicle_section_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'renter_section_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'closing_1' => [
                    'en' => null,
                    'ar' => null,
                ],
                'closing_2' => [
                    'en' => null,
                    'ar' => null,
                ],
                'attachments_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'attachments' => [
                    'en' => null,
                    'ar' => null,
                ],
                'signature_name_label' => [
                    'en' => null,
                    'ar' => null,
                ],
                'signature_title_label' => [
                    'en' => null,
                    'ar' => null,
                ],
                'signature_date_label' => [
                    'en' => null,
                    'ar' => null,
                ],
                'footer_note' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'police_notice' => [
                'company_name' => [
                    'en' => null,
                    'ar' => null,
                ],
                'registry_label' => [
                    'en' => null,
                    'ar' => null,
                ],
                'subject' => [
                    'en' => null,
                    'ar' => null,
                ],
                'greeting' => [
                    'en' => null,
                    'ar' => null,
                ],
                'intro' => [
                    'en' => null,
                    'ar' => null,
                ],
                'office_line' => [
                    'en' => null,
                    'ar' => null,
                ],
                'company_address' => [
                    'en' => null,
                    'ar' => null,
                ],
                'company_phone' => [
                    'en' => null,
                    'ar' => null,
                ],
                'vehicle_section_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'renter_section_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'closing_1' => [
                    'en' => null,
                    'ar' => null,
                ],
                'closing_2' => [
                    'en' => null,
                    'ar' => null,
                ],
                'attachments_title' => [
                    'en' => null,
                    'ar' => null,
                ],
                'attachments' => [
                    'en' => null,
                    'ar' => null,
                ],
                'signature_name_label' => [
                    'en' => null,
                    'ar' => null,
                ],
                'signature_title_label' => [
                    'en' => null,
                    'ar' => null,
                ],
                'signature_date_label' => [
                    'en' => null,
                    'ar' => null,
                ],
                'footer_note' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
            'reservation_settings' => ReservationSettingsCore::defaults(),
            'plate_formats' => PlateFormatSettingsCore::defaults(),
            'translations' => [
                ...array_fill_keys($supportedLocales, []),
            ],
            'footer' => [
                'description' => [
                    'en' => null,
                    'ar' => null,
                ],
            ],
        ];
    }

    public static function normalize(?self $settings): array
    {
        $defaults = self::defaults();
        $data = $settings?->toArray() ?? [];
        $logoUrl = null;

        if ($settings) {
            $file = $settings->relationLoaded('files')
                ? $settings->files->firstWhere('collection', 'logo')
                : $settings->files()->where('collection', 'logo')->first();

            if ($file && $file->path) {
                $logoUrl = self::publicUrlFromPath($file->path);
            }
        }

        return [
            'site_name' => self::nullableString($data['site_name'] ?? $defaults['site_name']),
            'logo_url' => self::nullableString($logoUrl ?: ($data['logo_url'] ?? $defaults['logo_url'])),
            'primary_color' => self::normalizeHexColor($data['primary_color'] ?? $defaults['primary_color'], $defaults['primary_color']),
            'secondary_color' => self::normalizeHexColor($data['secondary_color'] ?? $defaults['secondary_color'], $defaults['secondary_color']),
            'tax_percentage' => self::normalizePercentage($data['tax_percentage'] ?? $defaults['tax_percentage'], 7.0),
            'document_extraction_daily_limit' => self::nullablePositiveInteger(
                $data['document_extraction_daily_limit'] ?? $defaults['document_extraction_daily_limit']
            ),
            'enabled_locales' => self::normalizeEnabledLocales($data['enabled_locales'] ?? $defaults['enabled_locales']),
            'hero' => [
                'title' => [
                    'en' => self::nullableString(data_get($data, 'hero.title.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.title.ar')),
                ],
                'description' => [
                    'en' => self::nullableString(data_get($data, 'hero.description.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.description.ar')),
                ],
                'button_text' => [
                    'en' => self::nullableString(data_get($data, 'hero.button_text.en')),
                    'ar' => self::nullableString(data_get($data, 'hero.button_text.ar')),
                ],
                'button_link' => self::nullableString(data_get($data, 'hero.button_link')),
            ],
            'about' => [
                'title' => [
                    'en' => self::nullableString(data_get($data, 'about.title.en')),
                    'ar' => self::nullableString(data_get($data, 'about.title.ar')),
                ],
                'subtitle' => [
                    'en' => self::nullableString(data_get($data, 'about.subtitle.en')),
                    'ar' => self::nullableString(data_get($data, 'about.subtitle.ar')),
                ],
                'story_title' => [
                    'en' => self::nullableString(data_get($data, 'about.story_title.en')),
                    'ar' => self::nullableString(data_get($data, 'about.story_title.ar')),
                ],
                'story_p1' => [
                    'en' => self::nullableString(data_get($data, 'about.story_p1.en')),
                    'ar' => self::nullableString(data_get($data, 'about.story_p1.ar')),
                ],
                'story_p2' => [
                    'en' => self::nullableString(data_get($data, 'about.story_p2.en')),
                    'ar' => self::nullableString(data_get($data, 'about.story_p2.ar')),
                ],
                'mission_title' => [
                    'en' => self::nullableString(data_get($data, 'about.mission_title.en')),
                    'ar' => self::nullableString(data_get($data, 'about.mission_title.ar')),
                ],
                'mission_subtitle' => [
                    'en' => self::nullableString(data_get($data, 'about.mission_subtitle.en')),
                    'ar' => self::nullableString(data_get($data, 'about.mission_subtitle.ar')),
                ],
                'cta_title' => [
                    'en' => self::nullableString(data_get($data, 'about.cta_title.en')),
                    'ar' => self::nullableString(data_get($data, 'about.cta_title.ar')),
                ],
                'cta_subtitle' => [
                    'en' => self::nullableString(data_get($data, 'about.cta_subtitle.en')),
                    'ar' => self::nullableString(data_get($data, 'about.cta_subtitle.ar')),
                ],
                'cta_browse_text' => [
                    'en' => self::nullableString(data_get($data, 'about.cta_browse_text.en')),
                    'ar' => self::nullableString(data_get($data, 'about.cta_browse_text.ar')),
                ],
                'cta_contact_text' => [
                    'en' => self::nullableString(data_get($data, 'about.cta_contact_text.en')),
                    'ar' => self::nullableString(data_get($data, 'about.cta_contact_text.ar')),
                ],
            ],
            'contact' => [
                'phone' => self::nullableString(data_get($data, 'contact.phone')),
                'email' => self::nullableString(data_get($data, 'contact.email')),
                'address' => [
                    'en' => self::nullableString(data_get($data, 'contact.address.en')),
                    'ar' => self::nullableString(data_get($data, 'contact.address.ar')),
                ],
            ],
            'contact_page' => [
                'title' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.title.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.title.ar')),
                ],
                'subtitle' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.subtitle.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.subtitle.ar')),
                ],
                'form_title' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.form_title.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.form_title.ar')),
                ],
                'info_title' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.info_title.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.info_title.ar')),
                ],
                'hours' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.hours.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.hours.ar')),
                ],
                'quick_links_title' => [
                    'en' => self::nullableString(data_get($data, 'contact_page.quick_links_title.en')),
                    'ar' => self::nullableString(data_get($data, 'contact_page.quick_links_title.ar')),
                ],
            ],
            'seo' => [
                'defaults' => [
                    'title_suffix' => [
                        'en' => self::nullableString(data_get($data, 'seo.defaults.title_suffix.en')),
                        'ar' => self::nullableString(data_get($data, 'seo.defaults.title_suffix.ar')),
                    ],
                    'default_description' => [
                        'en' => self::nullableString(data_get($data, 'seo.defaults.default_description.en')),
                        'ar' => self::nullableString(data_get($data, 'seo.defaults.default_description.ar')),
                    ],
                    'og_image' => self::nullableString(data_get($data, 'seo.defaults.og_image')),
                    'robots' => self::nullableString(data_get($data, 'seo.defaults.robots')) ?? 'index,follow',
                ],
                'pages' => [
                    'home' => [
                        'title' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.home.title.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.home.title.ar')),
                        ],
                        'description' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.home.description.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.home.description.ar')),
                        ],
                        'canonical_url' => self::nullableString(data_get($data, 'seo.pages.home.canonical_url')),
                        'robots' => self::nullableString(data_get($data, 'seo.pages.home.robots')),
                        'focus_keyword' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.home.focus_keyword.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.home.focus_keyword.ar')),
                        ],
                    ],
                    'fleet' => [
                        'title' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.fleet.title.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.fleet.title.ar')),
                        ],
                        'description' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.fleet.description.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.fleet.description.ar')),
                        ],
                        'canonical_url' => self::nullableString(data_get($data, 'seo.pages.fleet.canonical_url')),
                        'robots' => self::nullableString(data_get($data, 'seo.pages.fleet.robots')),
                        'focus_keyword' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.fleet.focus_keyword.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.fleet.focus_keyword.ar')),
                        ],
                    ],
                    'about' => [
                        'title' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.about.title.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.about.title.ar')),
                        ],
                        'description' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.about.description.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.about.description.ar')),
                        ],
                        'canonical_url' => self::nullableString(data_get($data, 'seo.pages.about.canonical_url')),
                        'robots' => self::nullableString(data_get($data, 'seo.pages.about.robots')),
                        'focus_keyword' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.about.focus_keyword.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.about.focus_keyword.ar')),
                        ],
                    ],
                    'contact' => [
                        'title' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.contact.title.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.contact.title.ar')),
                        ],
                        'description' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.contact.description.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.contact.description.ar')),
                        ],
                        'canonical_url' => self::nullableString(data_get($data, 'seo.pages.contact.canonical_url')),
                        'robots' => self::nullableString(data_get($data, 'seo.pages.contact.robots')),
                        'focus_keyword' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.contact.focus_keyword.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.contact.focus_keyword.ar')),
                        ],
                    ],
                    'car' => [
                        'title' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.car.title.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.car.title.ar')),
                        ],
                        'description' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.car.description.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.car.description.ar')),
                        ],
                        'canonical_url' => self::nullableString(data_get($data, 'seo.pages.car.canonical_url')),
                        'robots' => self::nullableString(data_get($data, 'seo.pages.car.robots')),
                        'focus_keyword' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.car.focus_keyword.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.car.focus_keyword.ar')),
                        ],
                    ],
                    'booking_checkout' => [
                        'title' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.booking_checkout.title.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.booking_checkout.title.ar')),
                        ],
                        'description' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.booking_checkout.description.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.booking_checkout.description.ar')),
                        ],
                        'canonical_url' => self::nullableString(data_get($data, 'seo.pages.booking_checkout.canonical_url')),
                        'robots' => self::nullableString(data_get($data, 'seo.pages.booking_checkout.robots')),
                        'focus_keyword' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.booking_checkout.focus_keyword.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.booking_checkout.focus_keyword.ar')),
                        ],
                    ],
                    'booking_confirmation' => [
                        'title' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.booking_confirmation.title.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.booking_confirmation.title.ar')),
                        ],
                        'description' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.booking_confirmation.description.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.booking_confirmation.description.ar')),
                        ],
                        'canonical_url' => self::nullableString(data_get($data, 'seo.pages.booking_confirmation.canonical_url')),
                        'robots' => self::nullableString(data_get($data, 'seo.pages.booking_confirmation.robots')),
                        'focus_keyword' => [
                            'en' => self::nullableString(data_get($data, 'seo.pages.booking_confirmation.focus_keyword.en')),
                            'ar' => self::nullableString(data_get($data, 'seo.pages.booking_confirmation.focus_keyword.ar')),
                        ],
                    ],
                ],
                'technical' => [
                    'sitemap' => [
                        'pages' => collect((array) data_get($data, 'seo.technical.sitemap.pages', data_get($defaults, 'seo.technical.sitemap.pages', [])))
                            ->map(function ($page) {
                                $path = self::nullableString(data_get($page, 'path'));
                                if ($path === null) {
                                    return null;
                                }

                                $priority = data_get($page, 'priority');
                                $priority = is_numeric($priority) ? (float) $priority : 0.5;

                                return [
                                    'path' => str_starts_with($path, '/') ? $path : '/'.$path,
                                    'priority' => max(0.1, min(1.0, round($priority, 1))),
                                    'changeFreq' => (string) (data_get($page, 'changeFreq') ?: 'weekly'),
                                    'lastmod' => self::nullableString(data_get($page, 'lastmod')),
                                ];
                            })
                            ->filter()
                            ->values()
                            ->all(),
                    ],
                    'robots' => [
                        'allowAll' => (bool) data_get($data, 'seo.technical.robots.allowAll', data_get($defaults, 'seo.technical.robots.allowAll', true)),
                        'disallowPaths' => collect((array) data_get($data, 'seo.technical.robots.disallowPaths', data_get($defaults, 'seo.technical.robots.disallowPaths', [])))
                            ->map(fn ($path) => self::nullableString($path))
                            ->filter()
                            ->values()
                            ->all(),
                        'crawlDelay' => (int) data_get($data, 'seo.technical.robots.crawlDelay', data_get($defaults, 'seo.technical.robots.crawlDelay', 1)),
                        'requestRate' => (int) data_get($data, 'seo.technical.robots.requestRate', data_get($defaults, 'seo.technical.robots.requestRate', 30)),
                        'sitemapUrl' => (string) data_get($data, 'seo.technical.robots.sitemapUrl', data_get($defaults, 'seo.technical.robots.sitemapUrl', '/sitemap.xml')),
                    ],
                    'redirects' => [
                        'items' => collect((array) data_get($data, 'seo.technical.redirects.items', []))
                            ->map(function ($item) {
                                $fromPath = self::nullableString(data_get($item, 'fromPath'));
                                $toPath = self::nullableString(data_get($item, 'toPath'));

                                if ($fromPath === null || $toPath === null) {
                                    return null;
                                }

                                $statusCode = (int) data_get($item, 'statusCode', 301);

                                return [
                                    'id' => (string) (data_get($item, 'id') ?: uniqid('redirect_', true)),
                                    'fromPath' => str_starts_with($fromPath, '/') ? $fromPath : '/'.$fromPath,
                                    'toPath' => str_starts_with($toPath, '/') ? $toPath : '/'.$toPath,
                                    'statusCode' => in_array($statusCode, [301, 302, 307, 308], true) ? $statusCode : 301,
                                    'isPermanent' => (bool) data_get($item, 'isPermanent', in_array($statusCode, [301, 308], true)),
                                    'isActive' => (bool) data_get($item, 'isActive', true),
                                ];
                            })
                            ->filter()
                            ->values()
                            ->all(),
                    ],
                ],
            ],
            'pdf_header' => [
                'company_name' => [
                    'en' => self::nullableString(data_get($data, 'pdf_header.company_name.en')),
                    'ar' => self::nullableString(data_get($data, 'pdf_header.company_name.ar')),
                ],
                'cr_number' => self::nullableString(data_get($data, 'pdf_header.cr_number')),
                'po_box' => self::nullableString(data_get($data, 'pdf_header.po_box')),
                'pc' => self::nullableString(data_get($data, 'pdf_header.pc')),
                'country' => [
                    'en' => self::nullableString(data_get($data, 'pdf_header.country.en')),
                    'ar' => self::nullableString(data_get($data, 'pdf_header.country.ar')),
                ],
                'gsm_1' => self::nullableString(data_get($data, 'pdf_header.gsm_1')),
                'gsm_2' => self::nullableString(data_get($data, 'pdf_header.gsm_2')),
                'gsm_3' => self::nullableString(data_get($data, 'pdf_header.gsm_3')),
                'registry_label' => [
                    'en' => self::nullableString(data_get($data, 'pdf_header.registry_label.en')),
                    'ar' => self::nullableString(data_get($data, 'pdf_header.registry_label.ar')),
                ],
            ],
            'pdf_templates' => [
                'contract' => in_array(
                    (string) data_get($data, 'pdf_templates.contract', $defaults['pdf_templates']['contract']),
                    \App\Support\TenantPdfTemplateRegistry::contractTemplateValues(),
                    true
                )
                    ? (string) data_get($data, 'pdf_templates.contract', $defaults['pdf_templates']['contract'])
                    : $defaults['pdf_templates']['contract'],
            ],
            'police_notice' => [
                'company_name' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.company_name.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.company_name.ar')),
                ],
                'registry_label' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.registry_label.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.registry_label.ar')),
                ],
                'subject' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.subject.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.subject.ar')),
                ],
                'greeting' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.greeting.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.greeting.ar')),
                ],
                'intro' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.intro.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.intro.ar')),
                ],
                'office_line' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.office_line.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.office_line.ar')),
                ],
                'company_address' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.company_address.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.company_address.ar')),
                ],
                'company_phone' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.company_phone.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.company_phone.ar')),
                ],
                'vehicle_section_title' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.vehicle_section_title.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.vehicle_section_title.ar')),
                ],
                'renter_section_title' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.renter_section_title.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.renter_section_title.ar')),
                ],
                'closing_1' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.closing_1.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.closing_1.ar')),
                ],
                'closing_2' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.closing_2.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.closing_2.ar')),
                ],
                'attachments_title' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.attachments_title.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.attachments_title.ar')),
                ],
                'attachments' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.attachments.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.attachments.ar')),
                ],
                'signature_name_label' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.signature_name_label.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.signature_name_label.ar')),
                ],
                'signature_title_label' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.signature_title_label.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.signature_title_label.ar')),
                ],
                'signature_date_label' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.signature_date_label.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.signature_date_label.ar')),
                ],
                'footer_note' => [
                    'en' => self::nullableString(data_get($data, 'police_notice.footer_note.en')),
                    'ar' => self::nullableString(data_get($data, 'police_notice.footer_note.ar')),
                ],
            ],
            'translations' => self::normalizeTranslations($data['translations'] ?? $defaults['translations']),
            'reservation_settings' => ReservationSettingsCore::normalize(
                is_array($data['reservation_settings'] ?? null) ? $data['reservation_settings'] : null
            ),
            'plate_formats' => PlateFormatSettingsCore::normalize(
                is_array($data['plate_formats'] ?? null) ? $data['plate_formats'] : null
            ),
            'footer' => [
                'description' => [
                    'en' => self::nullableString(data_get($data, 'footer.description.en')),
                    'ar' => self::nullableString(data_get($data, 'footer.description.ar')),
                ],
            ],
        ];
    }

    public static function forTenant(Tenant $tenant): array
    {
        return self::normalize($tenant->siteSetting);
    }

    public static function publicUrlFromPath(?string $path): ?string
    {
        $path = trim((string) ($path ?? ''));
        if ($path === '') {
            return null;
        }

        $normalized = ltrim($path, '/');
        if (str_starts_with($normalized, 'storage/')) {
            $normalized = substr($normalized, strlen('storage/'));
        }

        return Storage::url($normalized);
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function normalizeHexColor(mixed $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return strtolower($value);
        }

        return strtolower($fallback);
    }

    private static function normalizePercentage(mixed $value, float $fallback): float
    {
        if ($value === null || $value === '') {
            return $fallback;
        }

        $number = (float) $value;

        if (!is_finite($number)) {
            return $fallback;
        }

        return max(0, min(100, round($number, 2)));
    }

    private static function normalizeEnabledLocales(mixed $value): array
    {
        $supported = self::supportedLocales();
        $enabled = is_array($value) ? $value : [];
        $enabled = array_values(array_unique(array_intersect($supported, array_map('strval', $enabled))));

        return empty($enabled) ? $supported : $enabled;
    }

    private static function normalizeTranslations(mixed $value): array
    {
        $value = is_array($value) ? $value : [];
        $supported = self::supportedLocales();
        $result = [];

        foreach ($supported as $locale) {
            $result[$locale] = is_array($value[$locale] ?? null) ? $value[$locale] : [];
        }

        return $result;
    }

    private static function nullablePositiveInteger(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (!is_numeric($value)) {
            return null;
        }

        $integer = (int) $value;

        return $integer >= 1 ? min($integer, 100000) : null;
    }

    private static function supportedLocales(): array
    {
        $supported = array_keys((array) config('laravellocalization.supportedLocales', []));
        if (empty($supported)) {
            $supported = array_values((array) config('app.available_locales', ['en']));
        }

        $supported = array_values(array_unique(array_map('strval', $supported)));

        return empty($supported) ? ['en'] : $supported;
    }
}
