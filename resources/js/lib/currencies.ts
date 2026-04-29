export type CurrencyOption = {
  code: string;
  label: string;
};

const FALLBACK_CURRENCIES = [
  'USD',
  'EUR',
  'GBP',
  'AED',
  'SAR',
  'QAR',
  'KWD',
  'BHD',
  'OMR',
  'JOD',
  'EGP',
  'MAD',
  'TND',
  'DZD',
  'IQD',
  'TRY',
  'ILS',
  'CNY',
  'JPY',
  'HKD',
  'SGD',
  'AUD',
  'NZD',
  'CAD',
  'CHF',
  'SEK',
  'NOK',
  'DKK',
  'RUB',
  'INR',
  'PKR',
  'BDT',
  'LKR',
  'ZAR',
  'NGN',
  'KES',
  'UAH',
  'PLN',
  'CZK',
  'RON',
  'HUF',
  'MXN',
  'BRL',
  'ARS',
  'CLP',
  'COP',
];

function getSupportedCurrencyCodes(): string[] {
  const supportedValuesOf = (Intl as Intl & {
    supportedValuesOf?: (key: 'currency') => string[];
  }).supportedValuesOf;

  if (typeof supportedValuesOf === 'function') {
    return supportedValuesOf.call(Intl, 'currency');
  }

  return FALLBACK_CURRENCIES;
}

export function getCurrencyOptions(locale = 'en'): CurrencyOption[] {
  const displayNames =
    typeof Intl.DisplayNames === 'function'
      ? new Intl.DisplayNames([locale || 'en'], { type: 'currency' })
      : null;

  return Array.from(new Set(getSupportedCurrencyCodes()))
    .map((code) => {
      const name = displayNames?.of(code) ?? '';
      return {
        code,
        label: name && name !== code ? `${code} - ${name}` : code,
      };
    })
    .sort((a, b) => a.code.localeCompare(b.code));
}
