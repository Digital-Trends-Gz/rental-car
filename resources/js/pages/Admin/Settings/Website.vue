<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import SearchableSelect from '@/components/SearchableSelect.vue';
import { getCurrencyOptions } from '@/lib/currencies';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { ChevronDown } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

type LocalizedText = { en: string | null; ar: string | null };
type UploadedFilePreview = { id: number; url: string };
type PdfTemplateOption = {
    value: string;
    label: LocalizedText;
    description: LocalizedText;
};
type CountryOption = {
    iso2: string;
    name_en: string;
    name_ar: string;
    dial_code: string;
};
type CityOption = {
    value: string;
    label: string;
};

const props = defineProps<{
    tenant: {
        id: number;
        name: string;
        slug: string;
    };
    settings: {
        site_name: string | null;
        logo_url: string | null;
        favicon_url: string | null;
        primary_color: string;
        secondary_color: string;
        market_location?: {
            country_code?: string | null;
            country_name?: string | null;
            region?: string | null;
            city?: string | null;
            market_area?: string | null;
            timezone?: string | null;
            currency_code?: string | null;
            enabled_currency_codes?: string[] | null;
        };
        tax_percentage: number;
        hero: {
            title: LocalizedText;
            description: LocalizedText;
            button_text: LocalizedText;
            button_link: string | null;
            image_url?: string | null;
        };
        about: {
            title: LocalizedText;
            subtitle: LocalizedText;
            story_title: LocalizedText;
            story_p1: LocalizedText;
            story_p2: LocalizedText;
            mission_title: LocalizedText;
            mission_subtitle: LocalizedText;
            cta_title: LocalizedText;
            cta_subtitle: LocalizedText;
            cta_browse_text: LocalizedText;
            cta_contact_text: LocalizedText;
            team_images?: {
                sarah?: string | null;
                michael?: string | null;
                emily?: string | null;
            };
        };
        contact: {
            phone: string | null;
            email: string | null;
            address: LocalizedText;
        };
        contact_page: {
            title: LocalizedText;
            subtitle: LocalizedText;
            form_title: LocalizedText;
            info_title: LocalizedText;
            hours: LocalizedText;
            quick_links_title: LocalizedText;
        };
        enabled_locales?: string[];
        seo: {
            defaults: {
                title_suffix: LocalizedText;
                default_description: LocalizedText;
                og_image: string | null;
                robots: string | null;
            };
            pages: {
                home: {
                    title: LocalizedText;
                    description: LocalizedText;
                    canonical_url: string | null;
                };
                fleet: {
                    title: LocalizedText;
                    description: LocalizedText;
                    canonical_url: string | null;
                };
                about: {
                    title: LocalizedText;
                    description: LocalizedText;
                    canonical_url: string | null;
                };
                contact: {
                    title: LocalizedText;
                    description: LocalizedText;
                    canonical_url: string | null;
                };
                car: {
                    title: LocalizedText;
                    description: LocalizedText;
                    canonical_url: string | null;
                };
                booking_checkout: {
                    title: LocalizedText;
                    description: LocalizedText;
                    canonical_url: string | null;
                };
                booking_confirmation: {
                    title: LocalizedText;
                    description: LocalizedText;
                    canonical_url: string | null;
                };
            };
        };
        pdf_header: {
            company_name: LocalizedText;
            cr_number: string | null;
            po_box: string | null;
            pc: string | null;
            country: LocalizedText;
            gsm_1: string | null;
            gsm_2: string | null;
            gsm_3: string | null;
            registry_label: LocalizedText;
        };
        pdf_templates: {
            contract: string;
        };
        footer: {
            description: LocalizedText;
        };
    };
    marketCountryOptions: CountryOption[];
    marketCityOptionsByCountry: Record<string, CityOption[]>;
    pdfTemplateOptions: PdfTemplateOption[];
    logoFiles: UploadedFilePreview[];
    faviconFiles: UploadedFilePreview[];
    seoOgImageFiles?: UploadedFilePreview[];
    heroImageFiles?: UploadedFilePreview[];
    aboutTeamImageFiles?: {
        sarah?: UploadedFilePreview[];
        michael?: UploadedFilePreview[];
        emily?: UploadedFilePreview[];
    };
    actions: {
        update: string;
        website?: string;
        seo_edit?: string;
        seo_audit?: string;
    };
}>();

const { locale } = useTrans();
const page = usePage<any>();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);
const selectClass = 'h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm';
type ContentSectionKey = 'hero' | 'about' | 'pdfHeader' | 'contactPage' | 'contactFooter';
const contentSectionOpen = ref<Record<ContentSectionKey, boolean>>({
    hero: true,
    about: false,
    pdfHeader: false,
    contactPage: false,
    contactFooter: false,
});

function toggleContentSection(section: ContentSectionKey) {
    contentSectionOpen.value[section] = !contentSectionOpen.value[section];
}

const marketCountryOptions = computed(() =>
    [...(props.marketCountryOptions || [])].sort((a, b) => localizedCountryName(a).localeCompare(localizedCountryName(b))),
);
const fallbackMarketAreas = computed(() => [
    localize('Airport', 'المطار'),
    localize('City center', 'وسط المدينة'),
    localize('Tourism area', 'منطقة سياحية'),
    localize('Business district', 'منطقة أعمال'),
    localize('Hotels area', 'منطقة الفنادق'),
    localize('All service areas', 'كل مناطق الخدمة'),
]);
const marketTimezoneOptions = computed(() => {
    const supportedValuesOf = (Intl as typeof Intl & { supportedValuesOf?: (key: 'timeZone') => string[] }).supportedValuesOf;
    const values = typeof supportedValuesOf === 'function'
        ? supportedValuesOf.call(Intl, 'timeZone')
        : ['Asia/Muscat', 'Asia/Dubai', 'Asia/Hebron', 'Asia/Riyadh', 'Asia/Qatar', 'America/New_York', 'Europe/London'];

    return Array.from(new Set(values)).sort((a, b) => a.localeCompare(b));
});
const marketCurrencyOptions = computed(() => getCurrencyOptions(locale.value || 'en'));
const marketCurrencySelectOptions = computed(() =>
    marketCurrencyOptions.value.map((currency) => ({
        value: currency.code,
        label: currency.label,
    })),
);
const enabledCurrencySearch = ref('');
const filteredEnabledCurrencyOptions = computed(() => {
    const term = enabledCurrencySearch.value.trim().toLowerCase();

    if (!term) {
        return marketCurrencyOptions.value;
    }

    return marketCurrencyOptions.value.filter((currency) =>
        `${currency.code} ${currency.label}`.toLowerCase().includes(term),
    );
});

function localizedCountryName(country: CountryOption): string {
    return locale.value === 'ar' ? country.name_ar || country.name_en : country.name_en;
}

function isCurrencyEnabled(code: string): boolean {
    return form.market_location.enabled_currency_codes.includes(code);
}

function toggleEnabledCurrency(code: string) {
    const normalized = String(code || '').toUpperCase();

    if (!normalized) {
        return;
    }

    if (normalized === form.market_location.currency_code) {
        if (!form.market_location.enabled_currency_codes.includes(normalized)) {
            form.market_location.enabled_currency_codes.push(normalized);
        }

        return;
    }

    if (form.market_location.enabled_currency_codes.includes(normalized)) {
        form.market_location.enabled_currency_codes = form.market_location.enabled_currency_codes.filter((item) => item !== normalized);
        return;
    }

    form.market_location.enabled_currency_codes = [...form.market_location.enabled_currency_codes, normalized];
}

function enableAllCurrencies() {
    form.market_location.enabled_currency_codes = marketCurrencyOptions.value.map((currency) => currency.code);
}

function clearEnabledCurrencies() {
    form.market_location.enabled_currency_codes = form.market_location.currency_code ? [form.market_location.currency_code] : [];
}

function currencyOptionLabel(code: string): string {
    return marketCurrencyOptions.value.find((currency) => currency.code === code)?.label ?? code;
}

