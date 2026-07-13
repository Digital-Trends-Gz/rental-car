<?php

namespace App\Support;

use App\Models\Tenant;

class CurrencyCatalog
{
    /**
     * @return array<int, array{code:string,abbreviation:string,name:string,currency:string,symbol:string,icon:string,label:string}>
     */
    public static function all(?string $locale = null): array
    {
        $locale = self::normalizeLocale($locale);

        $currencies = [];

        foreach (self::currencyNames() as $code => $defaultName) {
            $localized = self::localizedCurrencyData($code, $locale);
            $name = $localized['name'] ?: $defaultName;
            $symbol = $localized['symbol'] ?: $code;

            $currencies[] = [
                'code' => $code,
                'abbreviation' => $code,
                'name' => $name,
                'currency' => $name,
                'symbol' => $symbol,
                'icon' => $symbol,
                'label' => $code.' - '.$name,
            ];
        }

        return $currencies;
    }

    public static function normalizeCode(mixed $value, ?string $fallback = null): string
    {
        $code = strtoupper(trim((string) ($value ?? '')));

        if (preg_match('/^[A-Z]{3}$/', $code) === 1) {
            return $code;
        }

        $fallback = strtoupper(trim((string) ($fallback ?: config('app.currency_code', 'USD'))));

        return preg_match('/^[A-Z]{3}$/', $fallback) === 1 ? $fallback : 'USD';
    }

    /**
     * @return array{code:string,abbreviation:string,name:string,currency:string,symbol:string,icon:string,label:string}
     */
    public static function find(mixed $code): array
    {
        $normalized = self::normalizeCode($code);

        foreach (self::all() as $currency) {
            if ($currency['code'] === $normalized) {
                return $currency;
            }
        }

        return [
            'code' => $normalized,
            'abbreviation' => $normalized,
            'name' => $normalized,
            'currency' => $normalized,
            'symbol' => $normalized,
            'icon' => $normalized,
            'label' => $normalized,
        ];
    }

    public static function codeForTenant(?Tenant $tenant, mixed $fallback = null): string
    {
        $code = null;

        if ($tenant) {
            $tenant->loadMissing('siteSetting');
            $code = data_get($tenant->siteSetting?->market_location, 'currency_code');
        }

        return self::normalizeCode($code ?: $tenant?->stripe_currency ?: $fallback);
    }

    public static function codeForTenantId(mixed $tenantId, mixed $fallback = null): string
    {
        $tenantId = (int) ($tenantId ?? 0);

        if ($tenantId <= 0) {
            return self::normalizeCode($fallback);
        }

        $tenant = Tenant::query()
            ->with('siteSetting')
            ->find($tenantId);

        return self::codeForTenant($tenant, $fallback);
    }

    /**
     * @return array{code:string,abbreviation:string,name:string,currency:string,symbol:string,icon:string,label:string}
     */
    public static function forTenant(?Tenant $tenant, mixed $fallback = null): array
    {
        return self::find(self::codeForTenant($tenant, $fallback));
    }

    private static function normalizeLocale(?string $locale): string
    {
        $locale = trim(str_replace('_', '-', (string) ($locale ?: app()->getLocale() ?: 'en')));

        if ($locale === '') {
            return 'en';
        }

        if (str_contains($locale, ',')) {
            $locale = trim(explode(',', $locale)[0]);
        }

        if (str_contains($locale, ';')) {
            $locale = trim(explode(';', $locale)[0]);
        }

        return $locale !== '' ? $locale : 'en';
    }

    /**
     * @return array{symbol: string|null, name: string|null}
     */
    private static function localizedCurrencyData(string $code, string $locale): array
    {
        if (!class_exists(\ResourceBundle::class)) {
            return ['symbol' => null, 'name' => null];
        }

        try {
            $bundle = \ResourceBundle::create($locale, 'ICUDATA-curr');
            $currencies = $bundle instanceof \ResourceBundle ? $bundle->get('Currencies') : null;
            $currency = $currencies instanceof \ResourceBundle ? $currencies->get($code) : null;

            if (!$currency instanceof \ResourceBundle) {
                return ['symbol' => null, 'name' => null];
            }

            $symbol = self::cleanCurrencyText((string) ($currency->get(0) ?? ''));
            $name = self::cleanCurrencyText((string) ($currency->get(1) ?? ''));

            return [
                'symbol' => $symbol !== '' ? $symbol : null,
                'name' => $name !== '' ? $name : null,
            ];
        } catch (\Throwable) {
            return ['symbol' => null, 'name' => null];
        }
    }

