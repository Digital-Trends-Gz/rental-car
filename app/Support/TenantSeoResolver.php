<?php

namespace App\Support;

use App\Models\Car;
use App\Models\Reservation;
use App\Models\SiteSetting;
use App\Models\Tenant;
use App\Models\TenantSiteSetting;
use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

class TenantSeoResolver
{
    private const MAIN_SITE_SEO_KEY = 'main_site_seo';

    public static function forPage(?Tenant $tenant, string $pageKey): array
    {
        $settings = self::settings($tenant);
        $seoSettings = self::seoSettings($settings);
        $pageSettings = self::pageSettings($seoSettings, $pageKey);
        $enabledLocales = self::enabledLocales($settings);
        $siteName = self::siteName($tenant, $settings);
        $titleSuffix = self::localized(data_get($seoSettings, 'defaults.title_suffix'), $siteName);
        $customTitle = self::localized(data_get($pageSettings, 'title'));
        $fallbackTitle = self::fallbackTitle($pageKey, $siteName, $titleSuffix);
        $title = $customTitle !== null
            ? ($pageKey === 'home' ? $customTitle : self::composeTitle($customTitle, $titleSuffix))
            : $fallbackTitle;

        $description = self::localized(
            data_get($pageSettings, 'description'),
            self::localized(
                data_get($seoSettings, 'defaults.default_description'),
                self::fallbackDescription($pageKey, $siteName)
            )
        );

        $canonicalUrl = self::cleanUrl(
            data_get($pageSettings, 'canonical_url'),
            url()->current()
        );

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonicalUrl,
            'robots' => self::resolvePageRobots($pageSettings, $seoSettings, 'index,follow'),
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => self::cleanUrl(data_get($seoSettings, 'defaults.og_image'), $settings['logo_url'] ?? null),
            'alternates' => self::alternateUrls($enabledLocales),
            'schemas' => self::pageSchemas($tenant, $settings, $pageKey, $title, $description, $canonicalUrl),
        ];
    }

    public static function forCar(?Tenant $tenant, Car $car): array
    {
        $settings = self::settings($tenant);
        $seoSettings = self::seoSettings($settings);
        $pageSettings = self::pageSettings($seoSettings, 'car');
        $enabledLocales = self::enabledLocales($settings);
        $siteName = self::siteName($tenant, $settings);
        $titleSuffix = self::localized(data_get($seoSettings, 'defaults.title_suffix'), $siteName);
        $carName = self::carName($car);
        $customTitle = self::localized(data_get($pageSettings, 'title'));
        $fallbackTitle = self::composeTitle(
            $carName !== '' ? "{$carName} Rental" : self::fallbackLabel('car'),
            $titleSuffix
        );
        $title = $customTitle !== null
            ? self::composeTitle(str_replace(':car', $carName, $customTitle), $titleSuffix)
            : $fallbackTitle;

        $defaultDescription = self::localized(
            data_get($seoSettings, 'defaults.default_description'),
            self::fallbackCarDescription($siteName, $car)
        );
        $customDescription = self::localized(data_get($pageSettings, 'description'));
        $description = $customDescription !== null
            ? str_replace(':car', $carName, $customDescription)
            : $defaultDescription;

        $canonicalUrl = self::cleanUrl(
            data_get($pageSettings, 'canonical_url'),
            url()->current()
        );

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonicalUrl,
            'robots' => self::resolvePageRobots($pageSettings, $seoSettings, 'index,follow'),
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => self::cleanUrl($car->image_url ?: data_get($seoSettings, 'defaults.og_image'), $settings['logo_url'] ?? null),
            'alternates' => self::alternateUrls($enabledLocales),
            'schemas' => self::carSchemas($tenant, $settings, $car, $title, $description, $canonicalUrl),
        ];
    }

    public static function forReservation(?Tenant $tenant, Reservation $reservation, string $pageKey): array
    {
        $settings = self::settings($tenant);
        $seoSettings = self::seoSettings($settings);
        $pageSettings = self::pageSettings($seoSettings, $pageKey);
        $enabledLocales = self::enabledLocales($settings);
        $siteName = self::siteName($tenant, $settings);
        $titleSuffix = self::localized(data_get($seoSettings, 'defaults.title_suffix'), $siteName);
        $reservationNumber = trim((string) $reservation->reservation_number);
        $customTitle = self::localized(data_get($pageSettings, 'title'));
        $fallbackTitle = self::composeTitle(self::fallbackReservationTitle($pageKey, $reservationNumber), $titleSuffix);
        $title = $customTitle !== null
            ? self::composeTitle(str_replace(':reservation', $reservationNumber, $customTitle), $titleSuffix)
            : $fallbackTitle;

        $defaultDescription = self::localized(
            data_get($seoSettings, 'defaults.default_description'),
            self::fallbackReservationDescription($pageKey, $siteName, $reservation)
        );
        $customDescription = self::localized(data_get($pageSettings, 'description'));
        $description = $customDescription !== null
            ? str_replace(':reservation', $reservationNumber, $customDescription)
            : $defaultDescription;

        $canonicalUrl = self::cleanUrl(
            data_get($pageSettings, 'canonical_url'),
            url()->current()
        );

        return [
            'title' => $title,
            'description' => $description,
            'canonical_url' => $canonicalUrl,
            'robots' => self::resolvePageRobots($pageSettings, $seoSettings, 'noindex,nofollow'),
            'og_title' => $title,
            'og_description' => $description,
            'og_image' => self::cleanUrl($reservation->car?->image_url ?: data_get($seoSettings, 'defaults.og_image'), $settings['logo_url'] ?? null),
            'alternates' => self::alternateUrls($enabledLocales),
            'schemas' => self::reservationSchemas($tenant, $settings, $reservation, $pageKey, $title, $description, $canonicalUrl),
        ];
    }

    private static function settings(?Tenant $tenant): array
    {
        if ($tenant) {
            return TenantSiteSetting::forTenant($tenant);
        }

        $stored = SiteSetting::query()
            ->where('key', self::MAIN_SITE_SEO_KEY)
            ->value('value');

        return [
            ...TenantSiteSetting::defaults(),
            'seo' => array_replace_recursive(
                TenantSiteSetting::defaults()['seo'],
                is_array($stored) ? $stored : []
            ),
        ];
    }

    private static function seoSettings(array $settings): array
    {
        $seoSettings = $settings['seo'] ?? null;

        return is_array($seoSettings) ? $seoSettings : TenantSiteSetting::defaults()['seo'];
    }

    private static function pageSettings(array $seoSettings, string $pageKey): array
    {
        $pageSettings = data_get($seoSettings, "pages.{$pageKey}");

        return is_array($pageSettings) ? $pageSettings : [];
    }

    private static function siteName(?Tenant $tenant, array $settings): string
    {
        $siteName = trim((string) ($settings['site_name'] ?? ''));

        if ($siteName !== '') {
            return $siteName;
        }

        $tenantName = trim((string) ($tenant?->name ?? ''));

        if ($tenantName !== '') {
            return $tenantName;
        }

        return (string) config('app.name', 'Car4u');
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, string>
     */
    private static function enabledLocales(array $settings): array
    {
        $enabled = array_values(array_filter(
            array_map('strval', (array) ($settings['enabled_locales'] ?? [])),
            static fn (string $value) => trim($value) !== ''
        ));

        if (!empty($enabled)) {
            return array_values(array_unique($enabled));
        }

        return array_values(array_keys((array) config('laravellocalization.supportedLocales', ['en' => []])));
    }

    private static function localized(mixed $value, ?string $fallback = null): ?string
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value !== '' ? $value : $fallback;
        }

        if (!is_array($value)) {
            return $fallback;
        }

        $locale = (string) app()->getLocale();
        $candidate = trim((string) ($value[$locale] ?? $value['en'] ?? $value['ar'] ?? ''));

        return $candidate !== '' ? $candidate : $fallback;
    }

    private static function composeTitle(string $title, string $suffix): string
    {
        $title = trim($title);
        $suffix = trim($suffix);

        if ($title === '') {
            return $suffix;
        }

        if ($suffix === '') {
            return $title;
        }

        return "{$title} | {$suffix}";
    }

    private static function fallbackTitle(string $pageKey, string $siteName, string $titleSuffix): string
    {
        if ($pageKey === 'home') {
            return $siteName;
        }

        return self::composeTitle(self::fallbackLabel($pageKey), $titleSuffix);
    }

    private static function fallbackLabel(string $pageKey): string
    {
        $labels = [
            'en' => [
                'fleet' => 'Fleet',
                'about' => 'About',
                'contact' => 'Contact',
                'car' => 'Car Rental',
                'booking_checkout' => 'Booking Checkout',
                'booking_confirmation' => 'Booking Confirmation',
            ],
            'ar' => [
                'fleet' => 'الأسطول',
                'about' => 'من نحن',
                'contact' => 'اتصل بنا',
                'car' => 'تأجير سيارة',
                'booking_checkout' => 'إتمام الحجز',
                'booking_confirmation' => 'تأكيد الحجز',
            ],
        ];

        $locale = (string) app()->getLocale();

        return $labels[$locale][$pageKey] ?? $labels['en'][$pageKey] ?? ucfirst($pageKey);
    }

    private static function fallbackDescription(string $pageKey, string $siteName): string
    {
        $descriptions = [
            'en' => [
                'home' => "Discover {$siteName} and reserve your next rental car online.",
                'fleet' => "Browse available rental vehicles from {$siteName}.",
                'about' => "Learn more about {$siteName} and its car rental services.",
                'contact' => "Get in touch with {$siteName} for bookings and support.",
                'car' => "View rental car details and pricing from {$siteName}.",
                'booking_checkout' => "Choose your payment provider and complete your booking securely with {$siteName}.",
                'booking_confirmation' => "Review your confirmed booking and reservation details from {$siteName}.",
            ],
            'ar' => [
                'home' => "اكتشف {$siteName} واحجز سيارة الإيجار التالية عبر الإنترنت.",
                'fleet' => "استعرض سيارات الإيجار المتاحة من {$siteName}.",
                'about' => "تعرّف أكثر على {$siteName} وخدمات تأجير السيارات الخاصة به.",
                'contact' => "تواصل مع {$siteName} للحجوزات والدعم.",
                'car' => "اطلع على تفاصيل السيارة وسعر الإيجار لدى {$siteName}.",
                'booking_checkout' => "اختر مزود الدفع وأكمل الحجز بأمان مع {$siteName}.",
                'booking_confirmation' => "راجع تفاصيل الحجز المؤكد ومعلومات الحجز لدى {$siteName}.",
            ],
        ];

        $locale = (string) app()->getLocale();

        return $descriptions[$locale][$pageKey]
            ?? $descriptions['en'][$pageKey]
            ?? "Explore {$siteName}.";
    }

    private static function fallbackCarDescription(string $siteName, Car $car): string
    {
        $carName = self::carName($car);
        $price = is_numeric($car->price_per_day) ? number_format((float) $car->price_per_day, 2) : null;
        $fuelType = method_exists($car->fuel_type, 'label') ? $car->fuel_type->label() : trim((string) $car->fuel_type);

        if ((string) app()->getLocale() === 'ar') {
            return trim("احجز {$carName} لدى {$siteName}".($price ? " بسعر يبدأ من {$price} يوميًا." : '.').($fuelType !== '' ? " نوع الوقود: {$fuelType}." : ''));
        }

        return trim("Book {$carName} with {$siteName}".($price ? " from {$price} per day." : '.').($fuelType !== '' ? " Fuel type: {$fuelType}." : ''));
    }

    private static function fallbackReservationTitle(string $pageKey, string $reservationNumber): string
    {
        return match ($pageKey) {
            'booking_checkout' => ((string) app()->getLocale() === 'ar'
                ? "إتمام دفع الحجز {$reservationNumber}"
                : "Checkout Reservation {$reservationNumber}"),
            'booking_confirmation' => ((string) app()->getLocale() === 'ar'
                ? "تأكيد الحجز {$reservationNumber}"
                : "Reservation {$reservationNumber} Confirmed"),
            default => $reservationNumber,
        };
    }

    private static function fallbackReservationDescription(string $pageKey, string $siteName, Reservation $reservation): string
    {
        $carName = $reservation->car ? self::carName($reservation->car) : '';
        $number = trim((string) $reservation->reservation_number);

        if ((string) app()->getLocale() === 'ar') {
            return $pageKey === 'booking_checkout'
                ? "أكمل دفع الحجز {$number} لسيارة {$carName} مع {$siteName}."
                : "راجع تفاصيل الحجز {$number} لسيارة {$carName} مع {$siteName}.";
        }

        return $pageKey === 'booking_checkout'
            ? "Complete payment for reservation {$number} for {$carName} with {$siteName}."
            : "Review reservation {$number} details for {$carName} with {$siteName}.";
    }

    private static function carName(Car $car): string
    {
        return trim(implode(' ', array_filter([
            $car->year,
            $car->make,
            $car->model,
        ], static fn ($value) => $value !== null && $value !== '')));
    }

    private static function cleanUrl(mixed $value, ?string $fallback = null): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value !== '') {
            return $value;
        }

        $fallback = trim((string) ($fallback ?? ''));

        return $fallback !== '' ? $fallback : null;
    }

    private static function cleanRobots(mixed $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        return $value !== '' ? $value : $fallback;
    }

    private static function resolvePageRobots(array $pageSettings, array $seoSettings, string $fallback): string
    {
        $pageRobots = self::cleanRobots(data_get($pageSettings, 'robots'), '');
        if ($pageRobots !== '') {
            return $pageRobots;
        }

        return self::cleanRobots(data_get($seoSettings, 'defaults.robots'), $fallback);
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private static function pageSchemas(?Tenant $tenant, array $settings, string $pageKey, string $title, string $description, ?string $canonicalUrl): array
    {
        $siteName = self::siteName($tenant, $settings);
        $siteUrl = rtrim((string) config('app.url'), '/');
        $contactPhone = trim((string) data_get($settings, 'contact.phone', ''));
        $contactEmail = trim((string) data_get($settings, 'contact.email', ''));
        $contactAddress = self::localized(data_get($settings, 'contact.address'));
        $logoUrl = self::cleanUrl($settings['logo_url'] ?? null);

        $organization = array_filter([
            '@context' => 'https://schema.org',
            '@type' => $tenant ? 'AutoRental' : 'Organization',
            'name' => $siteName,
            'url' => $siteUrl,
            'logo' => $logoUrl,
            'description' => $description,
            'email' => $contactEmail !== '' ? $contactEmail : null,
            'telephone' => $contactPhone !== '' ? $contactPhone : null,
            'address' => $contactAddress
                ? [
                    '@type' => 'PostalAddress',
                    'streetAddress' => $contactAddress,
                ]
                : null,
        ], static fn ($value) => $value !== null && $value !== '');

        $pageType = match ($pageKey) {
            'home' => 'WebSite',
            'fleet' => 'CollectionPage',
            'about' => 'AboutPage',
            'contact' => 'ContactPage',
            'booking_checkout' => 'CheckoutPage',
            default => 'WebPage',
        };

        $pageSchema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => $pageType,
            'name' => $title,
            'description' => $description,
            'url' => $canonicalUrl ?: url()->current(),
            'inLanguage' => (string) app()->getLocale(),
            'publisher' => [
                '@type' => $tenant ? 'AutoRental' : 'Organization',
                'name' => $siteName,
            ],
        ], static fn ($value) => $value !== null && $value !== '');

        return [$organization, $pageSchema, self::staticBreadcrumbSchema($pageKey, $title, $canonicalUrl)];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private static function carSchemas(?Tenant $tenant, array $settings, Car $car, string $title, string $description, ?string $canonicalUrl): array
    {
        $baseSchemas = array_slice(self::pageSchemas($tenant, $settings, 'car', $title, $description, $canonicalUrl), 0, 2);

        $productSchema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Product',
            'name' => self::carName($car),
            'description' => $description,
            'image' => $car->image_url ?: null,
            'brand' => $car->make ? [
                '@type' => 'Brand',
                'name' => $car->make,
            ] : null,
            'model' => $car->model ?: null,
            'vehicleModelDate' => $car->year ?: null,
            'offers' => array_filter([
                '@type' => 'Offer',
                'price' => is_numeric($car->price_per_day) ? (float) $car->price_per_day : null,
                'priceCurrency' => (string) config('app.currency_code', 'USD'),
                'availability' => 'https://schema.org/InStock',
                'url' => $canonicalUrl ?: url()->current(),
            ], static fn ($value) => $value !== null && $value !== ''),
        ], static fn ($value) => $value !== null && $value !== '');

        return [...$baseSchemas, self::carBreadcrumbSchema($canonicalUrl, self::carName($car)), $productSchema];
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<int, array<string, mixed>>
     */
    private static function reservationSchemas(?Tenant $tenant, array $settings, Reservation $reservation, string $pageKey, string $title, string $description, ?string $canonicalUrl): array
    {
        $baseSchemas = array_slice(self::pageSchemas($tenant, $settings, $pageKey, $title, $description, $canonicalUrl), 0, 2);
        $car = $reservation->car;

        $reservationSchema = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'Reservation',
            'reservationNumber' => $reservation->reservation_number,
            'reservationStatus' => trim((string) $reservation->status),
            'underName' => $reservation->user?->name ? [
                '@type' => 'Person',
                'name' => $reservation->user->name,
            ] : null,
            'reservationFor' => $car ? [
                '@type' => 'Car',
                'name' => self::carName($car),
            ] : null,
            'startTime' => optional($reservation->start_date)->toDateString(),
            'endTime' => optional($reservation->end_date)->toDateString(),
            'totalPrice' => is_numeric($reservation->total_amount) ? (float) $reservation->total_amount : null,
            'priceCurrency' => (string) config('app.currency_code', 'USD'),
        ], static fn ($value) => $value !== null && $value !== '');

        return [...$baseSchemas, self::reservationBreadcrumbSchema($pageKey, $canonicalUrl, $title), $reservationSchema];
    }

    private static function staticBreadcrumbSchema(string $pageKey, string $title, ?string $canonicalUrl): array
    {
        $items = [
            [
                '@type' => 'ListItem',
                'position' => 1,
                'name' => self::fallbackLabel('home'),
                'item' => url('/'),
            ],
        ];

        if ($pageKey !== 'home') {
            $items[] = [
                '@type' => 'ListItem',
                'position' => 2,
                'name' => $title,
                'item' => $canonicalUrl ?: url()->current(),
            ];
        }

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => $items,
        ];
    }

    private static function carBreadcrumbSchema(?string $canonicalUrl, string $carName): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => self::fallbackLabel('home'),
                    'item' => url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => self::fallbackLabel('fleet'),
                    'item' => url('/fleet'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $carName,
                    'item' => $canonicalUrl ?: url()->current(),
                ],
            ],
        ];
    }

    private static function reservationBreadcrumbSchema(string $pageKey, ?string $canonicalUrl, string $title): array
    {
        $checkoutLabel = $pageKey === 'booking_checkout'
            ? self::fallbackLabel('booking_checkout')
            : self::fallbackLabel('booking_confirmation');

        return [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => self::fallbackLabel('home'),
                    'item' => url('/'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => $checkoutLabel,
                    'item' => $canonicalUrl ?: url()->current(),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $title,
                    'item' => $canonicalUrl ?: url()->current(),
                ],
            ],
        ];
    }

    /**
     * @param  array<int, string>  $enabledLocales
     * @return array<int, array{locale: string, url: string}>
     */
    private static function alternateUrls(array $enabledLocales): array
    {
        $alternates = [];

        foreach ($enabledLocales as $locale) {
            $url = LaravelLocalization::getLocalizedURL($locale, null, [], true);

            if (is_string($url) && trim($url) !== '') {
                $alternates[] = [
                    'locale' => $locale,
                    'url' => $url,
                ];
            }
        }

        $defaultUrl = url()->current();

        if (is_string($defaultUrl) && trim($defaultUrl) !== '') {
            $alternates[] = [
                'locale' => 'x-default',
                'url' => $defaultUrl,
            ];
        }

        return $alternates;
    }
}