const form = useForm({
    site_name: props.settings.site_name ?? '',
    logo_url: props.settings.logo_url ?? '',
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
    favicon_url: props.settings.favicon_url ?? '',
    favicon_temp_folders: [] as string[],
    favicon_removed_files: [] as number[],
    hero_image_temp_folders: [] as string[],
    hero_image_removed_files: [] as number[],
    about_team_sarah_image_temp_folders: [] as string[],
    about_team_sarah_image_removed_files: [] as number[],
    about_team_michael_image_temp_folders: [] as string[],
    about_team_michael_image_removed_files: [] as number[],
    about_team_emily_image_temp_folders: [] as string[],
    about_team_emily_image_removed_files: [] as number[],
    seo_og_image_temp_folders: [] as string[],
    seo_og_image_removed_files: [] as number[],
    primary_color: props.settings.primary_color || '#f97316',
    secondary_color: props.settings.secondary_color || '#ea580c',
    market_location: {
        country_code: props.settings.market_location?.country_code ?? '',
        country_name: props.settings.market_location?.country_name ?? '',
        region: props.settings.market_location?.region ?? '',
        city: props.settings.market_location?.city ?? '',
        market_area: props.settings.market_location?.market_area ?? '',
        timezone: props.settings.market_location?.timezone ?? '',
        currency_code: props.settings.market_location?.currency_code ?? '',
        enabled_currency_codes: props.settings.market_location?.enabled_currency_codes ?? [],
    },
    tax_percentage: props.settings.tax_percentage ?? 7,
    hero: {
        title: {
            en: props.settings.hero?.title?.en ?? '',
            ar: props.settings.hero?.title?.ar ?? '',
        },
        description: {
            en: props.settings.hero?.description?.en ?? '',
            ar: props.settings.hero?.description?.ar ?? '',
        },
        button_text: {
            en: props.settings.hero?.button_text?.en ?? '',
            ar: props.settings.hero?.button_text?.ar ?? '',
        },
        button_link: props.settings.hero?.button_link ?? '',
        image_url: props.settings.hero?.image_url ?? '',
    },
    about: {
        title: {
            en: props.settings.about?.title?.en ?? '',
            ar: props.settings.about?.title?.ar ?? '',
        },
        subtitle: {
            en: props.settings.about?.subtitle?.en ?? '',
            ar: props.settings.about?.subtitle?.ar ?? '',
        },
        story_title: {
            en: props.settings.about?.story_title?.en ?? '',
            ar: props.settings.about?.story_title?.ar ?? '',
        },
        story_p1: {
            en: props.settings.about?.story_p1?.en ?? '',
            ar: props.settings.about?.story_p1?.ar ?? '',
        },
        story_p2: {
            en: props.settings.about?.story_p2?.en ?? '',
            ar: props.settings.about?.story_p2?.ar ?? '',
        },
        mission_title: {
            en: props.settings.about?.mission_title?.en ?? '',
            ar: props.settings.about?.mission_title?.ar ?? '',
        },
        mission_subtitle: {
            en: props.settings.about?.mission_subtitle?.en ?? '',
            ar: props.settings.about?.mission_subtitle?.ar ?? '',
        },
        cta_title: {
            en: props.settings.about?.cta_title?.en ?? '',
            ar: props.settings.about?.cta_title?.ar ?? '',
        },
        cta_subtitle: {
            en: props.settings.about?.cta_subtitle?.en ?? '',
            ar: props.settings.about?.cta_subtitle?.ar ?? '',
        },
        cta_browse_text: {
            en: props.settings.about?.cta_browse_text?.en ?? '',
            ar: props.settings.about?.cta_browse_text?.ar ?? '',
        },
        cta_contact_text: {
            en: props.settings.about?.cta_contact_text?.en ?? '',
            ar: props.settings.about?.cta_contact_text?.ar ?? '',
        },
        team_images: {
            sarah: props.settings.about?.team_images?.sarah ?? '',
            michael: props.settings.about?.team_images?.michael ?? '',
            emily: props.settings.about?.team_images?.emily ?? '',
        },
    },
    contact: {
        phone: props.settings.contact?.phone ?? '',
        email: props.settings.contact?.email ?? '',
        address: {
            en: props.settings.contact?.address?.en ?? '',
            ar: props.settings.contact?.address?.ar ?? '',
        },
    },
    contact_page: {
        title: {
            en: props.settings.contact_page?.title?.en ?? '',
            ar: props.settings.contact_page?.title?.ar ?? '',
        },
        subtitle: {
            en: props.settings.contact_page?.subtitle?.en ?? '',
            ar: props.settings.contact_page?.subtitle?.ar ?? '',
        },
        form_title: {
            en: props.settings.contact_page?.form_title?.en ?? '',
            ar: props.settings.contact_page?.form_title?.ar ?? '',
        },
        info_title: {
            en: props.settings.contact_page?.info_title?.en ?? '',
            ar: props.settings.contact_page?.info_title?.ar ?? '',
        },
        hours: {
            en: props.settings.contact_page?.hours?.en ?? '',
            ar: props.settings.contact_page?.hours?.ar ?? '',
        },
        quick_links_title: {
            en: props.settings.contact_page?.quick_links_title?.en ?? '',
            ar: props.settings.contact_page?.quick_links_title?.ar ?? '',
        },
    },
    seo: {
        defaults: {
            title_suffix: {
                en: props.settings.seo?.defaults?.title_suffix?.en ?? '',
                ar: props.settings.seo?.defaults?.title_suffix?.ar ?? '',
            },
            default_description: {
                en: props.settings.seo?.defaults?.default_description?.en ?? '',
                ar: props.settings.seo?.defaults?.default_description?.ar ?? '',
            },
            og_image: props.settings.seo?.defaults?.og_image ?? '',
            robots: props.settings.seo?.defaults?.robots ?? 'index,follow',
        },
        pages: {
            home: {
                title: {
                    en: props.settings.seo?.pages?.home?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.home?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.home?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.home?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.home?.canonical_url ?? '',
            },
            fleet: {
                title: {
                    en: props.settings.seo?.pages?.fleet?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.fleet?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.fleet?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.fleet?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.fleet?.canonical_url ?? '',
            },
            about: {
                title: {
                    en: props.settings.seo?.pages?.about?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.about?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.about?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.about?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.about?.canonical_url ?? '',
            },
            contact: {
                title: {
                    en: props.settings.seo?.pages?.contact?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.contact?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.contact?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.contact?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.contact?.canonical_url ?? '',
            },
            car: {
                title: {
                    en: props.settings.seo?.pages?.car?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.car?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.car?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.car?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.car?.canonical_url ?? '',
            },
            booking_checkout: {
                title: {
                    en: props.settings.seo?.pages?.booking_checkout?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.booking_checkout?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.booking_checkout?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.booking_checkout?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.booking_checkout?.canonical_url ?? '',
            },
            booking_confirmation: {
                title: {
                    en: props.settings.seo?.pages?.booking_confirmation?.title?.en ?? '',
                    ar: props.settings.seo?.pages?.booking_confirmation?.title?.ar ?? '',
                },
                description: {
                    en: props.settings.seo?.pages?.booking_confirmation?.description?.en ?? '',
                    ar: props.settings.seo?.pages?.booking_confirmation?.description?.ar ?? '',
                },
                canonical_url: props.settings.seo?.pages?.booking_confirmation?.canonical_url ?? '',
            },
        },
    },
    pdf_header: {
        company_name: {
            en: props.settings.pdf_header?.company_name?.en ?? '',
            ar: props.settings.pdf_header?.company_name?.ar ?? '',
        },
        cr_number: props.settings.pdf_header?.cr_number ?? '',
        po_box: props.settings.pdf_header?.po_box ?? '',
        pc: props.settings.pdf_header?.pc ?? '',
        country: {
            en: props.settings.pdf_header?.country?.en ?? '',
            ar: props.settings.pdf_header?.country?.ar ?? '',
        },
        gsm_1: props.settings.pdf_header?.gsm_1 ?? '',
        gsm_2: props.settings.pdf_header?.gsm_2 ?? '',
        gsm_3: props.settings.pdf_header?.gsm_3 ?? '',
        registry_label: {
            en: props.settings.pdf_header?.registry_label?.en ?? '',
            ar: props.settings.pdf_header?.registry_label?.ar ?? '',
        },
    },
    pdf_templates: {
        contract: props.settings.pdf_templates?.contract ?? 'classic',
    },
    footer: {
        description: {
            en: props.settings.footer?.description?.en ?? '',
            ar: props.settings.footer?.description?.ar ?? '',
        },
    },
});

watch(
    () => form.market_location.currency_code,
    (code) => {
        const baseCode = String(code || '').toUpperCase();
        const enabled = Array.from(new Set(form.market_location.enabled_currency_codes.map((item) => String(item || '').toUpperCase()).filter(Boolean)));

        if (baseCode && !enabled.includes(baseCode)) {
            enabled.unshift(baseCode);
        }

        form.market_location.enabled_currency_codes = enabled;
    },
    { immediate: true },
);

const selectedMarketCountry = computed(() => {
    const countryName = String(form.market_location.country_name || '');
    const countryCode = String(form.market_location.country_code || '').toUpperCase();

    return marketCountryOptions.value.find((country) => country.name_en === countryName || country.name_ar === countryName || country.iso2 === countryCode) ?? null;
});
const marketCityOptions = computed(() => {
    const countryCode = selectedMarketCountry.value?.iso2 || String(form.market_location.country_code || '').toUpperCase();
    const options = props.marketCityOptionsByCountry?.[countryCode] ?? [];
    const currentCity = String(form.market_location.city || '');

    if (currentCity && !options.some((option) => option.value === currentCity)) {
        return [{ value: currentCity, label: currentCity }, ...options];
    }

    return options;
});
const marketRegionOptions = computed(() => {
    const currentRegion = String(form.market_location.region || '');
    const fallback = selectedMarketCountry.value
        ? [{ value: localizedCountryName(selectedMarketCountry.value), label: localizedCountryName(selectedMarketCountry.value) }]
        : [];

    if (currentRegion && !fallback.some((option) => option.value === currentRegion)) {
        return [{ value: currentRegion, label: currentRegion }, ...fallback];
    }

    return fallback;
});
const marketAreaOptions = computed(() => {
    const currentArea = String(form.market_location.market_area || '');
    const options = fallbackMarketAreas.value.map((area) => ({ value: area, label: area }));

    if (currentArea && !options.some((option) => option.value === currentArea)) {
        return [{ value: currentArea, label: currentArea }, ...options];
    }

    return options;
});

function syncMarketCountry(country: CountryOption) {
    form.market_location.country_name = country.name_en;
    form.market_location.country_code = country.iso2;

    if (!marketRegionOptions.value.some((option) => option.value === form.market_location.region)) {
        form.market_location.region = marketRegionOptions.value[0]?.value ?? '';
    }

    if (!marketCityOptions.value.some((option) => option.value === form.market_location.city)) {
        form.market_location.city = marketCityOptions.value[0]?.value ?? '';
    }

    if (!marketAreaOptions.value.some((option) => option.value === form.market_location.market_area)) {
        form.market_location.market_area = marketAreaOptions.value[0]?.value ?? '';
    }
}

function handleMarketCountryChange() {
    const country = marketCountryOptions.value.find((option) => option.iso2 === form.market_location.country_code || option.name_en === form.market_location.country_name || option.name_ar === form.market_location.country_name);

    if (country) {
        syncMarketCountry(country);
    }
}

function handleMarketCountryCodeChange() {
    const country = marketCountryOptions.value.find((option) => option.iso2 === String(form.market_location.country_code || '').toUpperCase());

    if (country) {
        syncMarketCountry(country);
    }
}

const initialMarketCountry = marketCountryOptions.value.find((country) =>
    country.name_en === form.market_location.country_name ||
    country.name_ar === form.market_location.country_name ||
    country.iso2 === String(form.market_location.country_code || '').toUpperCase(),
);
if (initialMarketCountry) {
    syncMarketCountry(initialMarketCountry);
}

const flashSuccess = computed(() => page.props.flash?.success ?? null);
const flashError = computed(() => page.props.flash?.error ?? null);
const flashRestrictedAction = computed(() => page.props.flash?.restricted_action ?? null);
const formErrorList = computed(() => Object.values(form.errors ?? {}).filter((value): value is string => typeof value === 'string' && value.length > 0));
const previewName = computed(() => form.site_name || props.tenant.name);
const selectedContractPdfTemplate = computed(
    () => props.pdfTemplateOptions.find((option) => option.value === form.pdf_templates.contract) ?? null,
);
const uploadedLogoUrl = computed(() => props.logoFiles?.[0]?.url || null);
const previewLogoUrl = computed(() => uploadedLogoUrl.value || form.logo_url || null);
const uploadedSeoOgImageUrl = computed(() => props.seoOgImageFiles?.[0]?.url || null);
const enabledSeoLocales = computed(() => {
    const locales = Array.isArray(props.settings.enabled_locales) ? props.settings.enabled_locales : ['en', 'ar'];

    return locales
        .map((locale) => String(locale).trim())
        .filter((locale, index, array) => locale !== '' && array.indexOf(locale) === index);
});
const primarySecondaryGradient = computed(
    () => `linear-gradient(135deg, ${form.primary_color || '#f97316'}, ${form.secondary_color || '#ea580c'})`,
);
const primaryButtonStyle = computed(() => ({
    backgroundColor: form.primary_color || '#f97316',
    borderColor: form.primary_color || '#f97316',
    color: '#ffffff',
}));
const seoPreviewBaseUrl = computed(() => {
    if (typeof window !== 'undefined' && window.location?.origin) {
        return window.location.origin;
    }

    return 'https://example.com';
});

const localizedSeoText = (value: LocalizedText | undefined | null): string => {
    if (!value) {
        return '';
    }

    const preferred = locale.value === 'ar' ? value.ar : value.en;
    const fallback = locale.value === 'ar' ? value.en : value.ar;

    return String(preferred || fallback || '').trim();
};

type SeoPageKey = 'home' | 'fleet' | 'about' | 'contact' | 'car' | 'booking_checkout' | 'booking_confirmation';

const seoPagePath = (pageKey: SeoPageKey): string => {
    if (pageKey === 'home') return '/';
    if (pageKey === 'car') return '/fleet/sample-car';
    if (pageKey === 'booking_checkout') return '/booking/sample-reservation/checkout';
    if (pageKey === 'booking_confirmation') return '/booking/sample-reservation';

    return `/${pageKey}`;
};

const seoPageDefaultTitle = (pageKey: 'home' | 'fleet' | 'about' | 'contact'): string => {
    const suffix = localizedSeoText(form.seo.defaults.title_suffix) || previewName.value;

    if (pageKey === 'home') {
        return previewName.value;
    }

    const labels = {
        home: localize('Home', 'الرئيسية'),
        fleet: localize('Fleet', 'الأسطول'),
        about: localize('About', 'من نحن'),
        contact: localize('Contact', 'اتصل بنا'),
    };

    return `${labels[pageKey]} | ${suffix}`;
};

const seoPageDefaultDescription = (pageKey: 'home' | 'fleet' | 'about' | 'contact'): string => {
    const shared = localizedSeoText(form.seo.defaults.default_description);

    if (shared) {
        return shared;
    }

    const descriptions = {
        home: localize(`Discover ${previewName.value} and reserve your next rental car online.`, `اكتشف ${previewName.value} واحجز سيارة الإيجار التالية عبر الإنترنت.`),
        fleet: localize(`Browse available rental vehicles from ${previewName.value}.`, `استعرض سيارات الإيجار المتاحة من ${previewName.value}.`),
        about: localize(`Learn more about ${previewName.value} and its car rental services.`, `تعرف أكثر على ${previewName.value} وخدمات تأجير السيارات الخاصة به.`),
        contact: localize(`Get in touch with ${previewName.value} for bookings and support.`, `تواصل مع ${previewName.value} للحجوزات والدعم.`),
    };

    return descriptions[pageKey];
};

const seoPreviewCards = computed(() => {
    const pages: Array<'home' | 'fleet' | 'about' | 'contact'> = ['home', 'fleet', 'about', 'contact'];

    return pages.map((pageKey) => {
        const title = localizedSeoText(form.seo.pages[pageKey].title) || seoPageDefaultTitle(pageKey);
        const description = localizedSeoText(form.seo.pages[pageKey].description) || seoPageDefaultDescription(pageKey);
        const path = form.seo.pages[pageKey].canonical_url || `${seoPreviewBaseUrl.value}${seoPagePath(pageKey)}`;

        return {
            key: pageKey,
            label: localize(
                `${pageKey.charAt(0).toUpperCase()}${pageKey.slice(1)} Page`,
                pageKey === 'home' ? 'الصفحة الرئيسية' : pageKey === 'fleet' ? 'صفحة الأسطول' : pageKey === 'about' ? 'صفحة من نحن' : 'صفحة اتصل بنا',
            ),
            title,
            description,
            path,
        };
    });
});

const seoPageDefaultTitleExtended = (pageKey: SeoPageKey): string => {
    const suffix = localizedSeoText(form.seo.defaults.title_suffix) || previewName.value;

    if (pageKey === 'home') {
        return previewName.value;
    }

    const labels: Record<SeoPageKey, string> = {
        home: localize('Home', 'الرئيسية'),
        fleet: localize('Fleet', 'الأسطول'),
        about: localize('About', 'من نحن'),
        contact: localize('Contact', 'اتصل بنا'),
        car: localize('Car Rental', 'تأجير سيارة'),
        booking_checkout: localize('Booking Checkout', 'إتمام الحجز'),
        booking_confirmation: localize('Booking Confirmation', 'تأكيد الحجز'),
    };

    return `${labels[pageKey]} | ${suffix}`;
};

const seoPageDefaultDescriptionExtended = (pageKey: SeoPageKey): string => {
    const shared = localizedSeoText(form.seo.defaults.default_description);

    if (shared) {
        return shared;
    }

    const descriptions: Record<SeoPageKey, string> = {
        home: localize(`Discover ${previewName.value} and reserve your next rental car online.`, `اكتشف ${previewName.value} واحجز سيارة الإيجار التالية عبر الإنترنت.`),
        fleet: localize(`Browse available rental vehicles from ${previewName.value}.`, `استعرض سيارات الإيجار المتاحة من ${previewName.value}.`),
        about: localize(`Learn more about ${previewName.value} and its car rental services.`, `تعرف أكثر على ${previewName.value} وخدمات تأجير السيارات الخاصة به.`),
        contact: localize(`Get in touch with ${previewName.value} for bookings and support.`, `تواصل مع ${previewName.value} للحجوزات والدعم.`),
        car: localize(`View rental car details and pricing from ${previewName.value}.`, `اطلع على تفاصيل السيارة وسعر الإيجار لدى ${previewName.value}.`),
        booking_checkout: localize(`Choose your payment provider and complete your booking securely with ${previewName.value}.`, `اختر مزود الدفع وأكمل الحجز بأمان مع ${previewName.value}.`),
        booking_confirmation: localize(`Review your confirmed booking and reservation details from ${previewName.value}.`, `راجع تفاصيل الحجز المؤكد ومعلومات الحجز لدى ${previewName.value}.`),
    };

    return descriptions[pageKey];
};

const seoPreviewCardsData = computed(() => {
    const pages: SeoPageKey[] = ['home', 'fleet', 'about', 'contact', 'car', 'booking_checkout', 'booking_confirmation'];
    const englishLabels: Record<SeoPageKey, string> = {
        home: 'Home Page',
        fleet: 'Fleet Page',
        about: 'About Page',
        contact: 'Contact Page',
        car: 'Car Details Page',
        booking_checkout: 'Booking Checkout Page',
        booking_confirmation: 'Booking Confirmation Page',
    };
    const arabicLabels: Record<SeoPageKey, string> = {
        home: 'الصفحة الرئيسية',
        fleet: 'صفحة الأسطول',
        about: 'صفحة من نحن',
        contact: 'صفحة اتصل بنا',
        car: 'صفحة السيارة',
        booking_checkout: 'صفحة إتمام الحجز',
        booking_confirmation: 'صفحة تأكيد الحجز',
    };

    return pages.map((pageKey) => {
        const title = localizedSeoText(form.seo.pages[pageKey].title) || seoPageDefaultTitleExtended(pageKey);
        const description = localizedSeoText(form.seo.pages[pageKey].description) || seoPageDefaultDescriptionExtended(pageKey);
        const path = form.seo.pages[pageKey].canonical_url || `${seoPreviewBaseUrl.value}${seoPagePath(pageKey)}`;
        const robots = pageKey === 'booking_checkout' || pageKey === 'booking_confirmation'
            ? 'noindex,nofollow'
            : (form.seo.defaults.robots || 'index,follow');
        const canonicalValue = (form.seo.pages[pageKey].canonical_url || '').trim();
        const canonicalLooksValid = canonicalValue === '' || /^https?:\/\/\S+$/i.test(canonicalValue);
        const alternateUrls = enabledSeoLocales.value.map((locale) => ({
            locale,
            url: path,
        }));
        const pathname = (() => {
            try {
                return new URL(path, seoPreviewBaseUrl.value).pathname;
            } catch {
                return seoPagePath(pageKey);
            }
        })();
        const normalizedSlug = pathname.replace(/\/+/g, '/').replace(/^\/|\/$/g, '');
        const slugLooksValid = normalizedSlug !== '' && /^[a-z0-9/_-]+$/i.test(normalizedSlug) && !/\s/.test(normalizedSlug);
        const hreflangLooksValid = alternateUrls.length === enabledSeoLocales.value.length && enabledSeoLocales.value.length > 0;
        const checks = [
            {
                ok: title.length >= 30 && title.length <= 60,
                label: localize('Title length looks good', 'طول العنوان مناسب'),
                failLabel: localize('Recommended title length is 30-60 characters', 'الطول الموصى به للعنوان هو 30-60 حرفًا'),
            },
            {
                ok: description.length >= 70 && description.length <= 160,
                label: localize('Description length looks good', 'طول الوصف مناسب'),
                failLabel: localize('Recommended description length is 70-160 characters', 'الطول الموصى به للوصف هو 70-160 حرفًا'),
            },
            {
                ok: Boolean((form.seo.defaults.og_image || uploadedSeoOgImageUrl.value || previewLogoUrl.value || '').trim()),
                label: localize('Open Graph image is set', 'صورة Open Graph مضبوطة'),
                failLabel: localize('Set an Open Graph image for sharing previews', 'حدد صورة Open Graph لمعاينات المشاركة'),
            },
            {
                ok: canonicalLooksValid,
                label: localize('Canonical URL is valid', 'رابط Canonical صحيح'),
                failLabel: localize('Canonical URL must start with http:// or https://', 'رابط Canonical يجب أن يبدأ بظ€ http:// أو https://'),
            },
            {
                ok: slugLooksValid,
                label: localize('Slug format looks clean', 'تنسيق الرابط المختصر سليم'),
                failLabel: localize('Slug should use clean URL segments without spaces', 'يجب أن يستخدم الرابط المختصر مقاطع نظيفة بدون مسافات'),
            },
            {
                ok: hreflangLooksValid,
                label: localize('hreflang alternates are available for enabled locales', 'روابط hreflang متوفرة للغات المفعلة'),
                failLabel: localize('hreflang alternates are missing for one or more enabled locales', 'روابط hreflang مفقودة لإحدى اللغات المفعلة أو أكثر'),
            },
        ];

        return {
            key: pageKey,
            label: localize(englishLabels[pageKey], arabicLabels[pageKey]),
            title,
            description,
            path,
            robots,
            ogImage: (form.seo.defaults.og_image || uploadedSeoOgImageUrl.value || previewLogoUrl.value || '').trim(),
            twitterCardType: 'summary_large_image',
            alternates: alternateUrls,
            slug: normalizedSlug,
            score: checks.filter((check) => check.ok).length,
            checks,
        };
    });
});

const seoBlockingPages = computed(() => seoPreviewCardsData.value.filter((preview) => preview.score === 0));
const seoHealthStatus = computed(() => {
    const totalChecks = seoPreviewCardsData.value.reduce((sum, preview) => sum + preview.checks.length, 0);
    const passedChecks = seoPreviewCardsData.value.reduce((sum, preview) => sum + preview.score, 0);
    const ratio = totalChecks > 0 ? passedChecks / totalChecks : 0;

    if (ratio >= 0.85) {
        return {
            label: localize('Good', 'جيد'),
            description: localize('Most SEO signals are in good shape.', 'معظم إشارات SEO في وضع جيد.'),
            className: 'bg-emerald-100 text-emerald-700',
        };
    }

    if (ratio >= 0.5) {
        return {
            label: localize('Needs Work', 'يحتاج تحسين'),
            description: localize('Some pages still need SEO cleanup.', 'بعض الصفحات ما زالت تحتاج تحسين SEO.'),
            className: 'bg-amber-100 text-amber-700',
        };
    }

    return {
        label: localize('Critical', 'حرج'),
        description: localize('SEO coverage is weak and should be fixed before publishing changes.', 'تغطية SEO ضعيفة ويجب إصلاحها قبل اعتماد التغييرات.'),
        className: 'bg-red-100 text-red-700',
    };
});
const seoSaveBlockedMessage = ref('');
const seoCopyMessage = ref('');

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const faviconUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const seoFileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const heroImageUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const aboutSarahImageUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const aboutMichaelImageUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const aboutEmilyImageUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const logoTempFolders = ref<string[]>([]);
const faviconTempFolders = ref<string[]>([]);
const heroImageTempFolders = ref<string[]>([]);
const aboutSarahImageTempFolders = ref<string[]>([]);
const aboutMichaelImageTempFolders = ref<string[]>([]);
const aboutEmilyImageTempFolders = ref<string[]>([]);
const logoRemovedFileIds = ref<number[]>([]);
const faviconRemovedFileIds = ref<number[]>([]);
const seoOgImageRemovedFileIds = ref<number[]>([]);
const heroImageRemovedFileIds = ref<number[]>([]);
const aboutSarahImageRemovedFileIds = ref<number[]>([]);
const aboutMichaelImageRemovedFileIds = ref<number[]>([]);
const aboutEmilyImageRemovedFileIds = ref<number[]>([]);
const showAdvancedBranding = ref(false);

watch(
    logoTempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    faviconTempFolders,
    (value) => {
        form.favicon_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    heroImageTempFolders,
    (value) => {
        form.hero_image_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    aboutSarahImageTempFolders,
    (value) => {
        form.about_team_sarah_image_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    aboutMichaelImageTempFolders,
    (value) => {
        form.about_team_michael_image_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    aboutEmilyImageTempFolders,
    (value) => {
        form.about_team_emily_image_temp_folders = [...value];
    },
    { deep: true },
);

function copySeoMetaSummary(preview: {
    label: string;
    title: string;
    description: string;
    path: string;
    robots: string;
    ogImage: string;
    twitterCardType: string;
    slug: string;
}) {
    const summary = [
        `${preview.label}`,
        `Title: ${preview.title}`,
        `Description: ${preview.description}`,
        `Canonical: ${preview.path}`,
        `Slug: ${preview.slug}`,
        `Robots: ${preview.robots}`,
        `OG Image: ${preview.ogImage || 'N/A'}`,
        `Twitter Card: ${preview.twitterCardType}`,
    ].join('\n');

    if (typeof navigator === 'undefined' || !navigator.clipboard) {
        seoCopyMessage.value = localize('Clipboard is not available in this browser.', 'الحافظة غير متاحة في هذا المتصفح.');
        return;
    }

    navigator.clipboard.writeText(summary)
        .then(() => {
            seoCopyMessage.value = localize(`Copied SEO summary for ${preview.label}.`, `تم نسخ ملخص SEO لصفحة ${preview.label}.`);
        })
        .catch(() => {
            seoCopyMessage.value = localize('Could not copy SEO summary.', 'تعذر نسخ ملخص SEO.');
        });
}

function exportSeoReport() {
    const lines = [
        `Tenant: ${props.tenant.name}`,
        `Slug: ${props.tenant.slug}`,
        `Overall Status: ${seoHealthStatus.value.label}`,
        `Enabled Locales: ${enabledSeoLocales.value.join(', ')}`,
        '',
        ...seoPreviewCardsData.value.flatMap((preview) => [
            `[${preview.label}]`,
            `Title: ${preview.title}`,
            `Description: ${preview.description}`,
            `Canonical: ${preview.path}`,
            `Slug: ${preview.slug}`,
            `Robots: ${preview.robots}`,
            `OG Image: ${preview.ogImage || 'N/A'}`,
            `Twitter Card: ${preview.twitterCardType}`,
            `Alternates: ${preview.alternates.map((alternate) => `${alternate.locale}=${alternate.url}`).join(' | ')}`,
            `Score: ${preview.score}/${preview.checks.length}`,
            ...preview.checks.map((check) => `- ${check.ok ? 'PASS' : 'WARN'}: ${check.ok ? check.label : check.failLabel}`),
            '',
        ]),
    ].join('\n');

    if (typeof window === 'undefined' || typeof document === 'undefined') {
        seoCopyMessage.value = localize('SEO report export is not available in this environment.', 'تصدير تقرير SEO غير متاح في هذه البيئة.');
        return;
    }

    const blob = new Blob([lines], { type: 'text/plain;charset=utf-8' });
    const url = window.URL.createObjectURL(blob);
    const link = document.createElement('a');
    link.href = url;
    link.download = `${props.tenant.slug || 'tenant'}-seo-report.txt`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    window.URL.revokeObjectURL(url);
    seoCopyMessage.value = localize('SEO report exported successfully.', 'تم تصدير تقرير SEO بنجاح.');
}

watch(
    () => form.errors.logo_url,
    (value) => {
        if (value) {
            showAdvancedBranding.value = true;
        }
    },
);

watch(
    () => form.errors.favicon_url,
    (value) => {
        if (value) {
            showAdvancedBranding.value = true;
        }
    },
);

watch(
    seoBlockingPages,
    (pages) => {
        if (pages.length === 0) {
            seoSaveBlockedMessage.value = '';
        }
    },
    { deep: true },
);

function handleLogoFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        logoRemovedFileIds.value.push(data.fileId);
        form.logo_removed_files = [...new Set(logoRemovedFileIds.value)];
    }
}

function handleFaviconFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        faviconRemovedFileIds.value.push(data.fileId);
        form.favicon_removed_files = [...new Set(faviconRemovedFileIds.value)];
    }
}

function handleSeoOgImageFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        seoOgImageRemovedFileIds.value.push(data.fileId);
        form.seo_og_image_removed_files = [...new Set(seoOgImageRemovedFileIds.value)];
    }
}

function handleHeroImageFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        heroImageRemovedFileIds.value.push(data.fileId);
        form.hero_image_removed_files = [...new Set(heroImageRemovedFileIds.value)];
    }
}

function handleAboutSarahImageFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        aboutSarahImageRemovedFileIds.value.push(data.fileId);
        form.about_team_sarah_image_removed_files = [...new Set(aboutSarahImageRemovedFileIds.value)];
    }
}

function handleAboutMichaelImageFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        aboutMichaelImageRemovedFileIds.value.push(data.fileId);
        form.about_team_michael_image_removed_files = [...new Set(aboutMichaelImageRemovedFileIds.value)];
    }
}

function handleAboutEmilyImageFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        aboutEmilyImageRemovedFileIds.value.push(data.fileId);
        form.about_team_emily_image_removed_files = [...new Set(aboutEmilyImageRemovedFileIds.value)];
    }
}

function submit() {
    if (false && seoBlockingPages.value.length > 0) {
        const labels = seoBlockingPages.value.map((page) => page.label).join(', ');
        seoSaveBlockedMessage.value = localize(
            `SEO save blocked. Fix these pages first: ${labels}.`,
            `تم منع الحفظ بسبب ضعف SEO في هذه الصفحات: ${labels}.`,
        );
        return;
    }

    form.put(props.actions.update, {
        preserveScroll: true,
        onSuccess: () => {
            seoSaveBlockedMessage.value = '';
            logoTempFolders.value = [];
            form.logo_temp_folders = [];
            form.logo_removed_files = [];
            logoRemovedFileIds.value = [];
            fileUploadRef.value?.resetFiles();

            faviconTempFolders.value = [];
            form.favicon_temp_folders = [];
            form.favicon_removed_files = [];
            faviconRemovedFileIds.value = [];
            faviconUploadRef.value?.resetFiles();

            form.seo_og_image_temp_folders = [];
            form.seo_og_image_removed_files = [];
            seoOgImageRemovedFileIds.value = [];
            seoFileUploadRef.value?.resetFiles();

            heroImageTempFolders.value = [];
            form.hero_image_temp_folders = [];
            form.hero_image_removed_files = [];
            heroImageRemovedFileIds.value = [];
            heroImageUploadRef.value?.resetFiles();

            aboutSarahImageTempFolders.value = [];
            form.about_team_sarah_image_temp_folders = [];
            form.about_team_sarah_image_removed_files = [];
            aboutSarahImageRemovedFileIds.value = [];
            aboutSarahImageUploadRef.value?.resetFiles();

            aboutMichaelImageTempFolders.value = [];
            form.about_team_michael_image_temp_folders = [];
            form.about_team_michael_image_removed_files = [];
            aboutMichaelImageRemovedFileIds.value = [];
            aboutMichaelImageUploadRef.value?.resetFiles();

            aboutEmilyImageTempFolders.value = [];
            form.about_team_emily_image_temp_folders = [];
            form.about_team_emily_image_removed_files = [];
            aboutEmilyImageRemovedFileIds.value = [];
            aboutEmilyImageUploadRef.value?.resetFiles();
        },
    });
}
</script>