    private static function cleanCurrencyText(string $value): string
    {
        $value = preg_replace('/\p{Cf}/u', '', $value) ?? $value;

        return trim($value);
    }

    /**
     * @return array<string, string>
     */
    private static function currencyNames(): array
    {
        return [
            'AED' => 'United Arab Emirates Dirham',
            'AFN' => 'Afghan Afghani',
            'ALL' => 'Albanian Lek',
            'AMD' => 'Armenian Dram',
            'ANG' => 'Netherlands Antillean Guilder',
            'AOA' => 'Angolan Kwanza',
            'ARS' => 'Argentine Peso',
            'AUD' => 'Australian Dollar',
            'AWG' => 'Aruban Florin',
            'AZN' => 'Azerbaijani Manat',
            'BAM' => 'Bosnia-Herzegovina Convertible Mark',
            'BBD' => 'Barbadian Dollar',
            'BDT' => 'Bangladeshi Taka',
            'BGN' => 'Bulgarian Lev',
            'BHD' => 'Bahraini Dinar',
            'BIF' => 'Burundian Franc',
            'BMD' => 'Bermudan Dollar',
            'BND' => 'Brunei Dollar',
            'BOB' => 'Bolivian Boliviano',
            'BRL' => 'Brazilian Real',
            'BSD' => 'Bahamian Dollar',
            'BTN' => 'Bhutanese Ngultrum',
            'BWP' => 'Botswanan Pula',
            'BYN' => 'Belarusian Ruble',
            'BZD' => 'Belize Dollar',
            'CAD' => 'Canadian Dollar',
            'CDF' => 'Congolese Franc',
            'CHF' => 'Swiss Franc',
            'CLP' => 'Chilean Peso',
            'CNY' => 'Chinese Yuan',
            'COP' => 'Colombian Peso',
            'CRC' => 'Costa Rican Colon',
            'CUC' => 'Cuban Convertible Peso',
            'CUP' => 'Cuban Peso',
            'CVE' => 'Cape Verdean Escudo',
            'CZK' => 'Czech Koruna',
            'DJF' => 'Djiboutian Franc',
            'DKK' => 'Danish Krone',
            'DOP' => 'Dominican Peso',
            'DZD' => 'Algerian Dinar',
            'EGP' => 'Egyptian Pound',
            'ERN' => 'Eritrean Nakfa',
            'ETB' => 'Ethiopian Birr',
            'EUR' => 'Euro',
            'FJD' => 'Fijian Dollar',
            'FKP' => 'Falkland Islands Pound',
            'GBP' => 'British Pound',
            'GEL' => 'Georgian Lari',
            'GHS' => 'Ghanaian Cedi',
            'GIP' => 'Gibraltar Pound',
            'GMD' => 'Gambian Dalasi',
            'GNF' => 'Guinean Franc',
            'GTQ' => 'Guatemalan Quetzal',
            'GYD' => 'Guyanaese Dollar',
            'HKD' => 'Hong Kong Dollar',
            'HNL' => 'Honduran Lempira',
            'HRK' => 'Croatian Kuna',
            'HTG' => 'Haitian Gourde',
            'HUF' => 'Hungarian Forint',
            'IDR' => 'Indonesian Rupiah',
            'ILS' => 'Israeli New Shekel',
            'INR' => 'Indian Rupee',
            'IQD' => 'Iraqi Dinar',
            'IRR' => 'Iranian Rial',
            'ISK' => 'Icelandic Krona',
            'JMD' => 'Jamaican Dollar',
            'JOD' => 'Jordanian Dinar',
            'JPY' => 'Japanese Yen',
            'KES' => 'Kenyan Shilling',
            'KGS' => 'Kyrgystani Som',
            'KHR' => 'Cambodian Riel',
            'KMF' => 'Comorian Franc',
            'KPW' => 'North Korean Won',
            'KRW' => 'South Korean Won',
            'KWD' => 'Kuwaiti Dinar',
            'KYD' => 'Cayman Islands Dollar',
            'KZT' => 'Kazakhstani Tenge',
            'LAK' => 'Laotian Kip',
            'LBP' => 'Lebanese Pound',
            'LKR' => 'Sri Lankan Rupee',
            'LRD' => 'Liberian Dollar',
            'LSL' => 'Lesotho Loti',
            'LYD' => 'Libyan Dinar',
            'MAD' => 'Moroccan Dirham',
            'MDL' => 'Moldovan Leu',
            'MGA' => 'Malagasy Ariary',
            'MKD' => 'Macedonian Denar',
            'MMK' => 'Myanmar Kyat',
            'MNT' => 'Mongolian Tugrik',
            'MOP' => 'Macanese Pataca',
            'MRU' => 'Mauritanian Ouguiya',
            'MUR' => 'Mauritian Rupee',
            'MVR' => 'Maldivian Rufiyaa',
            'MWK' => 'Malawian Kwacha',
            'MXN' => 'Mexican Peso',
            'MYR' => 'Malaysian Ringgit',
            'MZN' => 'Mozambican Metical',
            'NAD' => 'Namibian Dollar',
            'NGN' => 'Nigerian Naira',
            'NIO' => 'Nicaraguan Cordoba',
            'NOK' => 'Norwegian Krone',
            'NPR' => 'Nepalese Rupee',
            'NZD' => 'New Zealand Dollar',
            'OMR' => 'Omani Rial',
            'PAB' => 'Panamanian Balboa',
            'PEN' => 'Peruvian Sol',
            'PGK' => 'Papua New Guinean Kina',
            'PHP' => 'Philippine Peso',
            'PKR' => 'Pakistani Rupee',
            'PLN' => 'Polish Zloty',
            'PYG' => 'Paraguayan Guarani',
            'QAR' => 'Qatari Riyal',
            'RON' => 'Romanian Leu',
            'RSD' => 'Serbian Dinar',
            'RUB' => 'Russian Ruble',
            'RWF' => 'Rwandan Franc',
            'SAR' => 'Saudi Riyal',
            'SBD' => 'Solomon Islands Dollar',
            'SCR' => 'Seychellois Rupee',
            'SDG' => 'Sudanese Pound',
            'SEK' => 'Swedish Krona',
            'SGD' => 'Singapore Dollar',
            'SHP' => 'St. Helena Pound',
            'SLE' => 'Sierra Leonean Leone',
            'SLL' => 'Sierra Leonean Leone (1964-2022)',
            'SOS' => 'Somali Shilling',
            'SRD' => 'Surinamese Dollar',
            'SSP' => 'South Sudanese Pound',
            'STN' => 'Sao Tome & Principe Dobra',
            'SVC' => 'Salvadoran Colon',
            'SYP' => 'Syrian Pound',
            'SZL' => 'Swazi Lilangeni',
            'THB' => 'Thai Baht',
            'TJS' => 'Tajikistani Somoni',
            'TMT' => 'Turkmenistani Manat',
            'TND' => 'Tunisian Dinar',
            'TOP' => 'Tongan Paʻanga',
            'TRY' => 'Turkish Lira',
            'TTD' => 'Trinidad & Tobago Dollar',
            'TWD' => 'New Taiwan Dollar',
            'TZS' => 'Tanzanian Shilling',
            'UAH' => 'Ukrainian Hryvnia',
            'UGX' => 'Ugandan Shilling',
            'USD' => 'US Dollar',
            'UYU' => 'Uruguayan Peso',
            'UZS' => 'Uzbekistani Som',
            'VES' => 'Venezuelan Bolivar',
            'VND' => 'Vietnamese Dong',
            'VUV' => 'Vanuatu Vatu',
            'WST' => 'Samoan Tala',
            'XAF' => 'Central African CFA Franc',
            'XCD' => 'East Caribbean Dollar',
            'XCG' => 'Caribbean Guilder',
            'XDR' => 'Special Drawing Rights',
            'XOF' => 'West African CFA Franc',
            'XPF' => 'CFP Franc',
            'XSU' => 'Sucre',
            'YER' => 'Yemeni Rial',
            'ZAR' => 'South African Rand',
            'ZMW' => 'Zambian Kwacha',
            'ZWG' => 'Zimbabwean Gold',
            'ZWL' => 'Zimbabwean Dollar (2009-2024)',
        ];
    }
}
