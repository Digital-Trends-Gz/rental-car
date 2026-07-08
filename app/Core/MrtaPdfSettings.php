<?php

namespace App\Core;

use App\Models\TenantSiteSetting;

class MrtaPdfSettings
{
    public static function defaults(): array
    {
        return [
            'primary_color' => '#f15a24',
            'oman_logo_url' => null,
            'rop_logo_url' => null,
            'liva_logo_url' => null,
            'liva_logo_text' => '',
            'liva_logo_ar' => '',
            'liva_contact_email' => '',
            'liva_contact_website' => '',
            'insurance_section_title_en' => 'For insurance use',
            'insurance_section_title_ar' => 'لاستعمال شركة التأمين',
            'footer_ar' => '',
            'footer_en' => '',
        ];
    }

    public static function normalize(mixed $settings): array
    {
        $settings = is_array($settings) ? $settings : [];
        $defaults = self::defaults();

        return [
            'primary_color' => self::normalizeHexColor($settings['primary_color'] ?? null, $defaults['primary_color']),
            'oman_logo_url' => self::nullableUrl($settings['oman_logo_url'] ?? null),
            'rop_logo_url' => self::nullableUrl($settings['rop_logo_url'] ?? null),
            'liva_logo_url' => self::nullableUrl($settings['liva_logo_url'] ?? null),
            'liva_logo_text' => self::legacyDefaultValue($settings['liva_logo_text'] ?? null, 'liva', $defaults['liva_logo_text']),
            'liva_logo_ar' => self::legacyDefaultValue($settings['liva_logo_ar'] ?? null, 'ليفا للتأمين', $defaults['liva_logo_ar']),
            'liva_contact_email' => self::legacyDefaultValue($settings['liva_contact_email'] ?? null, 'info.om@livainsurance.com', $defaults['liva_contact_email']),
            'liva_contact_website' => self::legacyDefaultValue($settings['liva_contact_website'] ?? null, 'www.livainsurance.com', $defaults['liva_contact_website']),
            'insurance_section_title_en' => self::legacyDefaultValue($settings['insurance_section_title_en'] ?? null, 'For the use of Liva Insurance', $defaults['insurance_section_title_en']),
            'insurance_section_title_ar' => self::legacyDefaultValue($settings['insurance_section_title_ar'] ?? null, 'لاستعمال شركة ليفا للتأمين', $defaults['insurance_section_title_ar']),
            'footer_ar' => self::legacyDefaultValue($settings['footer_ar'] ?? null, 'شركة ليفا للتأمين، ص.ب: ١٤٦٣، الرمز البريدي: ١١٢، روي، سلطنة عمان، هاتف: ٢٤٧٦٦٨٠٠، فاكس: ٢٤٧٩٣٥٨٢، س.ت: ١٧٥٤٨٠٧', $defaults['footer_ar']),
            'footer_en' => self::legacyDefaultValue($settings['footer_en'] ?? null, 'Liva Insurance, P.O. Box: 1463, Ruwi, PC: 112, Sultanate of Oman, Tel.:24766800, Fax: 24793582, C.R. No.: 1754807', $defaults['footer_en']),
        ];
    }

    public static function forTenantSiteSetting(?TenantSiteSetting $siteSetting): array
    {
        $settings = self::normalize($siteSetting?->mrta_pdf);

        if (! $siteSetting) {
            return $settings;
        }

        $collectionMap = [
            'mrta_oman_logo' => 'oman_logo_url',
            'mrta_rop_logo' => 'rop_logo_url',
            'mrta_liva_logo' => 'liva_logo_url',
        ];

        foreach ($collectionMap as $collection => $key) {
            $file = $siteSetting->relationLoaded('files')
                ? $siteSetting->files->where('collection', $collection)->sortByDesc('id')->first()
                : $siteSetting->files()->where('collection', $collection)->latest('id')->first();

            if ($file && $file->path) {
                $settings[$key] = TenantSiteSetting::publicUrlFromPath($file->path);
            }
        }

        return $settings;
    }

    private static function normalizeHexColor(mixed $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value !== '' && preg_match('/^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/', $value)) {
            return strtolower($value);
        }

        return strtolower($fallback);
    }

    private static function nullableUrl(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }

    private static function stringValue(mixed $value, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? $fallback : $value;
    }

    private static function legacyDefaultValue(mixed $value, string $legacy, string $fallback): string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '' || $value === $legacy || strcasecmp($value, $legacy) === 0) {
            return $fallback;
        }

        return $value;
    }
}