<template>
    <Head :title="localize('Website Settings', 'إعدادات الموقع')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Website Settings', 'إعدادات الموقع') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Customize your tenant website branding and homepage content (Arabic / English).', 'خصص هوية موقع المستأجر ومحتوى الصفحة الرئيسية باللغتين العربية والإنجليزية.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" :style="primaryButtonStyle" @click="submit">
                    {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                </Button>
            </div>

            <div v-if="flashSuccess" class="rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-700">
                {{ flashSuccess }}
            </div>
            <div v-if="flashError" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                {{ flashError }}
            </div>
            <div v-if="flashRestrictedAction" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-700">
                {{ flashRestrictedAction }}
            </div>
            <div v-if="formErrorList.length" class="rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-700">
                <div class="font-medium">{{ localize('Please fix the following errors:', 'يرجى تصحيح الأخطاء التالية:') }}</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in formErrorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <div v-if="false && seoSaveBlockedMessage" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                {{ seoSaveBlockedMessage }}
            </div>
            <div v-if="false && seoBlockingPages.length" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                {{ localize('SEO saving is blocked until every page has at least one valid SEO signal.', 'تم منع حفظ SEO حتى يحتوي ?? صفحة على إشارة SEO صحيحة واحدة على الأقل.') }}
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border p-5">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold">{{ localize('Branding', 'الهوية البصرية') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Site identity, logo URL, and brand colors.', 'هوية الموقع ورابط الشعار وألوان العلامة التجارية.') }}</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2 md:col-span-2">
                                <Label for="site_name">{{ localize('Site Name', 'اسم الموقع') }}</Label>
                                <Input id="site_name" v-model="form.site_name" :placeholder="localize('Tenant website name', 'اسم موقع المستأجر')" />
                                <p v-if="form.errors.site_name" class="text-sm text-red-600">{{ form.errors.site_name }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Logo Upload (System)', 'رفع الشعار (النظام)') }}</Label>
                                <FileUpload
                                    ref="fileUploadRef"
                                    v-model="logoTempFolders"
                                    :initial-files="logoFiles || []"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    collection="logo"
                                    theme="light"
                                    width="100%"
                                    @file-removed="handleLogoFileRemoved"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Upload logo to your system. New upload replaces the previous logo.', 'ارفع الشعار إلى النظام. أي رفع جديد سيستبدل الشعار السابق.') }}
                                </p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Favicon Upload', 'رفع الأيقونة المفضلة (Favicon)') }}</Label>
                                <FileUpload
                                    ref="faviconUploadRef"
                                    v-model="faviconTempFolders"
                                    :initial-files="faviconFiles || []"
                                    :allow-multiple="false"
                                    :max-files="1"
                                    :allowed-file-types="['image/x-icon', 'image/vnd.microsoft.icon', 'image/png', 'image/jpeg', 'image/svg+xml']"
                                    collection="favicon"
                                    theme="light"
                                    width="100%"
                                    @file-removed="handleFaviconFileRemoved"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Upload favicon to your system. Standard formats: .ico, .png, .svg. Standard sizes: 16x16, 32x32.', 'ارفع أيقونة الموقع المفضلة هنا. التنسيقات القياسية: .ico أو .png أو .svg. المقاسات المعتادة: 16x16 أو 32x32.') }}
                                </p>
                            </div>

                            <div class="md:col-span-2 rounded-md border bg-muted/20 p-3 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium">{{ localize('Advanced Branding Options', 'خيارات الهوية المتقدمة') }}</div>
                                        <p class="text-xs text-muted-foreground">{{ localize('Optional fallback branding URLs (used only if no files are uploaded).', 'روابط الهوية الاحتياطية الاختيارية (تُستخدم فقط إذا لم يتم رفع ملفات).') }}</p>
                                    </div>
                                    <Button type="button" variant="outline" size="sm" @click="showAdvancedBranding = !showAdvancedBranding">
                                        {{ showAdvancedBranding ? localize('Hide Advanced', 'إخفاء المتقدم') : localize('Show Advanced', 'إظهار المتقدم') }}
                                    </Button>
                                </div>

                                <div v-if="showAdvancedBranding" class="space-y-4">
                                    <div class="space-y-2">
                                        <Label for="logo_url">{{ localize('Fallback Logo URL', 'رابط الشعار الاحتياطي') }}</Label>
                                        <Input id="logo_url" v-model="form.logo_url" placeholder="https://example.com/logo.png" />
                                        <p class="text-xs text-muted-foreground">
                                            {{ localize('This URL is used only when no uploaded logo exists in the system.', 'يُستخدم هذا الرابط فقط عندما لا يوجد شعار مرفوع في النظام.') }}
                                        </p>
                                        <p v-if="form.errors.logo_url" class="text-sm text-red-600">{{ form.errors.logo_url }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="favicon_url">{{ localize('Fallback Favicon URL', 'رابط الأيقونة المفضلة الاحتياطي') }}</Label>
                                        <Input id="favicon_url" v-model="form.favicon_url" placeholder="https://example.com/favicon.ico" />
                                        <p class="text-xs text-muted-foreground">
                                            {{ localize('This URL is used only when no uploaded favicon exists in the system.', 'يُستخدم هذا الرابط فقط عندما لا توجد أيقونة موقع مرفوعة في النظام.') }}
                                        </p>
                                        <p v-if="form.errors.favicon_url" class="text-sm text-red-600">{{ form.errors.favicon_url }}</p>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="primary_color">{{ localize('Primary Color', 'اللون الأساسي') }}</Label>
                                <div class="flex items-center gap-2">
                                    <input id="primary_color" v-model="form.primary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.primary_color" placeholder="#f97316" />
                                </div>
                                <p v-if="form.errors.primary_color" class="text-sm text-red-600">{{ form.errors.primary_color }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="secondary_color">{{ localize('Secondary Color', 'اللون الثانوي') }}</Label>
                                <div class="flex items-center gap-2">
                                    <input id="secondary_color" v-model="form.secondary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.secondary_color" placeholder="#ea580c" />
                                </div>
                                <p v-if="form.errors.secondary_color" class="text-sm text-red-600">{{ form.errors.secondary_color }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label for="tax_percentage">{{ localize('Booking Tax Percentage', 'نسبة ضريبة الحجز') }}</Label>
                                <Input
                                    id="tax_percentage"
                                    v-model.number="form.tax_percentage"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    max="100"
                                    placeholder="7"
                                />
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Set `0` to hide tax in booking page.', 'ضع `0` لإخفاء الضريبة في صفحة الحجز.') }}
                                </p>
                                <p v-if="form.errors.tax_percentage" class="text-sm text-red-600">{{ form.errors.tax_percentage }}</p>
                            </div>

                            <div class="space-y-4 rounded-md border bg-muted/20 p-4 md:col-span-2">
                                <div>
                                    <h3 class="text-sm font-semibold">{{ localize('Market Location', 'موقع السوق') }}</h3>
                                    <p class="text-xs text-muted-foreground">
                                        {{ localize('Used by AI market reports, pricing suggestions, and local business analysis.', 'تستخدمها تقارير الذكاء الاصطناعي ودراسة السوق واقتراحات التسعير حسب منطقة التاجر.') }}
                                    </p>
                                </div>

                                <div class="grid gap-4 md:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="market_country_name">{{ localize('Country', 'الدولة') }}</Label>
                                        <select id="market_country_name" v-model="form.market_location.country_name" :class="selectClass" @change="handleMarketCountryChange">
                                            <option value="">{{ localize('Select country', 'اختر الدولة') }}</option>
                                            <option v-for="country in marketCountryOptions" :key="country.iso2" :value="country.name_en">
                                                {{ localizedCountryName(country) }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors['market_location.country_name']" class="text-sm text-red-600">{{ form.errors['market_location.country_name'] }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="market_country_code">{{ localize('Country Code', 'رمز الدولة') }}</Label>
                                        <select id="market_country_code" v-model="form.market_location.country_code" :class="selectClass" @change="handleMarketCountryCodeChange">
                                            <option value="">{{ localize('Select code', 'اختر الرمز') }}</option>
                                            <option v-for="country in marketCountryOptions" :key="country.iso2" :value="country.iso2">
                                                {{ country.iso2 }}
                                            </option>
                                        </select>
                                        <p class="text-xs text-muted-foreground">{{ localize('ISO 2-letter code, for example OM, AE, PS.', 'رمز ISO من حرفين مثل OM أو AE أو PS.') }}</p>
                                        <p v-if="form.errors['market_location.country_code']" class="text-sm text-red-600">{{ form.errors['market_location.country_code'] }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="market_region">{{ localize('Region / Governorate', 'المنطقة / المحافظة') }}</Label>
                                        <select id="market_region" v-model="form.market_location.region" :class="selectClass">
                                            <option value="">{{ localize('Select region', 'اختر المنطقة') }}</option>
                                            <option v-for="region in marketRegionOptions" :key="region.value" :value="region.value">
                                                {{ region.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors['market_location.region']" class="text-sm text-red-600">{{ form.errors['market_location.region'] }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="market_city">{{ localize('City', 'المدينة') }}</Label>
                                        <select id="market_city" v-model="form.market_location.city" :class="selectClass">
                                            <option value="">{{ localize('Select city', 'اختر المدينة') }}</option>
                                            <option v-for="city in marketCityOptions" :key="city.value" :value="city.value">
                                                {{ city.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors['market_location.city']" class="text-sm text-red-600">{{ form.errors['market_location.city'] }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="market_area">{{ localize('Business Area', 'نطاق العمل') }}</Label>
                                        <select id="market_area" v-model="form.market_location.market_area" :class="selectClass">
                                            <option value="">{{ localize('Select area', 'اختر النطاق') }}</option>
                                            <option v-for="area in marketAreaOptions" :key="area.value" :value="area.value">
                                                {{ area.label }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors['market_location.market_area']" class="text-sm text-red-600">{{ form.errors['market_location.market_area'] }}</p>
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="market_currency_code">{{ localize('Site Currency', 'عملة الموقع') }}</Label>
                                        <SearchableSelect
                                            id="market_currency_code"
                                            v-model="form.market_location.currency_code"
                                            :options="marketCurrencySelectOptions"
                                            :placeholder="localize('Select currency', 'اختر العملة')"
                                            :search-placeholder="localize('Search currency...', 'ابحث عن عملة...')"
                                            :empty-text="localize('No currencies found.', 'لا توجد عملات.')"
                                            clearable
                                        />
                                        <p v-if="form.errors['market_location.currency_code']" class="text-sm text-red-600">{{ form.errors['market_location.currency_code'] }}</p>
                                    </div>

                                    <div class="space-y-2 md:col-span-2">
                                        <div class="space-y-3 rounded-md border bg-background p-4">
                                            <div class="flex flex-wrap items-start justify-between gap-3">
                                                <div>
                                                    <Label for="enabled_currency_search">{{ localize('Enabled Currencies', 'العملات المفعلة') }}</Label>
                                                    <p class="mt-1 text-xs text-muted-foreground">
                                                        {{ localize('Choose the currencies this office can use across the site, bookings, and contracts.', 'اختر العملات التي يمكن لهذا المكتب التعامل بها في الموقع والحجوزات والعقود.') }}
                                                    </p>
                                                </div>
                                                <div class="flex gap-2">
                                                    <Button type="button" variant="outline" size="sm" @click="enableAllCurrencies">
                                                        {{ localize('Select all', 'تحديد الكل') }}
                                                    </Button>
                                                    <Button type="button" variant="outline" size="sm" @click="clearEnabledCurrencies">
                                                        {{ localize('Keep base only', 'الأساسية فقط') }}
                                                    </Button>
                                                </div>
                                            </div>

                                            <Input
                                                id="enabled_currency_search"
                                                v-model="enabledCurrencySearch"
                                                :placeholder="localize('Search enabled currencies...', 'ابحث في العملات المفعلة...')"
                                            />

                                            <div class="max-h-64 overflow-auto rounded-md border">
                                                <label
                                                    v-for="currency in filteredEnabledCurrencyOptions"
                                                    :key="currency.code"
                                                    class="flex cursor-pointer items-center justify-between gap-3 border-b px-3 py-2 text-sm last:border-b-0 hover:bg-muted/50"
                                                >
                                                    <span>{{ currency.label }}</span>
                                                    <input
                                                        type="checkbox"
                                                        class="h-4 w-4 rounded border-input"
                                                        :checked="isCurrencyEnabled(currency.code)"
                                                        :disabled="currency.code === form.market_location.currency_code"
                                                        @change="toggleEnabledCurrency(currency.code)"
                                                    />
                                                </label>
                                            </div>

                                            <div v-if="form.market_location.enabled_currency_codes.length" class="flex flex-wrap gap-2">
                                                <span
                                                    v-for="code in form.market_location.enabled_currency_codes"
                                                    :key="code"
                                                    class="rounded-full bg-muted px-3 py-1 text-xs font-medium text-muted-foreground"
                                                >
                                                    {{ currencyOptionLabel(code) }}
                                                </span>
                                            </div>
                                            <p v-if="form.errors['market_location.enabled_currency_codes']" class="text-sm text-red-600">{{ form.errors['market_location.enabled_currency_codes'] }}</p>
                                        </div>

                                        <Label for="market_timezone">{{ localize('Timezone', 'المنطقة الزمنية') }}</Label>
                                        <select id="market_timezone" v-model="form.market_location.timezone" :class="selectClass">
                                            <option value="">{{ localize('Select timezone', 'اختر المنطقة الزمنية') }}</option>
                                            <option v-for="timezone in marketTimezoneOptions" :key="timezone" :value="timezone">
                                                {{ timezone }}
                                            </option>
                                        </select>
                                        <p v-if="form.errors['market_location.timezone']" class="text-sm text-red-600">{{ form.errors['market_location.timezone'] }}</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4">
                            <div class="text-sm font-medium mb-3">{{ localize('Preview', 'معاينة') }}</div>
                            <div class="rounded-xl border overflow-hidden bg-white">
                                <div class="h-20" :style="{ background: primarySecondaryGradient }"></div>
                                <div class="p-4 space-y-3">
                                    <div class="flex items-center gap-3">
                                        <img
                                            v-if="previewLogoUrl"
                                            :src="previewLogoUrl"
                                            alt="logo preview"
                                            class="h-10 w-10 rounded object-contain border bg-white p-1"
                                        />
                                        <div
                                            v-else
                                            class="h-10 w-10 rounded flex items-center justify-center text-white font-bold"
                                            :style="{ background: form.primary_color }"
                                        >
                                            {{ previewName.charAt(0).toUpperCase() }}
                                        </div>
                                        <div class="font-semibold truncate">{{ previewName }}</div>
                                    </div>
                                    <button
                                        type="button"
                                        class="w-full rounded-md px-3 py-2 text-sm font-semibold text-white"
                                        :style="{ background: primarySecondaryGradient }"
                                    >
                                        {{ localize('CTA Preview', 'معاينة زر الدعوة') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('SEO', 'تهيئة محركات البحث') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize('SEO management has moved to a dedicated page for cleaner settings management.', 'تم نقل إدارة SEO إلى صفحة مستقلة لتبقى الإعدادات العامة أوضح.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link v-if="props.actions.seo_edit" :href="props.actions.seo_edit">
                                <Button variant="outline">{{ localize('Open SEO Settings', 'فتح إعدادات SEO') }}</Button>
                            </Link>
                            <Link v-if="props.actions.seo_audit" :href="props.actions.seo_audit">
                                <Button :style="primaryButtonStyle">{{ localize('Open SEO Audit', 'فتح تدقيق SEO') }}</Button>
                            </Link>
                        </div>
                    </div>
                </section>

                <section v-if="false" class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('SEO', 'تهيئة محركات البحث') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('Control page titles, descriptions, canonical URLs, and Open Graph defaults for your public website.', 'تحكم في عناوين الصفحات والأوصاف وروابط canonical وإعدادات Open Graph الافتراضية لموقعك العام.') }}
                        </p>
                    </div>

                    <div class="rounded-lg border bg-muted/20 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <h3 class="font-semibold">{{ localize('Overall SEO Status', 'الحالة العامة لظ€ SEO') }}</h3>
                                <p class="text-sm text-muted-foreground">{{ seoHealthStatus.description }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link v-if="props.actions.seo_audit" :href="props.actions.seo_audit">
                                    <button
                                        type="button"
                                        class="rounded-full border px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        {{ localize('Open SEO Audit', 'فتح تدقيق SEO') }}
                                    </button>
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    @click="exportSeoReport"
                                >
                                    {{ localize('Export SEO Report', 'تصدير تقرير SEO') }}
                                </button>
                                <span class="rounded-full px-3 py-1 text-sm font-semibold" :class="seoHealthStatus.className">
                                    {{ seoHealthStatus.label }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div v-if="seoCopyMessage" class="rounded-md border border-sky-200 bg-sky-50 p-3 text-sm text-sky-800">
                        {{ seoCopyMessage }}
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="seo_title_suffix_en">{{ localize('Title Suffix (EN)', 'لاحقة العنوان (EN)') }}</Label>
                            <Input id="seo_title_suffix_en" v-model="form.seo.defaults.title_suffix.en" />
                            <p v-if="form.errors['seo.defaults.title_suffix.en']" class="text-sm text-red-600">{{ form.errors['seo.defaults.title_suffix.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_title_suffix_ar">{{ localize('Title Suffix (AR)', 'لاحقة العنوان (AR)') }}</Label>
                            <Input id="seo_title_suffix_ar" v-model="form.seo.defaults.title_suffix.ar" dir="rtl" />
                            <p v-if="form.errors['seo.defaults.title_suffix.ar']" class="text-sm text-red-600">{{ form.errors['seo.defaults.title_suffix.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="seo_default_description_en">{{ localize('Default Description (EN)', 'الوصف الافتراضي (EN)') }}</Label>
                            <textarea id="seo_default_description_en" v-model="form.seo.defaults.default_description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['seo.defaults.default_description.en']" class="text-sm text-red-600">{{ form.errors['seo.defaults.default_description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_default_description_ar">{{ localize('Default Description (AR)', 'الوصف الافتراضي (AR)') }}</Label>
                            <textarea id="seo_default_description_ar" v-model="form.seo.defaults.default_description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['seo.defaults.default_description.ar']" class="text-sm text-red-600">{{ form.errors['seo.defaults.default_description.ar'] }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label>{{ localize('Open Graph Image Upload', 'رفع صورة Open Graph') }}</Label>
                            <FileUpload
                                ref="seoFileUploadRef"
                                v-model="form.seo_og_image_temp_folders"
                                :initial-files="seoOgImageFiles || []"
                                :allow-multiple="false"
                                :max-files="1"
                                collection="seo_og_image"
                                theme="light"
                                width="100%"
                                @file-removed="handleSeoOgImageFileRemoved"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Upload an image for og:image, or paste a URL below as a fallback.', 'ارفع صورة لـ og:image أو الصق رابطًا أدناه كخيار احتياطي.') }}
                            </p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_og_image">{{ localize('Open Graph Image URL', 'رابط صورة Open Graph') }}</Label>
                            <Input id="seo_og_image" v-model="form.seo.defaults.og_image" placeholder="https://example.com/og-image.jpg" />
                            <p v-if="form.errors['seo.defaults.og_image']" class="text-sm text-red-600">{{ form.errors['seo.defaults.og_image'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_robots">{{ localize('Robots', 'تعليمات Robots') }}</Label>
                            <Input id="seo_robots" v-model="form.seo.defaults.robots" placeholder="index,follow" />
                            <p v-if="form.errors['seo.defaults.robots']" class="text-sm text-red-600">{{ form.errors['seo.defaults.robots'] }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg border bg-muted/20 p-4 space-y-3">
                        <div>
                            <h3 class="font-semibold">{{ localize('Search Preview', 'معاينة نتائج البحث') }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Live preview for each public page using the current form values.', 'معاينة حية ??? صفحة عامة باستخدام القيم الحالية في النموذج.') }}
                            </p>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div v-for="preview in seoPreviewCardsData" :key="preview.key" class="rounded-lg border bg-background p-4">
                                <div class="mb-2 flex items-center justify-between gap-3">
                                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {{ preview.label }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <button
                                            type="button"
                                            class="rounded-full border px-2 py-1 text-[11px] font-medium text-slate-600 hover:bg-slate-50"
                                            @click="copySeoMetaSummary(preview)"
                                        >
                                            {{ localize('Copy', 'نسخ') }}
                                        </button>
                                        <div class="rounded-full px-2 py-1 text-xs font-medium" :class="preview.score === preview.checks.length ? 'bg-emerald-100 text-emerald-700' : preview.score > 0 ? 'bg-amber-100 text-amber-700' : 'bg-red-100 text-red-700'">
                                            {{ preview.score }}/{{ preview.checks.length }}
                                        </div>
                                    </div>
                                </div>
                                <div class="space-y-1">
                                    <div class="text-lg font-medium text-blue-700">
                                        {{ preview.title }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <div class="truncate text-sm text-green-700">
                                            {{ preview.path }}
                                        </div>
                                        <span class="shrink-0 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                            {{ preview.robots }}
                                        </span>
                                    </div>
                                    <p class="line-clamp-3 text-sm text-muted-foreground">
                                        {{ preview.description }}
                                    </p>
                                </div>
                                <div class="mt-4 space-y-2 border-t pt-3">
                                    <div v-for="(check, index) in preview.checks" :key="`${preview.key}-${index}`" class="flex items-start gap-2 text-xs">
                                        <span class="mt-0.5 h-2.5 w-2.5 rounded-full" :class="check.ok ? 'bg-emerald-500' : 'bg-amber-500'" />
                                        <span :class="check.ok ? 'text-emerald-700' : 'text-amber-700'">
                                            {{ check.ok ? check.label : check.failLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border bg-muted/20 p-4 space-y-3">
                        <div>
                            <h3 class="font-semibold">{{ localize('Open Graph Preview', 'معاينة Open Graph') }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Preview how shared links can appear on WhatsApp, Facebook, and similar platforms.', 'معاينة شكل الرابط عند مشاركته في واتساب وفيسبوك والمنصات المشابهة.') }}
                            </p>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div v-for="preview in seoPreviewCardsData" :key="`${preview.key}-og`" class="overflow-hidden rounded-xl border bg-background">
                                <div class="flex items-center justify-between gap-3 border-b bg-muted/30 px-4 py-3">
                                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {{ preview.label }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                            {{ preview.robots }}
                                        </span>
                                        <div class="text-[11px] text-muted-foreground">Open Graph</div>
                                    </div>
                                </div>

                                <div class="aspect-[1.91/1] w-full overflow-hidden bg-slate-100">
                                    <img
                                        v-if="preview.ogImage"
                                        :src="preview.ogImage"
                                        :alt="preview.title"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full items-center justify-center px-6 text-center text-sm text-muted-foreground">
                                        {{ localize('No Open Graph image selected yet.', 'لم يتم تحديد صورة Open Graph بعد.') }}
                                    </div>
                                </div>

                                <div class="space-y-2 px-4 py-4">
                                    <div class="truncate text-xs uppercase tracking-wide text-muted-foreground">
                                        {{ preview.path.replace(/^https?:\/\//, '') }}
                                    </div>
                                    <div class="line-clamp-2 text-base font-semibold text-slate-900">
                                        {{ preview.title }}
                                    </div>
                                    <p class="line-clamp-3 text-sm text-slate-600">
                                        {{ preview.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-lg border bg-muted/20 p-4 space-y-3">
                        <div>
                            <h3 class="font-semibold">{{ localize('Twitter / X Card Preview', 'معاينة بطاقة Twitter / X') }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Preview the large summary card style used by X/Twitter when the page is shared.', 'معاينة شكل البطاقة الكبيرة المستخدمة في X/Twitter عند مشاركة الصفحة.') }}
                            </p>
                        </div>

                        <div class="grid gap-4 lg:grid-cols-2">
                            <div v-for="preview in seoPreviewCardsData" :key="`${preview.key}-twitter`" class="overflow-hidden rounded-2xl border bg-background shadow-sm">
                                <div class="flex items-center justify-between border-b bg-muted/30 px-4 py-3">
                                    <div class="text-xs font-medium uppercase tracking-wide text-muted-foreground">
                                        {{ preview.label }}
                                    </div>
                                    <div class="flex items-center gap-2">
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                            {{ preview.twitterCardType }}
                                        </span>
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-600">
                                            {{ preview.robots }}
                                        </span>
                                    </div>
                                </div>

                                <div class="aspect-[2/1] w-full overflow-hidden bg-slate-100">
                                    <img
                                        v-if="preview.ogImage"
                                        :src="preview.ogImage"
                                        :alt="preview.title"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="flex h-full items-center justify-center px-6 text-center text-sm text-muted-foreground">
                                        {{ localize('No card image available.', 'لا توجد صورة للبطاقة.') }}
                                    </div>
                                </div>

                                <div class="space-y-2 px-4 py-4">
                                    <div class="truncate text-xs text-muted-foreground">
                                        {{ preview.path.replace(/^https?:\/\//, '') }}
                                    </div>
                                    <div class="line-clamp-2 text-[15px] font-semibold text-slate-900">
                                        {{ preview.title }}
                                    </div>
                                    <p class="line-clamp-2 text-sm text-slate-600">
                                        {{ preview.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Home Page SEO', 'SEO الصفحة الرئيسية') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_home_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_home_title_en" v-model="form.seo.pages.home.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_home_title_ar" v-model="form.seo.pages.home.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_home_description_en" v-model="form.seo.pages.home.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_home_description_ar" v-model="form.seo.pages.home.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_home_canonical" v-model="form.seo.pages.home.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Fleet Page SEO', 'SEO صفحة الأسطول') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_fleet_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_fleet_title_en" v-model="form.seo.pages.fleet.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_fleet_title_ar" v-model="form.seo.pages.fleet.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_fleet_description_en" v-model="form.seo.pages.fleet.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_fleet_description_ar" v-model="form.seo.pages.fleet.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_fleet_canonical" v-model="form.seo.pages.fleet.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('About Page SEO', 'SEO صفحة من نحن') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_about_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_about_title_en" v-model="form.seo.pages.about.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_about_title_ar" v-model="form.seo.pages.about.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_about_description_en" v-model="form.seo.pages.about.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_about_description_ar" v-model="form.seo.pages.about.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_about_canonical" v-model="form.seo.pages.about.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Contact Page SEO', 'SEO صفحة اتصل بنا') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_contact_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_contact_title_en" v-model="form.seo.pages.contact.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_contact_title_ar" v-model="form.seo.pages.contact.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_contact_description_en" v-model="form.seo.pages.contact.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_contact_description_ar" v-model="form.seo.pages.contact.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_contact_canonical" v-model="form.seo.pages.contact.canonical_url" />
                            </div>
                        </div>
                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Car Details Page SEO', 'SEO صفحة السيارة') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_car_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_car_title_en" v-model="form.seo.pages.car.title.en" placeholder="Use :car as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_car_title_ar" v-model="form.seo.pages.car.title.ar" dir="rtl" placeholder="استخدم :car كمتغير" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_car_description_en" v-model="form.seo.pages.car.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_car_description_ar" v-model="form.seo.pages.car.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_car_canonical" v-model="form.seo.pages.car.canonical_url" />
                            </div>
                        </div>
                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Booking Checkout SEO', 'SEO صفحة إتمام الحجز') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_checkout_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_checkout_title_en" v-model="form.seo.pages.booking_checkout.title.en" placeholder="Use :reservation as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_checkout_title_ar" v-model="form.seo.pages.booking_checkout.title.ar" dir="rtl" placeholder="استخدم :reservation كمتغير" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_checkout_description_en" v-model="form.seo.pages.booking_checkout.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_checkout_description_ar" v-model="form.seo.pages.booking_checkout.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_checkout_canonical" v-model="form.seo.pages.booking_checkout.canonical_url" />
                            </div>
                        </div>
                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Booking Confirmation SEO', 'SEO صفحة تأكيد الحجز') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_title_en">{{ localize('Title (EN)', 'العنوان (EN)') }}</Label>
                                <Input id="seo_confirmation_title_en" v-model="form.seo.pages.booking_confirmation.title.en" placeholder="Use :reservation as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_title_ar">{{ localize('Title (AR)', 'العنوان (AR)') }}</Label>
                                <Input id="seo_confirmation_title_ar" v-model="form.seo.pages.booking_confirmation.title.ar" dir="rtl" placeholder="استخدم :reservation كمتغير" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_description_en">{{ localize('Description (EN)', 'الوصف (EN)') }}</Label>
                                <textarea id="seo_confirmation_description_en" v-model="form.seo.pages.booking_confirmation.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_description_ar">{{ localize('Description (AR)', 'الوصف (AR)') }}</Label>
                                <textarea id="seo_confirmation_description_ar" v-model="form.seo.pages.booking_confirmation.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_canonical">{{ localize('Canonical URL', 'رابط Canonical') }}</Label>
                                <Input id="seo_confirmation_canonical" v-model="form.seo.pages.booking_confirmation.canonical_url" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 p-5 text-start"
                        :aria-expanded="contentSectionOpen.hero"
                        @click="toggleContentSection('hero')"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Hero Section', 'القسم الرئيسي') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ localize('Main banner texts for the tenant homepage.', 'النصوص الرئيسية لواجهة الصفحة الرئيسية الخاصة بالمستأجر.') }}</p>
                        </div>
                        <ChevronDown class="h-5 w-5 shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': contentSectionOpen.hero }" />
                    </button>

                    <div v-show="contentSectionOpen.hero" class="grid gap-4 border-t p-5 pt-4 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <Label>{{ localize('Hero Image', 'صورة القسم الرئيسي') }}</Label>
                            <FileUpload
                                ref="heroImageUploadRef"
                                v-model="heroImageTempFolders"
                                :initial-files="heroImageFiles || []"
                                :allow-multiple="false"
                                :max-files="1"
                                :allowed-file-types="['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml']"
                                collection="hero_image"
                                theme="light"
                                width="100%"
                                @file-removed="handleHeroImageFileRemoved"
                            />
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Upload the image shown on the homepage hero. A new upload replaces the previous image.', 'ارفع الصورة الظاهرة في القسم الرئيسي للصفحة الرئيسية. أي رفع جديد سيستبدل الصورة السابقة.') }}
                            </p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_title_en">{{ localize('Hero Title (EN)', 'عنوان القسم الرئيسي (EN)') }}</Label>
                            <Input id="hero_title_en" v-model="form.hero.title.en" placeholder="Rent the perfect car today" />
                            <p v-if="form.errors['hero.title.en']" class="text-sm text-red-600">{{ form.errors['hero.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_title_ar">{{ localize('Hero Title (AR)', 'عنوان القسم الرئيسي (AR)') }}</Label>
                            <Input id="hero_title_ar" v-model="form.hero.title.ar" placeholder="استأجر السيارة المناسبة اليوم" dir="rtl" />
                            <p v-if="form.errors['hero.title.ar']" class="text-sm text-red-600">{{ form.errors['hero.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_desc_en">{{ localize('Hero Description (EN)', 'وصف القسم الرئيسي (EN)') }}</Label>
                            <textarea id="hero_desc_en" v-model="form.hero.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['hero.description.en']" class="text-sm text-red-600">{{ form.errors['hero.description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_desc_ar">{{ localize('Hero Description (AR)', 'وصف القسم الرئيسي (AR)') }}</Label>
                            <textarea id="hero_desc_ar" v-model="form.hero.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['hero.description.ar']" class="text-sm text-red-600">{{ form.errors['hero.description.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_button_text_en">{{ localize('Button Text (EN)', 'نص الزر (EN)') }}</Label>
                            <Input id="hero_button_text_en" v-model="form.hero.button_text.en" placeholder="Browse Fleet" />
                            <p v-if="form.errors['hero.button_text.en']" class="text-sm text-red-600">{{ form.errors['hero.button_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_button_text_ar">{{ localize('Button Text (AR)', 'نص الزر (AR)') }}</Label>
                            <Input id="hero_button_text_ar" v-model="form.hero.button_text.ar" placeholder="تصفح السيارات" dir="rtl" />
                            <p v-if="form.errors['hero.button_text.ar']" class="text-sm text-red-600">{{ form.errors['hero.button_text.ar'] }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="hero_button_link">{{ localize('Button Link', 'رابط الزر') }}</Label>
                            <Input id="hero_button_link" v-model="form.hero.button_link" placeholder="/fleet" />
                            <p class="text-xs text-muted-foreground">{{ localize('Example: `/fleet` or `https://...`', 'مثال: `/fleet` أو `https://...`') }}</p>
                            <p v-if="form.errors['hero.button_link']" class="text-sm text-red-600">{{ form.errors['hero.button_link'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 p-5 text-start"
                        :aria-expanded="contentSectionOpen.about"
                        @click="toggleContentSection('about')"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('About Page', 'صفحة من نحن') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ localize('Editable content for public About page.', 'محتوى قابل للتعديل لصفحة من نحن العامة.') }}</p>
                        </div>
                        <ChevronDown class="h-5 w-5 shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': contentSectionOpen.about }" />
                    </button>

                    <div v-show="contentSectionOpen.about" class="grid gap-4 border-t p-5 pt-4 md:grid-cols-2">
                        <div class="space-y-4 rounded-md border bg-muted/20 p-4 md:col-span-2">
                            <div>
                                <h3 class="text-sm font-semibold">{{ localize('Team Images', 'صور الفريق') }}</h3>
                                <p class="text-xs text-muted-foreground">
                                    {{ localize('Upload the three team member images shown on the About page.', 'ارفع صور أعضاء الفريق الثلاثة الظاهرة في صفحة من نحن.') }}
                                </p>
                            </div>
                            <div class="grid gap-4 md:grid-cols-3">
                                <div class="space-y-2">
                                    <Label>{{ localize('Sarah Image', 'صورة Sarah') }}</Label>
                                    <FileUpload
                                        ref="aboutSarahImageUploadRef"
                                        v-model="aboutSarahImageTempFolders"
                                        :initial-files="aboutTeamImageFiles?.sarah || []"
                                        :allow-multiple="false"
                                        :max-files="1"
                                        :allowed-file-types="['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml']"
                                        collection="about_team_sarah_image"
                                        theme="light"
                                        width="100%"
                                        @file-removed="handleAboutSarahImageFileRemoved"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Michael Image', 'صورة Michael') }}</Label>
                                    <FileUpload
                                        ref="aboutMichaelImageUploadRef"
                                        v-model="aboutMichaelImageTempFolders"
                                        :initial-files="aboutTeamImageFiles?.michael || []"
                                        :allow-multiple="false"
                                        :max-files="1"
                                        :allowed-file-types="['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml']"
                                        collection="about_team_michael_image"
                                        theme="light"
                                        width="100%"
                                        @file-removed="handleAboutMichaelImageFileRemoved"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{ localize('Emily Image', 'صورة Emily') }}</Label>
                                    <FileUpload
                                        ref="aboutEmilyImageUploadRef"
                                        v-model="aboutEmilyImageTempFolders"
                                        :initial-files="aboutTeamImageFiles?.emily || []"
                                        :allow-multiple="false"
                                        :max-files="1"
                                        :allowed-file-types="['image/png', 'image/jpeg', 'image/webp', 'image/gif', 'image/svg+xml']"
                                        collection="about_team_emily_image"
                                        theme="light"
                                        width="100%"
                                        @file-removed="handleAboutEmilyImageFileRemoved"
                                    />
                                </div>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_title_en">{{ localize('Page Title (EN)', 'عنوان الصفحة (EN)') }}</Label>
                            <Input id="about_title_en" v-model="form.about.title.en" />
                            <p v-if="form.errors['about.title.en']" class="text-sm text-red-600">{{ form.errors['about.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_title_ar">{{ localize('Page Title (AR)', 'عنوان الصفحة (AR)') }}</Label>
                            <Input id="about_title_ar" v-model="form.about.title.ar" dir="rtl" />
                            <p v-if="form.errors['about.title.ar']" class="text-sm text-red-600">{{ form.errors['about.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_subtitle_en">{{ localize('Subtitle (EN)', 'العنوان الفرعي (EN)') }}</Label>
                            <textarea id="about_subtitle_en" v-model="form.about.subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_subtitle_ar">{{ localize('Subtitle (AR)', 'العنوان الفرعي (AR)') }}</Label>
                            <textarea id="about_subtitle_ar" v-model="form.about.subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_title_en">{{ localize('Story Title (EN)', 'عنوان القصة (EN)') }}</Label>
                            <Input id="about_story_title_en" v-model="form.about.story_title.en" />
                            <p v-if="form.errors['about.story_title.en']" class="text-sm text-red-600">{{ form.errors['about.story_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_title_ar">{{ localize('Story Title (AR)', 'عنوان القصة (AR)') }}</Label>
                            <Input id="about_story_title_ar" v-model="form.about.story_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.story_title.ar']" class="text-sm text-red-600">{{ form.errors['about.story_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_p1_en">{{ localize('Story Paragraph 1 (EN)', 'الفقرة الأولى من القصة (EN)') }}</Label>
                            <textarea id="about_story_p1_en" v-model="form.about.story_p1.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p1.en']" class="text-sm text-red-600">{{ form.errors['about.story_p1.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_p1_ar">{{ localize('Story Paragraph 1 (AR)', 'الفقرة الأولى من القصة (AR)') }}</Label>
                            <textarea id="about_story_p1_ar" v-model="form.about.story_p1.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p1.ar']" class="text-sm text-red-600">{{ form.errors['about.story_p1.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_p2_en">{{ localize('Story Paragraph 2 (EN)', 'الفقرة الثانية من القصة (EN)') }}</Label>
                            <textarea id="about_story_p2_en" v-model="form.about.story_p2.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p2.en']" class="text-sm text-red-600">{{ form.errors['about.story_p2.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_p2_ar">{{ localize('Story Paragraph 2 (AR)', 'الفقرة الثانية من القصة (AR)') }}</Label>
                            <textarea id="about_story_p2_ar" v-model="form.about.story_p2.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p2.ar']" class="text-sm text-red-600">{{ form.errors['about.story_p2.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_mission_title_en">{{ localize('Mission Title (EN)', 'عنوان الرسالة (EN)') }}</Label>
                            <Input id="about_mission_title_en" v-model="form.about.mission_title.en" />
                            <p v-if="form.errors['about.mission_title.en']" class="text-sm text-red-600">{{ form.errors['about.mission_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_mission_title_ar">{{ localize('Mission Title (AR)', 'عنوان الرسالة (AR)') }}</Label>
                            <Input id="about_mission_title_ar" v-model="form.about.mission_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.mission_title.ar']" class="text-sm text-red-600">{{ form.errors['about.mission_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_mission_subtitle_en">{{ localize('Mission Subtitle (EN)', 'وصف الرسالة (EN)') }}</Label>
                            <textarea id="about_mission_subtitle_en" v-model="form.about.mission_subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.mission_subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.mission_subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_mission_subtitle_ar">{{ localize('Mission Subtitle (AR)', 'وصف الرسالة (AR)') }}</Label>
                            <textarea id="about_mission_subtitle_ar" v-model="form.about.mission_subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.mission_subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.mission_subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_title_en">{{ localize('CTA Title (EN)', 'عنوان الدعوة للإجراء (EN)') }}</Label>
                            <Input id="about_cta_title_en" v-model="form.about.cta_title.en" />
                            <p v-if="form.errors['about.cta_title.en']" class="text-sm text-red-600">{{ form.errors['about.cta_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_title_ar">{{ localize('CTA Title (AR)', 'عنوان الدعوة للإجراء (AR)') }}</Label>
                            <Input id="about_cta_title_ar" v-model="form.about.cta_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_title.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_subtitle_en">{{ localize('CTA Subtitle (EN)', 'وصف الدعوة للإجراء (EN)') }}</Label>
                            <textarea id="about_cta_subtitle_en" v-model="form.about.cta_subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.cta_subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.cta_subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_subtitle_ar">{{ localize('CTA Subtitle (AR)', 'وصف الدعوة للإجراء (AR)') }}</Label>
                            <textarea id="about_cta_subtitle_ar" v-model="form.about.cta_subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.cta_subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_browse_text_en">{{ localize('CTA Browse Button (EN)', 'زر التصفح في الدعوة (EN)') }}</Label>
                            <Input id="about_cta_browse_text_en" v-model="form.about.cta_browse_text.en" />
                            <p v-if="form.errors['about.cta_browse_text.en']" class="text-sm text-red-600">{{ form.errors['about.cta_browse_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_browse_text_ar">{{ localize('CTA Browse Button (AR)', 'زر التصفح في الدعوة (AR)') }}</Label>
                            <Input id="about_cta_browse_text_ar" v-model="form.about.cta_browse_text.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_browse_text.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_browse_text.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_contact_text_en">{{ localize('CTA Contact Button (EN)', 'زر التواصل في الدعوة (EN)') }}</Label>
                            <Input id="about_cta_contact_text_en" v-model="form.about.cta_contact_text.en" />
                            <p v-if="form.errors['about.cta_contact_text.en']" class="text-sm text-red-600">{{ form.errors['about.cta_contact_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_contact_text_ar">{{ localize('CTA Contact Button (AR)', 'زر التواصل في الدعوة (AR)') }}</Label>
                            <Input id="about_cta_contact_text_ar" v-model="form.about.cta_contact_text.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_contact_text.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_contact_text.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 p-5 text-start"
                        :aria-expanded="contentSectionOpen.pdfHeader"
                        @click="toggleContentSection('pdfHeader')"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Contract PDF Header', 'ترويسة العقد') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize('Editable company header content printed at the top of the contract PDF.', 'محتوى ترويسة الشركة الذي يظهر في أعلى ملف العقد.') }}
                            </p>
                        </div>
                        <ChevronDown class="h-5 w-5 shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': contentSectionOpen.pdfHeader }" />
                    </button>

                    <div v-show="contentSectionOpen.pdfHeader" class="grid gap-4 border-t p-5 pt-4 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <Label for="pdf_template_contract">{{ localize('Contract PDF Template', 'قالب PDF للعقد') }}</Label>
                            <select
                                id="pdf_template_contract"
                                v-model="form.pdf_templates.contract"
                                class="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            >
                                <option v-for="option in pdfTemplateOptions" :key="option.value" :value="option.value">
                                    {{ localize(option.label.en || option.value, option.label.ar || option.value) }}
                                </option>
                            </select>
                            <p class="text-xs text-muted-foreground">
                                {{
                                    selectedContractPdfTemplate
                                        ? localize(
                                              selectedContractPdfTemplate.description.en || '',
                                              selectedContractPdfTemplate.description.ar || '',
                                          )
                                        : ''
                                }}
                            </p>
                            <p v-if="form.errors['pdf_templates.contract']" class="text-sm text-red-600">{{ form.errors['pdf_templates.contract'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_company_name_en">{{ localize('Company Name (EN)', 'اسم الشركة (EN)') }}</Label>
                            <Input id="pdf_header_company_name_en" v-model="form.pdf_header.company_name.en" />
                            <p v-if="form.errors['pdf_header.company_name.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.company_name.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_company_name_ar">{{ localize('Company Name (AR)', 'اسم الشركة (AR)') }}</Label>
                            <Input id="pdf_header_company_name_ar" v-model="form.pdf_header.company_name.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.company_name.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.company_name.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_cr_number">{{ localize('C.R Number', 'رقم السجل التجاري') }}</Label>
                            <Input id="pdf_header_cr_number" v-model="form.pdf_header.cr_number" />
                            <p v-if="form.errors['pdf_header.cr_number']" class="text-sm text-red-600">{{ form.errors['pdf_header.cr_number'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_po_box">{{ localize('P.O. Box', 'صندوق البريد') }}</Label>
                            <Input id="pdf_header_po_box" v-model="form.pdf_header.po_box" />
                            <p v-if="form.errors['pdf_header.po_box']" class="text-sm text-red-600">{{ form.errors['pdf_header.po_box'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_pc">{{ localize('P.C', 'الرمز البريدي') }}</Label>
                            <Input id="pdf_header_pc" v-model="form.pdf_header.pc" />
                            <p v-if="form.errors['pdf_header.pc']" class="text-sm text-red-600">{{ form.errors['pdf_header.pc'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_country_en">{{ localize('Country (EN)', 'الدولة (EN)') }}</Label>
                            <Input id="pdf_header_country_en" v-model="form.pdf_header.country.en" />
                            <p v-if="form.errors['pdf_header.country.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.country.en'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_country_ar">{{ localize('Country (AR)', 'الدولة (AR)') }}</Label>
                            <Input id="pdf_header_country_ar" v-model="form.pdf_header.country.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.country.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.country.ar'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_1">{{ localize('GSM 1', 'نقال 1') }}</Label>
                            <Input id="pdf_header_gsm_1" v-model="form.pdf_header.gsm_1" />
                            <p v-if="form.errors['pdf_header.gsm_1']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_1'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_2">{{ localize('GSM 2', 'نقال 2') }}</Label>
                            <Input id="pdf_header_gsm_2" v-model="form.pdf_header.gsm_2" />
                            <p v-if="form.errors['pdf_header.gsm_2']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_2'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_3">{{ localize('GSM 3', 'نقال 3') }}</Label>
                            <Input id="pdf_header_gsm_3" v-model="form.pdf_header.gsm_3" />
                            <p v-if="form.errors['pdf_header.gsm_3']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_3'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_registry_label_en">{{ localize('Registry Label (EN)', 'وسم السجل (EN)') }}</Label>
                            <Input id="pdf_header_registry_label_en" v-model="form.pdf_header.registry_label.en" />
                            <p v-if="form.errors['pdf_header.registry_label.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.registry_label.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_registry_label_ar">{{ localize('Registry Label (AR)', 'وسم السجل (AR)') }}</Label>
                            <Input id="pdf_header_registry_label_ar" v-model="form.pdf_header.registry_label.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.registry_label.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.registry_label.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 p-5 text-start"
                        :aria-expanded="contentSectionOpen.contactPage"
                        @click="toggleContentSection('contactPage')"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Contact Page', 'صفحة اتصل بنا') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ localize('Editable titles and business hours for public Contact page.', 'العناوين وساعات العمل القابلة للتعديل في صفحة اتصل بنا العامة.') }}</p>
                        </div>
                        <ChevronDown class="h-5 w-5 shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': contentSectionOpen.contactPage }" />
                    </button>

                    <div v-show="contentSectionOpen.contactPage" class="grid gap-4 border-t p-5 pt-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact_page_title_en">{{ localize('Page Title (EN)', 'عنوان الصفحة (EN)') }}</Label>
                            <Input id="contact_page_title_en" v-model="form.contact_page.title.en" />
                            <p v-if="form.errors['contact_page.title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_title_ar">{{ localize('Page Title (AR)', 'عنوان الصفحة (AR)') }}</Label>
                            <Input id="contact_page_title_ar" v-model="form.contact_page.title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_subtitle_en">{{ localize('Subtitle (EN)', 'العنوان الفرعي (EN)') }}</Label>
                            <textarea id="contact_page_subtitle_en" v-model="form.contact_page.subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['contact_page.subtitle.en']" class="text-sm text-red-600">{{ form.errors['contact_page.subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_subtitle_ar">{{ localize('Subtitle (AR)', 'العنوان الفرعي (AR)') }}</Label>
                            <textarea id="contact_page_subtitle_ar" v-model="form.contact_page.subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['contact_page.subtitle.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_form_title_en">{{ localize('Form Title (EN)', 'عنوان النموذج (EN)') }}</Label>
                            <Input id="contact_page_form_title_en" v-model="form.contact_page.form_title.en" />
                            <p v-if="form.errors['contact_page.form_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.form_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_form_title_ar">{{ localize('Form Title (AR)', 'عنوان النموذج (AR)') }}</Label>
                            <Input id="contact_page_form_title_ar" v-model="form.contact_page.form_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.form_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.form_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_info_title_en">{{ localize('Sidebar Title (EN)', 'عنوان الشريط الجانبي (EN)') }}</Label>
                            <Input id="contact_page_info_title_en" v-model="form.contact_page.info_title.en" />
                            <p v-if="form.errors['contact_page.info_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.info_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_info_title_ar">{{ localize('Sidebar Title (AR)', 'عنوان الشريط الجانبي (AR)') }}</Label>
                            <Input id="contact_page_info_title_ar" v-model="form.contact_page.info_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.info_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.info_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_hours_en">{{ localize('Business Hours (EN)', 'ساعات العمل (EN)') }}</Label>
                            <textarea id="contact_page_hours_en" v-model="form.contact_page.hours.en" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">{{ localize('Use new line for each row.', 'استخدم سطرًا جديدًا ??? صف.') }}</p>
                            <p v-if="form.errors['contact_page.hours.en']" class="text-sm text-red-600">{{ form.errors['contact_page.hours.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_hours_ar">{{ localize('Business Hours (AR)', 'ساعات العمل (AR)') }}</Label>
                            <textarea id="contact_page_hours_ar" v-model="form.contact_page.hours.ar" rows="4" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">{{ localize('Use new line for each row.', 'استخدم سطرًا جديدًا ??? صف.') }}</p>
                            <p v-if="form.errors['contact_page.hours.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.hours.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_quick_links_title_en">{{ localize('Quick Links Title (EN)', 'عنوان الروابط السريعة (EN)') }}</Label>
                            <Input id="contact_page_quick_links_title_en" v-model="form.contact_page.quick_links_title.en" />
                            <p v-if="form.errors['contact_page.quick_links_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.quick_links_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_quick_links_title_ar">{{ localize('Quick Links Title (AR)', 'عنوان الروابط السريعة (AR)') }}</Label>
                            <Input id="contact_page_quick_links_title_ar" v-model="form.contact_page.quick_links_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.quick_links_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.quick_links_title.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border">
                    <button
                        type="button"
                        class="flex w-full items-center justify-between gap-4 p-5 text-start"
                        :aria-expanded="contentSectionOpen.contactFooter"
                        @click="toggleContentSection('contactFooter')"
                    >
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('Contact & Footer (MVP)', 'التواصل والتذييل') }}</h2>
                            <p class="text-sm text-muted-foreground">{{ localize('Basic public contact info and footer description.', 'معلومات التواصل العامة ووصف التذييل.') }}</p>
                        </div>
                        <ChevronDown class="h-5 w-5 shrink-0 text-muted-foreground transition-transform" :class="{ 'rotate-180': contentSectionOpen.contactFooter }" />
                    </button>

                    <div v-show="contentSectionOpen.contactFooter" class="grid gap-4 border-t p-5 pt-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact_phone">{{ localize('Phone', 'الهاتف') }}</Label>
                            <Input id="contact_phone" v-model="form.contact.phone" placeholder="+965 ..." />
                            <p v-if="form.errors['contact.phone']" class="text-sm text-red-600">{{ form.errors['contact.phone'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_email">{{ localize('Email', 'البريد الإلكتروني') }}</Label>
                            <Input id="contact_email" v-model="form.contact.email" type="email" placeholder="hello@example.com" />
                            <p v-if="form.errors['contact.email']" class="text-sm text-red-600">{{ form.errors['contact.email'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_address_en">{{ localize('Address (EN)', 'العنوان (EN)') }}</Label>
                            <Input id="contact_address_en" v-model="form.contact.address.en" />
                            <p v-if="form.errors['contact.address.en']" class="text-sm text-red-600">{{ form.errors['contact.address.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_address_ar">{{ localize('Address (AR)', 'العنوان (AR)') }}</Label>
                            <Input id="contact_address_ar" v-model="form.contact.address.ar" dir="rtl" />
                            <p v-if="form.errors['contact.address.ar']" class="text-sm text-red-600">{{ form.errors['contact.address.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="footer_desc_en">{{ localize('Footer Description (EN)', 'وصف التذييل (EN)') }}</Label>
                            <textarea id="footer_desc_en" v-model="form.footer.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['footer.description.en']" class="text-sm text-red-600">{{ form.errors['footer.description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="footer_desc_ar">{{ localize('Footer Description (AR)', 'وصف التذييل (AR)') }}</Label>
                            <textarea id="footer_desc_ar" v-model="form.footer.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['footer.description.ar']" class="text-sm text-red-600">{{ form.errors['footer.description.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing" :style="primaryButtonStyle">
                        {{ form.processing ? localize('Saving...', 'جارٍ الحفظ...') : localize('Save Changes', 'حفظ التغييرات') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>
