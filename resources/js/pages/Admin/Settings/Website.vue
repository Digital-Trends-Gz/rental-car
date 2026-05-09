<script setup lang="ts">
import { useTrans } from '@/composables/useTrans';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import AdminLayout from '@/layouts/AdminLayout.vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, ref, watch } from 'vue';

type LocalizedText = { en: string | null; ar: string | null };
type PdfTemplateOption = {
    value: string;
    label: LocalizedText;
    description: LocalizedText;
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
        primary_color: string;
        secondary_color: string;
        tax_percentage: number;
        hero: {
            title: LocalizedText;
            description: LocalizedText;
            button_text: LocalizedText;
            button_link: string | null;
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
    pdfTemplateOptions: PdfTemplateOption[];
    logoFiles: Array<{ id: number; url: string }>;
    seoOgImageFiles?: Array<{ id: number; url: string }>;
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

const form = useForm({
    site_name: props.settings.site_name ?? '',
    logo_url: props.settings.logo_url ?? '',
    logo_temp_folders: [] as string[],
    logo_removed_files: [] as number[],
    seo_og_image_temp_folders: [] as string[],
    seo_og_image_removed_files: [] as number[],
    primary_color: props.settings.primary_color || '#f97316',
    secondary_color: props.settings.secondary_color || '#ea580c',
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
        home: localize('Home', 'ط§ظ„ط±ط¦ظٹط³ظٹط©'),
        fleet: localize('Fleet', 'ط§ظ„ط£ط³ط·ظˆظ„'),
        about: localize('About', 'ظ…ظ† ظ†ط­ظ†'),
        contact: localize('Contact', 'ط§طھطµظ„ ط¨ظ†ط§'),
    };

    return `${labels[pageKey]} | ${suffix}`;
};

const seoPageDefaultDescription = (pageKey: 'home' | 'fleet' | 'about' | 'contact'): string => {
    const shared = localizedSeoText(form.seo.defaults.default_description);

    if (shared) {
        return shared;
    }

    const descriptions = {
        home: localize(`Discover ${previewName.value} and reserve your next rental car online.`, `ط§ظƒطھط´ظپ ${previewName.value} ظˆط§ط­ط¬ط² ط³ظٹط§ط±ط© ط§ظ„ط¥ظٹط¬ط§ط± ط§ظ„طھط§ظ„ظٹط© ط¹ط¨ط± ط§ظ„ط¥ظ†طھط±ظ†طھ.`),
        fleet: localize(`Browse available rental vehicles from ${previewName.value}.`, `ط§ط³طھط¹ط±ط¶ ط³ظٹط§ط±ط§طھ ط§ظ„ط¥ظٹط¬ط§ط± ط§ظ„ظ…طھط§ط­ط© ظ…ظ† ${previewName.value}.`),
        about: localize(`Learn more about ${previewName.value} and its car rental services.`, `طھط¹ط±ظ‘ظپ ط£ظƒط«ط± ط¹ظ„ظ‰ ${previewName.value} ظˆط®ط¯ظ…ط§طھ طھط£ط¬ظٹط± ط§ظ„ط³ظٹط§ط±ط§طھ ط§ظ„ط®ط§طµط© ط¨ظ‡.`),
        contact: localize(`Get in touch with ${previewName.value} for bookings and support.`, `طھظˆط§طµظ„ ظ…ط¹ ${previewName.value} ظ„ظ„ط­ط¬ظˆط²ط§طھ ظˆط§ظ„ط¯ط¹ظ….`),
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
                pageKey === 'home' ? 'ط§ظ„طµظپط­ط© ط§ظ„ط±ط¦ظٹط³ظٹط©' : pageKey === 'fleet' ? 'طµظپط­ط© ط§ظ„ط£ط³ط·ظˆظ„' : pageKey === 'about' ? 'طµظپط­ط© ظ…ظ† ظ†ط­ظ†' : 'طµظپط­ط© ط§طھطµظ„ ط¨ظ†ط§',
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
        home: localize('Home', 'ط§ظ„ط±ط¦ظٹط³ظٹط©'),
        fleet: localize('Fleet', 'ط§ظ„ط£ط³ط·ظˆظ„'),
        about: localize('About', 'ظ…ظ† ظ†ط­ظ†'),
        contact: localize('Contact', 'ط§طھطµظ„ ط¨ظ†ط§'),
        car: localize('Car Rental', 'طھط£ط¬ظٹط± ط³ظٹط§ط±ط©'),
        booking_checkout: localize('Booking Checkout', 'ط¥طھظ…ط§ظ… ط§ظ„ط­ط¬ط²'),
        booking_confirmation: localize('Booking Confirmation', 'طھط£ظƒظٹط¯ ط§ظ„ط­ط¬ط²'),
    };

    return `${labels[pageKey]} | ${suffix}`;
};

const seoPageDefaultDescriptionExtended = (pageKey: SeoPageKey): string => {
    const shared = localizedSeoText(form.seo.defaults.default_description);

    if (shared) {
        return shared;
    }

    const descriptions: Record<SeoPageKey, string> = {
        home: localize(`Discover ${previewName.value} and reserve your next rental car online.`, `ط§ظƒطھط´ظپ ${previewName.value} ظˆط§ط­ط¬ط² ط³ظٹط§ط±ط© ط§ظ„ط¥ظٹط¬ط§ط± ط§ظ„طھط§ظ„ظٹط© ط¹ط¨ط± ط§ظ„ط¥ظ†طھط±ظ†طھ.`),
        fleet: localize(`Browse available rental vehicles from ${previewName.value}.`, `ط§ط³طھط¹ط±ط¶ ط³ظٹط§ط±ط§طھ ط§ظ„ط¥ظٹط¬ط§ط± ط§ظ„ظ…طھط§ط­ط© ظ…ظ† ${previewName.value}.`),
        about: localize(`Learn more about ${previewName.value} and its car rental services.`, `طھط¹ط±ظ‘ظپ ط£ظƒط«ط± ط¹ظ„ظ‰ ${previewName.value} ظˆط®ط¯ظ…ط§طھ طھط£ط¬ظٹط± ط§ظ„ط³ظٹط§ط±ط§طھ ط§ظ„ط®ط§طµط© ط¨ظ‡.`),
        contact: localize(`Get in touch with ${previewName.value} for bookings and support.`, `طھظˆط§طµظ„ ظ…ط¹ ${previewName.value} ظ„ظ„ط­ط¬ظˆط²ط§طھ ظˆط§ظ„ط¯ط¹ظ….`),
        car: localize(`View rental car details and pricing from ${previewName.value}.`, `ط§ط·ظ„ط¹ ط¹ظ„ظ‰ طھظپط§طµظٹظ„ ط§ظ„ط³ظٹط§ط±ط© ظˆط³ط¹ط± ط§ظ„ط¥ظٹط¬ط§ط± ظ„ط¯ظ‰ ${previewName.value}.`),
        booking_checkout: localize(`Choose your payment provider and complete your booking securely with ${previewName.value}.`, `ط§ط®طھط± ظ…ط²ظˆط¯ ط§ظ„ط¯ظپط¹ ظˆط£ظƒظ…ظ„ ط§ظ„ط­ط¬ط² ط¨ط£ظ…ط§ظ† ظ…ط¹ ${previewName.value}.`),
        booking_confirmation: localize(`Review your confirmed booking and reservation details from ${previewName.value}.`, `ط±ط§ط¬ط¹ طھظپط§طµظٹظ„ ط§ظ„ط­ط¬ط² ط§ظ„ظ…ط¤ظƒط¯ ظˆظ…ط¹ظ„ظˆظ…ط§طھ ط§ظ„ط­ط¬ط² ظ„ط¯ظ‰ ${previewName.value}.`),
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
        home: 'ط§ظ„طµظپط­ط© ط§ظ„ط±ط¦ظٹط³ظٹط©',
        fleet: 'طµظپط­ط© ط§ظ„ط£ط³ط·ظˆظ„',
        about: 'طµظپط­ط© ظ…ظ† ظ†ط­ظ†',
        contact: 'طµظپط­ط© ط§طھطµظ„ ط¨ظ†ط§',
        car: 'طµظپط­ط© ط§ظ„ط³ظٹط§ط±ط©',
        booking_checkout: 'طµظپط­ط© ط¥طھظ…ط§ظ… ط§ظ„ط­ط¬ط²',
        booking_confirmation: 'طµظپط­ط© طھط£ظƒظٹط¯ ط§ظ„ط­ط¬ط²',
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
                label: localize('Title length looks good', 'ط·ظˆظ„ ط§ظ„ط¹ظ†ظˆط§ظ† ظ…ظ†ط§ط³ط¨'),
                failLabel: localize('Recommended title length is 30-60 characters', 'ط§ظ„ط·ظˆظ„ ط§ظ„ظ…ظˆطµظ‰ ط¨ظ‡ ظ„ظ„ط¹ظ†ظˆط§ظ† ظ‡ظˆ 30-60 ط­ط±ظپظ‹ط§'),
            },
            {
                ok: description.length >= 70 && description.length <= 160,
                label: localize('Description length looks good', 'ط·ظˆظ„ ط§ظ„ظˆطµظپ ظ…ظ†ط§ط³ط¨'),
                failLabel: localize('Recommended description length is 70-160 characters', 'ط§ظ„ط·ظˆظ„ ط§ظ„ظ…ظˆطµظ‰ ط¨ظ‡ ظ„ظ„ظˆطµظپ ظ‡ظˆ 70-160 ط­ط±ظپظ‹ط§'),
            },
            {
                ok: Boolean((form.seo.defaults.og_image || uploadedSeoOgImageUrl.value || previewLogoUrl.value || '').trim()),
                label: localize('Open Graph image is set', 'طµظˆط±ط© Open Graph ظ…ط¶ط¨ظˆط·ط©'),
                failLabel: localize('Set an Open Graph image for sharing previews', 'ط­ط¯ط¯ طµظˆط±ط© Open Graph ظ„ظ…ط¹ط§ظٹظ†ط§طھ ط§ظ„ظ…ط´ط§ط±ظƒط©'),
            },
            {
                ok: canonicalLooksValid,
                label: localize('Canonical URL is valid', 'ط±ط§ط¨ط· Canonical طµط­ظٹط­'),
                failLabel: localize('Canonical URL must start with http:// or https://', 'ط±ط§ط¨ط· Canonical ظٹط¬ط¨ ط£ظ† ظٹط¨ط¯ط£ ط¨ظ€ http:// ط£ظˆ https://'),
            },
            {
                ok: slugLooksValid,
                label: localize('Slug format looks clean', 'طھظ†ط³ظٹظ‚ ط§ظ„ط±ط§ط¨ط· ط§ظ„ظ…ط®طھطµط± ط³ظ„ظٹظ…'),
                failLabel: localize('Slug should use clean URL segments without spaces', 'ظٹط¬ط¨ ط£ظ† ظٹط³طھط®ط¯ظ… ط§ظ„ط±ط§ط¨ط· ط§ظ„ظ…ط®طھطµط± ظ…ظ‚ط§ط·ط¹ ظ†ط¸ظٹظپط© ط¨ط¯ظˆظ† ظ…ط³ط§ظپط§طھ'),
            },
            {
                ok: hreflangLooksValid,
                label: localize('hreflang alternates are available for enabled locales', 'ط±ظˆط§ط¨ط· hreflang ظ…طھظˆظپط±ط© ظ„ظ„ط؛ط§طھ ط§ظ„ظ…ظپط¹ظ„ط©'),
                failLabel: localize('hreflang alternates are missing for one or more enabled locales', 'ط±ظˆط§ط¨ط· hreflang ظ…ظپظ‚ظˆط¯ط© ظ„ط¥ط­ط¯ظ‰ ط§ظ„ظ„ط؛ط§طھ ط§ظ„ظ…ظپط¹ظ„ط© ط£ظˆ ط£ظƒط«ط±'),
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
            label: localize('Good', 'ط¬ظٹط¯'),
            description: localize('Most SEO signals are in good shape.', 'ظ…ط¹ط¸ظ… ط¥ط´ط§ط±ط§طھ SEO ظپظٹ ظˆط¶ط¹ ط¬ظٹط¯.'),
            className: 'bg-emerald-100 text-emerald-700',
        };
    }

    if (ratio >= 0.5) {
        return {
            label: localize('Needs Work', 'ظٹط­طھط§ط¬ طھط­ط³ظٹظ†'),
            description: localize('Some pages still need SEO cleanup.', 'ط¨ط¹ط¶ ط§ظ„طµظپط­ط§طھ ظ…ط§ ط²ط§ظ„طھ طھط­طھط§ط¬ طھط­ط³ظٹظ† SEO.'),
            className: 'bg-amber-100 text-amber-700',
        };
    }

    return {
        label: localize('Critical', 'ط­ط±ط¬'),
        description: localize('SEO coverage is weak and should be fixed before publishing changes.', 'طھط؛ط·ظٹط© SEO ط¶ط¹ظٹظپط© ظˆظٹط¬ط¨ ط¥طµظ„ط§ط­ظ‡ط§ ظ‚ط¨ظ„ ط§ط¹طھظ…ط§ط¯ ط§ظ„طھط؛ظٹظٹط±ط§طھ.'),
        className: 'bg-red-100 text-red-700',
    };
});
const seoSaveBlockedMessage = ref('');
const seoCopyMessage = ref('');

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const seoFileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const logoTempFolders = ref<string[]>([]);
const logoRemovedFileIds = ref<number[]>([]);
const seoOgImageRemovedFileIds = ref<number[]>([]);
const showAdvancedBranding = ref(false);

watch(
    logoTempFolders,
    (value) => {
        form.logo_temp_folders = [...value];
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
        seoCopyMessage.value = localize('Clipboard is not available in this browser.', 'ط§ظ„ط­ط§ظپط¸ط© ط؛ظٹط± ظ…طھط§ط­ط© ظپظٹ ظ‡ط°ط§ ط§ظ„ظ…طھطµظپط­.');
        return;
    }

    navigator.clipboard.writeText(summary)
        .then(() => {
            seoCopyMessage.value = localize(`Copied SEO summary for ${preview.label}.`, `طھظ… ظ†ط³ط® ظ…ظ„ط®طµ SEO ظ„طµظپط­ط© ${preview.label}.`);
        })
        .catch(() => {
            seoCopyMessage.value = localize('Could not copy SEO summary.', 'طھط¹ط°ط± ظ†ط³ط® ظ…ظ„ط®طµ SEO.');
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
        seoCopyMessage.value = localize('SEO report export is not available in this environment.', 'طھطµط¯ظٹط± طھظ‚ط±ظٹط± SEO ط؛ظٹط± ظ…طھط§ط­ ظپظٹ ظ‡ط°ظ‡ ط§ظ„ط¨ظٹط¦ط©.');
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
    seoCopyMessage.value = localize('SEO report exported successfully.', 'طھظ… طھطµط¯ظٹط± طھظ‚ط±ظٹط± SEO ط¨ظ†ط¬ط§ط­.');
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

function handleSeoOgImageFileRemoved(data: { type: string; fileId?: number }) {
    if (data.type === 'existing' && data.fileId) {
        seoOgImageRemovedFileIds.value.push(data.fileId);
        form.seo_og_image_removed_files = [...new Set(seoOgImageRemovedFileIds.value)];
    }
}

function submit() {
    if (false && seoBlockingPages.value.length > 0) {
        const labels = seoBlockingPages.value.map((page) => page.label).join(', ');
        seoSaveBlockedMessage.value = localize(
            `SEO save blocked. Fix these pages first: ${labels}.`,
            `طھظ… ظ…ظ†ط¹ ط§ظ„ط­ظپط¸ ط¨ط³ط¨ط¨ ط¶ط¹ظپ SEO ظپظٹ ظ‡ط°ظ‡ ط§ظ„طµظپط­ط§طھ: ${labels}.`,
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
            form.seo_og_image_temp_folders = [];
            form.seo_og_image_removed_files = [];
            seoOgImageRemovedFileIds.value = [];
            seoFileUploadRef.value?.resetFiles();
        },
    });
}
</script>

<template>
    <Head :title="localize('Website Settings', 'ط¥ط¹ط¯ط§ط¯ط§طھ ط§ظ„ظ…ظˆظ‚ط¹')" />

    <AdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div class="flex items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold">{{ localize('Website Settings', 'ط¥ط¹ط¯ط§ط¯ط§طھ ط§ظ„ظ…ظˆظ‚ط¹') }}</h1>
                    <p class="text-sm text-muted-foreground">
                        {{ localize('Customize your tenant website branding and homepage content (Arabic / English).', 'ط®طµطµ ظ‡ظˆظٹط© ظ…ظˆظ‚ط¹ ط§ظ„ظ…ط³طھط£ط¬ط± ظˆظ…ط­طھظˆظ‰ ط§ظ„طµظپط­ط© ط§ظ„ط±ط¦ظٹط³ظٹط© ط¨ط§ظ„ظ„ط؛طھظٹظ† ط§ظ„ط¹ط±ط¨ظٹط© ظˆط§ظ„ط¥ظ†ط¬ظ„ظٹط²ظٹط©.') }}
                    </p>
                </div>
                <Button :disabled="form.processing" @click="submit">
                    {{ form.processing ? localize('Saving...', 'ط¬ط§ط±ظچ ط§ظ„ط­ظپط¸...') : localize('Save Changes', 'ط­ظپط¸ ط§ظ„طھط؛ظٹظٹط±ط§طھ') }}
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
                <div class="font-medium">{{ localize('Please fix the following errors:', 'ظٹط±ط¬ظ‰ طھطµط­ظٹط­ ط§ظ„ط£ط®ط·ط§ط، ط§ظ„طھط§ظ„ظٹط©:') }}</div>
                <ul class="mt-1 list-disc pl-5">
                    <li v-for="(message, idx) in formErrorList" :key="idx">{{ message }}</li>
                </ul>
            </div>

            <div v-if="false && seoSaveBlockedMessage" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                {{ seoSaveBlockedMessage }}
            </div>
            <div v-if="false && seoBlockingPages.length" class="rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                {{ localize('SEO saving is blocked until every page has at least one valid SEO signal.', 'طھظ… ظ…ظ†ط¹ ط­ظپط¸ SEO ط­طھظ‰ ظٹط­طھظˆظٹ ظƒظ„ طµظپط­ط© ط¹ظ„ظ‰ ط¥ط´ط§ط±ط© SEO طµط­ظٹط­ط© ظˆط§ط­ط¯ط© ط¹ظ„ظ‰ ط§ظ„ط£ظ‚ظ„.') }}
            </div>

            <form class="space-y-6" @submit.prevent="submit">
                <section class="rounded-lg border p-5">
                    <div class="mb-4">
                        <h2 class="text-lg font-semibold">{{ localize('Branding', 'ط§ظ„ظ‡ظˆظٹط© ط§ظ„ط¨طµط±ظٹط©') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Site identity, logo URL, and brand colors.', 'ظ‡ظˆظٹط© ط§ظ„ظ…ظˆظ‚ط¹ ظˆط±ط§ط¨ط· ط§ظ„ط´ط¹ط§ط± ظˆط£ظ„ظˆط§ظ† ط§ظ„ط¹ظ„ط§ظ…ط© ط§ظ„طھط¬ط§ط±ظٹط©.') }}</p>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div class="space-y-2 md:col-span-2">
                                <Label for="site_name">{{ localize('Site Name', 'ط§ط³ظ… ط§ظ„ظ…ظˆظ‚ط¹') }}</Label>
                                <Input id="site_name" v-model="form.site_name" :placeholder="localize('Tenant website name', 'ط§ط³ظ… ظ…ظˆظ‚ط¹ ط§ظ„ظ…ط³طھط£ط¬ط±')" />
                                <p v-if="form.errors.site_name" class="text-sm text-red-600">{{ form.errors.site_name }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label>{{ localize('Logo Upload (System)', 'ط±ظپط¹ ط§ظ„ط´ط¹ط§ط± (ط§ظ„ظ†ط¸ط§ظ…)') }}</Label>
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
                                    {{ localize('Upload logo to your system. New upload replaces the previous logo.', 'ط§ط±ظپط¹ ط§ظ„ط´ط¹ط§ط± ط¥ظ„ظ‰ ط§ظ„ظ†ط¸ط§ظ…. ط£ظٹ ط±ظپط¹ ط¬ط¯ظٹط¯ ط³ظٹط³طھط¨ط¯ظ„ ط§ظ„ط´ط¹ط§ط± ط§ظ„ط³ط§ط¨ظ‚.') }}
                                </p>
                            </div>

                            <div class="md:col-span-2 rounded-md border bg-muted/20 p-3 space-y-3">
                                <div class="flex items-center justify-between gap-3">
                                    <div>
                                        <div class="text-sm font-medium">{{ localize('Advanced Branding Options', 'ط®ظٹط§ط±ط§طھ ط§ظ„ظ‡ظˆظٹط© ط§ظ„ظ…طھظ‚ط¯ظ…ط©') }}</div>
                                        <p class="text-xs text-muted-foreground">{{ localize('Optional fallback logo URL (used only if no uploaded logo exists).', 'ط±ط§ط¨ط· ط´ط¹ط§ط± ط§ط­طھظٹط§ط·ظٹ ط§ط®طھظٹط§ط±ظٹ ظˆظٹظڈط³طھط®ط¯ظ… ظپظ‚ط· ط¥ط°ط§ ظ„ظ… ظٹظˆط¬ط¯ ط´ط¹ط§ط± ظ…ط±ظپظˆط¹.') }}</p>
                                    </div>
                                    <Button type="button" variant="outline" size="sm" @click="showAdvancedBranding = !showAdvancedBranding">
                                        {{ showAdvancedBranding ? localize('Hide Advanced', 'ط¥ط®ظپط§ط، ط§ظ„ظ…طھظ‚ط¯ظ…') : localize('Show Advanced', 'ط¥ط¸ظ‡ط§ط± ط§ظ„ظ…طھظ‚ط¯ظ…') }}
                                    </Button>
                                </div>

                                <div v-if="showAdvancedBranding" class="space-y-2">
                                    <Label for="logo_url">{{ localize('Fallback Logo URL', 'ط±ط§ط¨ط· ط§ظ„ط´ط¹ط§ط± ط§ظ„ط§ط­طھظٹط§ط·ظٹ') }}</Label>
                                    <Input id="logo_url" v-model="form.logo_url" placeholder="https://example.com/logo.png" />
                                    <p class="text-xs text-muted-foreground">
                                        {{ localize('This URL is used only when no uploaded logo exists in the system.', 'ظٹظڈط³طھط®ط¯ظ… ظ‡ط°ط§ ط§ظ„ط±ط§ط¨ط· ظپظ‚ط· ط¹ظ†ط¯ظ…ط§ ظ„ط§ ظٹظˆط¬ط¯ ط´ط¹ط§ط± ظ…ط±ظپظˆط¹ ظپظٹ ط§ظ„ظ†ط¸ط§ظ….') }}
                                    </p>
                                    <p v-if="form.errors.logo_url" class="text-sm text-red-600">{{ form.errors.logo_url }}</p>
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label for="primary_color">{{ localize('Primary Color', 'ط§ظ„ظ„ظˆظ† ط§ظ„ط£ط³ط§ط³ظٹ') }}</Label>
                                <div class="flex items-center gap-2">
                                    <input id="primary_color" v-model="form.primary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.primary_color" placeholder="#f97316" />
                                </div>
                                <p v-if="form.errors.primary_color" class="text-sm text-red-600">{{ form.errors.primary_color }}</p>
                            </div>

                            <div class="space-y-2">
                                <Label for="secondary_color">{{ localize('Secondary Color', 'ط§ظ„ظ„ظˆظ† ط§ظ„ط«ط§ظ†ظˆظٹ') }}</Label>
                                <div class="flex items-center gap-2">
                                    <input id="secondary_color" v-model="form.secondary_color" type="color" class="h-10 w-14 rounded border border-input bg-white p-1" />
                                    <Input v-model="form.secondary_color" placeholder="#ea580c" />
                                </div>
                                <p v-if="form.errors.secondary_color" class="text-sm text-red-600">{{ form.errors.secondary_color }}</p>
                            </div>

                            <div class="space-y-2 md:col-span-2">
                                <Label for="tax_percentage">{{ localize('Booking Tax Percentage', 'ظ†ط³ط¨ط© ط¶ط±ظٹط¨ط© ط§ظ„ط­ط¬ط²') }}</Label>
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
                                    {{ localize('Set `0` to hide tax in booking page.', 'ط¶ط¹ `0` ظ„ط¥ط®ظپط§ط، ط§ظ„ط¶ط±ظٹط¨ط© ظپظٹ طµظپط­ط© ط§ظ„ط­ط¬ط².') }}
                                </p>
                                <p v-if="form.errors.tax_percentage" class="text-sm text-red-600">{{ form.errors.tax_percentage }}</p>
                            </div>
                        </div>

                        <div class="rounded-lg border p-4">
                            <div class="text-sm font-medium mb-3">{{ localize('Preview', 'ظ…ط¹ط§ظٹظ†ط©') }}</div>
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
                                        {{ localize('CTA Preview', 'ظ…ط¹ط§ظٹظ†ط© ط²ط± ط§ظ„ط¯ط¹ظˆط©') }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h2 class="text-lg font-semibold">{{ localize('SEO', 'طھظ‡ظٹط¦ط© ظ…ط­ط±ظƒط§طھ ط§ظ„ط¨ط­ط«') }}</h2>
                            <p class="text-sm text-muted-foreground">
                                {{ localize('SEO management has moved to a dedicated page for cleaner settings management.', 'طھظ… ظ†ظ‚ظ„ ط¥ط¯ط§ط±ط© SEO ط¥ظ„ظ‰ طµظپط­ط© ظ…ط³طھظ‚ظ„ط© ظ„طھط¨ظ‚ظ‰ ط§ظ„ط¥ط¹ط¯ط§ط¯ط§طھ ط§ظ„ط¹ط§ظ…ط© ط£ظˆط¶ط­.') }}
                            </p>
                        </div>
                        <div class="flex items-center gap-2">
                            <Link v-if="props.actions.seo_edit" :href="props.actions.seo_edit">
                                <Button variant="outline">{{ localize('Open SEO Settings', 'ظپطھط­ ط¥ط¹ط¯ط§ط¯ط§طھ SEO') }}</Button>
                            </Link>
                            <Link v-if="props.actions.seo_audit" :href="props.actions.seo_audit">
                                <Button>{{ localize('Open SEO Audit', 'ظپطھط­ طھط¯ظ‚ظٹظ‚ SEO') }}</Button>
                            </Link>
                        </div>
                    </div>
                </section>

                <section v-if="false" class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('SEO', 'طھظ‡ظٹط¦ط© ظ…ط­ط±ظƒط§طھ ط§ظ„ط¨ط­ط«') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('Control page titles, descriptions, canonical URLs, and Open Graph defaults for your public website.', 'طھط­ظƒظ… ظپظٹ ط¹ظ†ط§ظˆظٹظ† ط§ظ„طµظپط­ط§طھ ظˆط§ظ„ط£ظˆطµط§ظپ ظˆط±ظˆط§ط¨ط· canonical ظˆط¥ط¹ط¯ط§ط¯ط§طھ Open Graph ط§ظ„ط§ظپطھط±ط§ط¶ظٹط© ظ„ظ…ظˆظ‚ط¹ظƒ ط§ظ„ط¹ط§ظ….') }}
                        </p>
                    </div>

                    <div class="rounded-lg border bg-muted/20 p-4">
                        <div class="flex items-start justify-between gap-4">
                            <div class="space-y-1">
                                <h3 class="font-semibold">{{ localize('Overall SEO Status', 'ط§ظ„ط­ط§ظ„ط© ط§ظ„ط¹ط§ظ…ط© ظ„ظ€ SEO') }}</h3>
                                <p class="text-sm text-muted-foreground">{{ seoHealthStatus.description }}</p>
                            </div>
                            <div class="flex items-center gap-2">
                                <Link v-if="props.actions.seo_audit" :href="props.actions.seo_audit">
                                    <button
                                        type="button"
                                        class="rounded-full border px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    >
                                        {{ localize('Open SEO Audit', 'ظپطھط­ طھط¯ظ‚ظٹظ‚ SEO') }}
                                    </button>
                                </Link>
                                <button
                                    type="button"
                                    class="rounded-full border px-3 py-1 text-xs font-medium text-slate-700 hover:bg-slate-50"
                                    @click="exportSeoReport"
                                >
                                    {{ localize('Export SEO Report', 'طھطµط¯ظٹط± طھظ‚ط±ظٹط± SEO') }}
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
                            <Label for="seo_title_suffix_en">{{ localize('Title Suffix (EN)', 'ظ„ط§ط­ظ‚ط© ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                            <Input id="seo_title_suffix_en" v-model="form.seo.defaults.title_suffix.en" />
                            <p v-if="form.errors['seo.defaults.title_suffix.en']" class="text-sm text-red-600">{{ form.errors['seo.defaults.title_suffix.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_title_suffix_ar">{{ localize('Title Suffix (AR)', 'ظ„ط§ط­ظ‚ط© ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                            <Input id="seo_title_suffix_ar" v-model="form.seo.defaults.title_suffix.ar" dir="rtl" />
                            <p v-if="form.errors['seo.defaults.title_suffix.ar']" class="text-sm text-red-600">{{ form.errors['seo.defaults.title_suffix.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="seo_default_description_en">{{ localize('Default Description (EN)', 'ط§ظ„ظˆطµظپ ط§ظ„ط§ظپطھط±ط§ط¶ظٹ (EN)') }}</Label>
                            <textarea id="seo_default_description_en" v-model="form.seo.defaults.default_description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['seo.defaults.default_description.en']" class="text-sm text-red-600">{{ form.errors['seo.defaults.default_description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="seo_default_description_ar">{{ localize('Default Description (AR)', 'ط§ظ„ظˆطµظپ ط§ظ„ط§ظپطھط±ط§ط¶ظٹ (AR)') }}</Label>
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
                            <Label for="seo_robots">{{ localize('Robots', 'طھط¹ظ„ظٹظ…ط§طھ Robots') }}</Label>
                            <Input id="seo_robots" v-model="form.seo.defaults.robots" placeholder="index,follow" />
                            <p v-if="form.errors['seo.defaults.robots']" class="text-sm text-red-600">{{ form.errors['seo.defaults.robots'] }}</p>
                        </div>
                    </div>

                    <div class="rounded-lg border bg-muted/20 p-4 space-y-3">
                        <div>
                            <h3 class="font-semibold">{{ localize('Search Preview', 'ظ…ط¹ط§ظٹظ†ط© ظ†طھط§ط¦ط¬ ط§ظ„ط¨ط­ط«') }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Live preview for each public page using the current form values.', 'ظ…ط¹ط§ظٹظ†ط© ط­ظٹط© ظ„ظƒظ„ طµظپط­ط© ط¹ط§ظ…ط© ط¨ط§ط³طھط®ط¯ط§ظ… ط§ظ„ظ‚ظٹظ… ط§ظ„ط­ط§ظ„ظٹط© ظپظٹ ط§ظ„ظ†ظ…ظˆط°ط¬.') }}
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
                                            {{ localize('Copy', 'ظ†ط³ط®') }}
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
                            <h3 class="font-semibold">{{ localize('Open Graph Preview', 'ظ…ط¹ط§ظٹظ†ط© Open Graph') }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Preview how shared links can appear on WhatsApp, Facebook, and similar platforms.', 'ظ…ط¹ط§ظٹظ†ط© ط´ظƒظ„ ط§ظ„ط±ط§ط¨ط· ط¹ظ†ط¯ ظ…ط´ط§ط±ظƒطھظ‡ ظپظٹ ظˆط§طھط³ط§ط¨ ظˆظپظٹط³ط¨ظˆظƒ ظˆط§ظ„ظ…ظ†طµط§طھ ط§ظ„ظ…ط´ط§ط¨ظ‡ط©.') }}
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
                                        {{ localize('No Open Graph image selected yet.', 'ظ„ظ… ظٹطھظ… طھط­ط¯ظٹط¯ طµظˆط±ط© Open Graph ط¨ط¹ط¯.') }}
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
                            <h3 class="font-semibold">{{ localize('Twitter / X Card Preview', 'ظ…ط¹ط§ظٹظ†ط© ط¨ط·ط§ظ‚ط© Twitter / X') }}</h3>
                            <p class="text-xs text-muted-foreground">
                                {{ localize('Preview the large summary card style used by X/Twitter when the page is shared.', 'ظ…ط¹ط§ظٹظ†ط© ط´ظƒظ„ ط§ظ„ط¨ط·ط§ظ‚ط© ط§ظ„ظƒط¨ظٹط±ط© ط§ظ„ظ…ط³طھط®ط¯ظ…ط© ظپظٹ X/Twitter ط¹ظ†ط¯ ظ…ط´ط§ط±ظƒط© ط§ظ„طµظپط­ط©.') }}
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
                                        {{ localize('No card image available.', 'ظ„ط§ طھظˆط¬ط¯ طµظˆط±ط© ظ„ظ„ط¨ط·ط§ظ‚ط©.') }}
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
                            <h3 class="font-semibold">{{ localize('Home Page SEO', 'SEO ط§ظ„طµظپط­ط© ط§ظ„ط±ط¦ظٹط³ظٹط©') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_home_title_en">{{ localize('Title (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                                <Input id="seo_home_title_en" v-model="form.seo.pages.home.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_title_ar">{{ localize('Title (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                                <Input id="seo_home_title_ar" v-model="form.seo.pages.home.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_description_en">{{ localize('Description (EN)', 'ط§ظ„ظˆطµظپ (EN)') }}</Label>
                                <textarea id="seo_home_description_en" v-model="form.seo.pages.home.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_description_ar">{{ localize('Description (AR)', 'ط§ظ„ظˆطµظپ (AR)') }}</Label>
                                <textarea id="seo_home_description_ar" v-model="form.seo.pages.home.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_home_canonical">{{ localize('Canonical URL', 'ط±ط§ط¨ط· Canonical') }}</Label>
                                <Input id="seo_home_canonical" v-model="form.seo.pages.home.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Fleet Page SEO', 'SEO طµظپط­ط© ط§ظ„ط£ط³ط·ظˆظ„') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_fleet_title_en">{{ localize('Title (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                                <Input id="seo_fleet_title_en" v-model="form.seo.pages.fleet.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_title_ar">{{ localize('Title (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                                <Input id="seo_fleet_title_ar" v-model="form.seo.pages.fleet.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_description_en">{{ localize('Description (EN)', 'ط§ظ„ظˆطµظپ (EN)') }}</Label>
                                <textarea id="seo_fleet_description_en" v-model="form.seo.pages.fleet.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_description_ar">{{ localize('Description (AR)', 'ط§ظ„ظˆطµظپ (AR)') }}</Label>
                                <textarea id="seo_fleet_description_ar" v-model="form.seo.pages.fleet.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_fleet_canonical">{{ localize('Canonical URL', 'ط±ط§ط¨ط· Canonical') }}</Label>
                                <Input id="seo_fleet_canonical" v-model="form.seo.pages.fleet.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('About Page SEO', 'SEO طµظپط­ط© ظ…ظ† ظ†ط­ظ†') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_about_title_en">{{ localize('Title (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                                <Input id="seo_about_title_en" v-model="form.seo.pages.about.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_title_ar">{{ localize('Title (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                                <Input id="seo_about_title_ar" v-model="form.seo.pages.about.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_description_en">{{ localize('Description (EN)', 'ط§ظ„ظˆطµظپ (EN)') }}</Label>
                                <textarea id="seo_about_description_en" v-model="form.seo.pages.about.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_description_ar">{{ localize('Description (AR)', 'ط§ظ„ظˆطµظپ (AR)') }}</Label>
                                <textarea id="seo_about_description_ar" v-model="form.seo.pages.about.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_about_canonical">{{ localize('Canonical URL', 'ط±ط§ط¨ط· Canonical') }}</Label>
                                <Input id="seo_about_canonical" v-model="form.seo.pages.about.canonical_url" />
                            </div>
                        </div>

                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Contact Page SEO', 'SEO طµظپط­ط© ط§طھطµظ„ ط¨ظ†ط§') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_contact_title_en">{{ localize('Title (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                                <Input id="seo_contact_title_en" v-model="form.seo.pages.contact.title.en" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_title_ar">{{ localize('Title (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                                <Input id="seo_contact_title_ar" v-model="form.seo.pages.contact.title.ar" dir="rtl" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_description_en">{{ localize('Description (EN)', 'ط§ظ„ظˆطµظپ (EN)') }}</Label>
                                <textarea id="seo_contact_description_en" v-model="form.seo.pages.contact.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_description_ar">{{ localize('Description (AR)', 'ط§ظ„ظˆطµظپ (AR)') }}</Label>
                                <textarea id="seo_contact_description_ar" v-model="form.seo.pages.contact.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_contact_canonical">{{ localize('Canonical URL', 'ط±ط§ط¨ط· Canonical') }}</Label>
                                <Input id="seo_contact_canonical" v-model="form.seo.pages.contact.canonical_url" />
                            </div>
                        </div>
                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Car Details Page SEO', 'SEO طµظپط­ط© ط§ظ„ط³ظٹط§ط±ط©') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_car_title_en">{{ localize('Title (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                                <Input id="seo_car_title_en" v-model="form.seo.pages.car.title.en" placeholder="Use :car as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_title_ar">{{ localize('Title (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                                <Input id="seo_car_title_ar" v-model="form.seo.pages.car.title.ar" dir="rtl" placeholder="ط§ط³طھط®ط¯ظ… :car ظƒظ…طھط؛ظٹط±" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_description_en">{{ localize('Description (EN)', 'ط§ظ„ظˆطµظپ (EN)') }}</Label>
                                <textarea id="seo_car_description_en" v-model="form.seo.pages.car.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_description_ar">{{ localize('Description (AR)', 'ط§ظ„ظˆطµظپ (AR)') }}</Label>
                                <textarea id="seo_car_description_ar" v-model="form.seo.pages.car.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_car_canonical">{{ localize('Canonical URL', 'ط±ط§ط¨ط· Canonical') }}</Label>
                                <Input id="seo_car_canonical" v-model="form.seo.pages.car.canonical_url" />
                            </div>
                        </div>
                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Booking Checkout SEO', 'SEO طµظپط­ط© ط¥طھظ…ط§ظ… ط§ظ„ط­ط¬ط²') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_checkout_title_en">{{ localize('Title (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                                <Input id="seo_checkout_title_en" v-model="form.seo.pages.booking_checkout.title.en" placeholder="Use :reservation as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_title_ar">{{ localize('Title (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                                <Input id="seo_checkout_title_ar" v-model="form.seo.pages.booking_checkout.title.ar" dir="rtl" placeholder="ط§ط³طھط®ط¯ظ… :reservation ظƒظ…طھط؛ظٹط±" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_description_en">{{ localize('Description (EN)', 'ط§ظ„ظˆطµظپ (EN)') }}</Label>
                                <textarea id="seo_checkout_description_en" v-model="form.seo.pages.booking_checkout.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_description_ar">{{ localize('Description (AR)', 'ط§ظ„ظˆطµظپ (AR)') }}</Label>
                                <textarea id="seo_checkout_description_ar" v-model="form.seo.pages.booking_checkout.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_checkout_canonical">{{ localize('Canonical URL', 'ط±ط§ط¨ط· Canonical') }}</Label>
                                <Input id="seo_checkout_canonical" v-model="form.seo.pages.booking_checkout.canonical_url" />
                            </div>
                        </div>
                        <div class="rounded-lg border p-4 space-y-3">
                            <h3 class="font-semibold">{{ localize('Booking Confirmation SEO', 'SEO طµظپط­ط© طھط£ظƒظٹط¯ ط§ظ„ط­ط¬ط²') }}</h3>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_title_en">{{ localize('Title (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                                <Input id="seo_confirmation_title_en" v-model="form.seo.pages.booking_confirmation.title.en" placeholder="Use :reservation as placeholder" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_title_ar">{{ localize('Title (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                                <Input id="seo_confirmation_title_ar" v-model="form.seo.pages.booking_confirmation.title.ar" dir="rtl" placeholder="ط§ط³طھط®ط¯ظ… :reservation ظƒظ…طھط؛ظٹط±" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_description_en">{{ localize('Description (EN)', 'ط§ظ„ظˆطµظپ (EN)') }}</Label>
                                <textarea id="seo_confirmation_description_en" v-model="form.seo.pages.booking_confirmation.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_description_ar">{{ localize('Description (AR)', 'ط§ظ„ظˆطµظپ (AR)') }}</Label>
                                <textarea id="seo_confirmation_description_ar" v-model="form.seo.pages.booking_confirmation.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            </div>
                            <div class="space-y-2">
                                <Label for="seo_confirmation_canonical">{{ localize('Canonical URL', 'ط±ط§ط¨ط· Canonical') }}</Label>
                                <Input id="seo_confirmation_canonical" v-model="form.seo.pages.booking_confirmation.canonical_url" />
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Hero Section', 'ط§ظ„ظ‚ط³ظ… ط§ظ„ط±ط¦ظٹط³ظٹ') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Main banner texts for the tenant homepage.', 'ط§ظ„ظ†طµظˆطµ ط§ظ„ط±ط¦ظٹط³ظٹط© ظ„ظˆط§ط¬ظ‡ط© ط§ظ„طµظپط­ط© ط§ظ„ط±ط¦ظٹط³ظٹط© ط§ظ„ط®ط§طµط© ط¨ط§ظ„ظ…ط³طھط£ط¬ط±.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="hero_title_en">{{ localize('Hero Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ظ‚ط³ظ… ط§ظ„ط±ط¦ظٹط³ظٹ (EN)') }}</Label>
                            <Input id="hero_title_en" v-model="form.hero.title.en" placeholder="Rent the perfect car today" />
                            <p v-if="form.errors['hero.title.en']" class="text-sm text-red-600">{{ form.errors['hero.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_title_ar">{{ localize('Hero Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ظ‚ط³ظ… ط§ظ„ط±ط¦ظٹط³ظٹ (AR)') }}</Label>
                            <Input id="hero_title_ar" v-model="form.hero.title.ar" placeholder="ط§ط³طھط£ط¬ط± ط§ظ„ط³ظٹط§ط±ط© ط§ظ„ظ…ظ†ط§ط³ط¨ط© ط§ظ„ظٹظˆظ…" dir="rtl" />
                            <p v-if="form.errors['hero.title.ar']" class="text-sm text-red-600">{{ form.errors['hero.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_desc_en">{{ localize('Hero Description (EN)', 'ظˆطµظپ ط§ظ„ظ‚ط³ظ… ط§ظ„ط±ط¦ظٹط³ظٹ (EN)') }}</Label>
                            <textarea id="hero_desc_en" v-model="form.hero.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['hero.description.en']" class="text-sm text-red-600">{{ form.errors['hero.description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_desc_ar">{{ localize('Hero Description (AR)', 'ظˆطµظپ ط§ظ„ظ‚ط³ظ… ط§ظ„ط±ط¦ظٹط³ظٹ (AR)') }}</Label>
                            <textarea id="hero_desc_ar" v-model="form.hero.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['hero.description.ar']" class="text-sm text-red-600">{{ form.errors['hero.description.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="hero_button_text_en">{{ localize('Button Text (EN)', 'ظ†طµ ط§ظ„ط²ط± (EN)') }}</Label>
                            <Input id="hero_button_text_en" v-model="form.hero.button_text.en" placeholder="Browse Fleet" />
                            <p v-if="form.errors['hero.button_text.en']" class="text-sm text-red-600">{{ form.errors['hero.button_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="hero_button_text_ar">{{ localize('Button Text (AR)', 'ظ†طµ ط§ظ„ط²ط± (AR)') }}</Label>
                            <Input id="hero_button_text_ar" v-model="form.hero.button_text.ar" placeholder="طھطµظپط­ ط§ظ„ط³ظٹط§ط±ط§طھ" dir="rtl" />
                            <p v-if="form.errors['hero.button_text.ar']" class="text-sm text-red-600">{{ form.errors['hero.button_text.ar'] }}</p>
                        </div>

                        <div class="space-y-2 md:col-span-2">
                            <Label for="hero_button_link">{{ localize('Button Link', 'ط±ط§ط¨ط· ط§ظ„ط²ط±') }}</Label>
                            <Input id="hero_button_link" v-model="form.hero.button_link" placeholder="/fleet" />
                            <p class="text-xs text-muted-foreground">{{ localize('Example: `/fleet` or `https://...`', 'ظ…ط«ط§ظ„: `/fleet` ط£ظˆ `https://...`') }}</p>
                            <p v-if="form.errors['hero.button_link']" class="text-sm text-red-600">{{ form.errors['hero.button_link'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('About Page', 'طµظپط­ط© ظ…ظ† ظ†ط­ظ†') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Editable content for public About page.', 'ظ…ط­طھظˆظ‰ ظ‚ط§ط¨ظ„ ظ„ظ„طھط¹ط¯ظٹظ„ ظ„طµظپط­ط© ظ…ظ† ظ†ط­ظ† ط§ظ„ط¹ط§ظ…ط©.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="about_title_en">{{ localize('Page Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„طµظپط­ط© (EN)') }}</Label>
                            <Input id="about_title_en" v-model="form.about.title.en" />
                            <p v-if="form.errors['about.title.en']" class="text-sm text-red-600">{{ form.errors['about.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_title_ar">{{ localize('Page Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„طµظپط­ط© (AR)') }}</Label>
                            <Input id="about_title_ar" v-model="form.about.title.ar" dir="rtl" />
                            <p v-if="form.errors['about.title.ar']" class="text-sm text-red-600">{{ form.errors['about.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_subtitle_en">{{ localize('Subtitle (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† ط§ظ„ظپط±ط¹ظٹ (EN)') }}</Label>
                            <textarea id="about_subtitle_en" v-model="form.about.subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_subtitle_ar">{{ localize('Subtitle (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† ط§ظ„ظپط±ط¹ظٹ (AR)') }}</Label>
                            <textarea id="about_subtitle_ar" v-model="form.about.subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_title_en">{{ localize('Story Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ظ‚طµط© (EN)') }}</Label>
                            <Input id="about_story_title_en" v-model="form.about.story_title.en" />
                            <p v-if="form.errors['about.story_title.en']" class="text-sm text-red-600">{{ form.errors['about.story_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_title_ar">{{ localize('Story Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ظ‚طµط© (AR)') }}</Label>
                            <Input id="about_story_title_ar" v-model="form.about.story_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.story_title.ar']" class="text-sm text-red-600">{{ form.errors['about.story_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_p1_en">{{ localize('Story Paragraph 1 (EN)', 'ط§ظ„ظپظ‚ط±ط© ط§ظ„ط£ظˆظ„ظ‰ ظ…ظ† ط§ظ„ظ‚طµط© (EN)') }}</Label>
                            <textarea id="about_story_p1_en" v-model="form.about.story_p1.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p1.en']" class="text-sm text-red-600">{{ form.errors['about.story_p1.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_p1_ar">{{ localize('Story Paragraph 1 (AR)', 'ط§ظ„ظپظ‚ط±ط© ط§ظ„ط£ظˆظ„ظ‰ ظ…ظ† ط§ظ„ظ‚طµط© (AR)') }}</Label>
                            <textarea id="about_story_p1_ar" v-model="form.about.story_p1.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p1.ar']" class="text-sm text-red-600">{{ form.errors['about.story_p1.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_story_p2_en">{{ localize('Story Paragraph 2 (EN)', 'ط§ظ„ظپظ‚ط±ط© ط§ظ„ط«ط§ظ†ظٹط© ظ…ظ† ط§ظ„ظ‚طµط© (EN)') }}</Label>
                            <textarea id="about_story_p2_en" v-model="form.about.story_p2.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p2.en']" class="text-sm text-red-600">{{ form.errors['about.story_p2.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_story_p2_ar">{{ localize('Story Paragraph 2 (AR)', 'ط§ظ„ظپظ‚ط±ط© ط§ظ„ط«ط§ظ†ظٹط© ظ…ظ† ط§ظ„ظ‚طµط© (AR)') }}</Label>
                            <textarea id="about_story_p2_ar" v-model="form.about.story_p2.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.story_p2.ar']" class="text-sm text-red-600">{{ form.errors['about.story_p2.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_mission_title_en">{{ localize('Mission Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط±ط³ط§ظ„ط© (EN)') }}</Label>
                            <Input id="about_mission_title_en" v-model="form.about.mission_title.en" />
                            <p v-if="form.errors['about.mission_title.en']" class="text-sm text-red-600">{{ form.errors['about.mission_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_mission_title_ar">{{ localize('Mission Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط±ط³ط§ظ„ط© (AR)') }}</Label>
                            <Input id="about_mission_title_ar" v-model="form.about.mission_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.mission_title.ar']" class="text-sm text-red-600">{{ form.errors['about.mission_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_mission_subtitle_en">{{ localize('Mission Subtitle (EN)', 'ظˆطµظپ ط§ظ„ط±ط³ط§ظ„ط© (EN)') }}</Label>
                            <textarea id="about_mission_subtitle_en" v-model="form.about.mission_subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.mission_subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.mission_subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_mission_subtitle_ar">{{ localize('Mission Subtitle (AR)', 'ظˆطµظپ ط§ظ„ط±ط³ط§ظ„ط© (AR)') }}</Label>
                            <textarea id="about_mission_subtitle_ar" v-model="form.about.mission_subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.mission_subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.mission_subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_title_en">{{ localize('CTA Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط¯ط¹ظˆط© ظ„ظ„ط¥ط¬ط±ط§ط، (EN)') }}</Label>
                            <Input id="about_cta_title_en" v-model="form.about.cta_title.en" />
                            <p v-if="form.errors['about.cta_title.en']" class="text-sm text-red-600">{{ form.errors['about.cta_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_title_ar">{{ localize('CTA Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط¯ط¹ظˆط© ظ„ظ„ط¥ط¬ط±ط§ط، (AR)') }}</Label>
                            <Input id="about_cta_title_ar" v-model="form.about.cta_title.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_title.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_subtitle_en">{{ localize('CTA Subtitle (EN)', 'ظˆطµظپ ط§ظ„ط¯ط¹ظˆط© ظ„ظ„ط¥ط¬ط±ط§ط، (EN)') }}</Label>
                            <textarea id="about_cta_subtitle_en" v-model="form.about.cta_subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.cta_subtitle.en']" class="text-sm text-red-600">{{ form.errors['about.cta_subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_subtitle_ar">{{ localize('CTA Subtitle (AR)', 'ظˆطµظپ ط§ظ„ط¯ط¹ظˆط© ظ„ظ„ط¥ط¬ط±ط§ط، (AR)') }}</Label>
                            <textarea id="about_cta_subtitle_ar" v-model="form.about.cta_subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['about.cta_subtitle.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_browse_text_en">{{ localize('CTA Browse Button (EN)', 'ط²ط± ط§ظ„طھطµظپط­ ظپظٹ ط§ظ„ط¯ط¹ظˆط© (EN)') }}</Label>
                            <Input id="about_cta_browse_text_en" v-model="form.about.cta_browse_text.en" />
                            <p v-if="form.errors['about.cta_browse_text.en']" class="text-sm text-red-600">{{ form.errors['about.cta_browse_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_browse_text_ar">{{ localize('CTA Browse Button (AR)', 'ط²ط± ط§ظ„طھطµظپط­ ظپظٹ ط§ظ„ط¯ط¹ظˆط© (AR)') }}</Label>
                            <Input id="about_cta_browse_text_ar" v-model="form.about.cta_browse_text.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_browse_text.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_browse_text.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="about_cta_contact_text_en">{{ localize('CTA Contact Button (EN)', 'ط²ط± ط§ظ„طھظˆط§طµظ„ ظپظٹ ط§ظ„ط¯ط¹ظˆط© (EN)') }}</Label>
                            <Input id="about_cta_contact_text_en" v-model="form.about.cta_contact_text.en" />
                            <p v-if="form.errors['about.cta_contact_text.en']" class="text-sm text-red-600">{{ form.errors['about.cta_contact_text.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="about_cta_contact_text_ar">{{ localize('CTA Contact Button (AR)', 'ط²ط± ط§ظ„طھظˆط§طµظ„ ظپظٹ ط§ظ„ط¯ط¹ظˆط© (AR)') }}</Label>
                            <Input id="about_cta_contact_text_ar" v-model="form.about.cta_contact_text.ar" dir="rtl" />
                            <p v-if="form.errors['about.cta_contact_text.ar']" class="text-sm text-red-600">{{ form.errors['about.cta_contact_text.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Contract PDF Header', 'طھط±ظˆظٹط³ط© ط§ظ„ط¹ظ‚ط¯') }}</h2>
                        <p class="text-sm text-muted-foreground">
                            {{ localize('Editable company header content printed at the top of the contract PDF.', 'ظ…ط­طھظˆظ‰ طھط±ظˆظٹط³ط© ط§ظ„ط´ط±ظƒط© ط§ظ„ط°ظٹ ظٹط¸ظ‡ط± ظپظٹ ط£ط¹ظ„ظ‰ ظ…ظ„ظپ ط§ظ„ط¹ظ‚ط¯.') }}
                        </p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2 md:col-span-2">
                            <Label for="pdf_template_contract">{{ localize('Contract PDF Template', 'ظ‚ط§ظ„ط¨ PDF ظ„ظ„ط¹ظ‚ط¯') }}</Label>
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
                            <Label for="pdf_header_company_name_en">{{ localize('Company Name (EN)', 'ط§ط³ظ… ط§ظ„ط´ط±ظƒط© (EN)') }}</Label>
                            <Input id="pdf_header_company_name_en" v-model="form.pdf_header.company_name.en" />
                            <p v-if="form.errors['pdf_header.company_name.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.company_name.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_company_name_ar">{{ localize('Company Name (AR)', 'ط§ط³ظ… ط§ظ„ط´ط±ظƒط© (AR)') }}</Label>
                            <Input id="pdf_header_company_name_ar" v-model="form.pdf_header.company_name.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.company_name.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.company_name.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_cr_number">{{ localize('C.R Number', 'ط±ظ‚ظ… ط§ظ„ط³ط¬ظ„ ط§ظ„طھط¬ط§ط±ظٹ') }}</Label>
                            <Input id="pdf_header_cr_number" v-model="form.pdf_header.cr_number" />
                            <p v-if="form.errors['pdf_header.cr_number']" class="text-sm text-red-600">{{ form.errors['pdf_header.cr_number'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_po_box">{{ localize('P.O. Box', 'طµظ†ط¯ظˆظ‚ ط§ظ„ط¨ط±ظٹط¯') }}</Label>
                            <Input id="pdf_header_po_box" v-model="form.pdf_header.po_box" />
                            <p v-if="form.errors['pdf_header.po_box']" class="text-sm text-red-600">{{ form.errors['pdf_header.po_box'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_pc">{{ localize('P.C', 'ط§ظ„ط±ظ…ط² ط§ظ„ط¨ط±ظٹط¯ظٹ') }}</Label>
                            <Input id="pdf_header_pc" v-model="form.pdf_header.pc" />
                            <p v-if="form.errors['pdf_header.pc']" class="text-sm text-red-600">{{ form.errors['pdf_header.pc'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_country_en">{{ localize('Country (EN)', 'ط§ظ„ط¯ظˆظ„ط© (EN)') }}</Label>
                            <Input id="pdf_header_country_en" v-model="form.pdf_header.country.en" />
                            <p v-if="form.errors['pdf_header.country.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.country.en'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_country_ar">{{ localize('Country (AR)', 'ط§ظ„ط¯ظˆظ„ط© (AR)') }}</Label>
                            <Input id="pdf_header_country_ar" v-model="form.pdf_header.country.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.country.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.country.ar'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_1">{{ localize('GSM 1', 'ظ†ظ‚ط§ظ„ 1') }}</Label>
                            <Input id="pdf_header_gsm_1" v-model="form.pdf_header.gsm_1" />
                            <p v-if="form.errors['pdf_header.gsm_1']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_1'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_2">{{ localize('GSM 2', 'ظ†ظ‚ط§ظ„ 2') }}</Label>
                            <Input id="pdf_header_gsm_2" v-model="form.pdf_header.gsm_2" />
                            <p v-if="form.errors['pdf_header.gsm_2']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_2'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_gsm_3">{{ localize('GSM 3', 'ظ†ظ‚ط§ظ„ 3') }}</Label>
                            <Input id="pdf_header_gsm_3" v-model="form.pdf_header.gsm_3" />
                            <p v-if="form.errors['pdf_header.gsm_3']" class="text-sm text-red-600">{{ form.errors['pdf_header.gsm_3'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pdf_header_registry_label_en">{{ localize('Registry Label (EN)', 'ظˆط³ظ… ط§ظ„ط³ط¬ظ„ (EN)') }}</Label>
                            <Input id="pdf_header_registry_label_en" v-model="form.pdf_header.registry_label.en" />
                            <p v-if="form.errors['pdf_header.registry_label.en']" class="text-sm text-red-600">{{ form.errors['pdf_header.registry_label.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="pdf_header_registry_label_ar">{{ localize('Registry Label (AR)', 'ظˆط³ظ… ط§ظ„ط³ط¬ظ„ (AR)') }}</Label>
                            <Input id="pdf_header_registry_label_ar" v-model="form.pdf_header.registry_label.ar" dir="rtl" />
                            <p v-if="form.errors['pdf_header.registry_label.ar']" class="text-sm text-red-600">{{ form.errors['pdf_header.registry_label.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Contact Page', 'طµظپط­ط© ط§طھطµظ„ ط¨ظ†ط§') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Editable titles and business hours for public Contact page.', 'ط§ظ„ط¹ظ†ط§ظˆظٹظ† ظˆط³ط§ط¹ط§طھ ط§ظ„ط¹ظ…ظ„ ط§ظ„ظ‚ط§ط¨ظ„ط© ظ„ظ„طھط¹ط¯ظٹظ„ ظپظٹ طµظپط­ط© ط§طھطµظ„ ط¨ظ†ط§ ط§ظ„ط¹ط§ظ…ط©.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact_page_title_en">{{ localize('Page Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„طµظپط­ط© (EN)') }}</Label>
                            <Input id="contact_page_title_en" v-model="form.contact_page.title.en" />
                            <p v-if="form.errors['contact_page.title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_title_ar">{{ localize('Page Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„طµظپط­ط© (AR)') }}</Label>
                            <Input id="contact_page_title_ar" v-model="form.contact_page.title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_subtitle_en">{{ localize('Subtitle (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† ط§ظ„ظپط±ط¹ظٹ (EN)') }}</Label>
                            <textarea id="contact_page_subtitle_en" v-model="form.contact_page.subtitle.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['contact_page.subtitle.en']" class="text-sm text-red-600">{{ form.errors['contact_page.subtitle.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_subtitle_ar">{{ localize('Subtitle (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† ط§ظ„ظپط±ط¹ظٹ (AR)') }}</Label>
                            <textarea id="contact_page_subtitle_ar" v-model="form.contact_page.subtitle.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['contact_page.subtitle.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.subtitle.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_form_title_en">{{ localize('Form Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ظ†ظ…ظˆط°ط¬ (EN)') }}</Label>
                            <Input id="contact_page_form_title_en" v-model="form.contact_page.form_title.en" />
                            <p v-if="form.errors['contact_page.form_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.form_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_form_title_ar">{{ localize('Form Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ظ†ظ…ظˆط°ط¬ (AR)') }}</Label>
                            <Input id="contact_page_form_title_ar" v-model="form.contact_page.form_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.form_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.form_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_info_title_en">{{ localize('Sidebar Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط´ط±ظٹط· ط§ظ„ط¬ط§ظ†ط¨ظٹ (EN)') }}</Label>
                            <Input id="contact_page_info_title_en" v-model="form.contact_page.info_title.en" />
                            <p v-if="form.errors['contact_page.info_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.info_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_info_title_ar">{{ localize('Sidebar Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط´ط±ظٹط· ط§ظ„ط¬ط§ظ†ط¨ظٹ (AR)') }}</Label>
                            <Input id="contact_page_info_title_ar" v-model="form.contact_page.info_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.info_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.info_title.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_hours_en">{{ localize('Business Hours (EN)', 'ط³ط§ط¹ط§طھ ط§ظ„ط¹ظ…ظ„ (EN)') }}</Label>
                            <textarea id="contact_page_hours_en" v-model="form.contact_page.hours.en" rows="4" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">{{ localize('Use new line for each row.', 'ط§ط³طھط®ط¯ظ… ط³ط·ط±ظ‹ط§ ط¬ط¯ظٹط¯ظ‹ط§ ظ„ظƒظ„ طµظپ.') }}</p>
                            <p v-if="form.errors['contact_page.hours.en']" class="text-sm text-red-600">{{ form.errors['contact_page.hours.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_hours_ar">{{ localize('Business Hours (AR)', 'ط³ط§ط¹ط§طھ ط§ظ„ط¹ظ…ظ„ (AR)') }}</Label>
                            <textarea id="contact_page_hours_ar" v-model="form.contact_page.hours.ar" rows="4" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p class="text-xs text-muted-foreground">{{ localize('Use new line for each row.', 'ط§ط³طھط®ط¯ظ… ط³ط·ط±ظ‹ط§ ط¬ط¯ظٹط¯ظ‹ط§ ظ„ظƒظ„ طµظپ.') }}</p>
                            <p v-if="form.errors['contact_page.hours.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.hours.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_page_quick_links_title_en">{{ localize('Quick Links Title (EN)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط±ظˆط§ط¨ط· ط§ظ„ط³ط±ظٹط¹ط© (EN)') }}</Label>
                            <Input id="contact_page_quick_links_title_en" v-model="form.contact_page.quick_links_title.en" />
                            <p v-if="form.errors['contact_page.quick_links_title.en']" class="text-sm text-red-600">{{ form.errors['contact_page.quick_links_title.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_page_quick_links_title_ar">{{ localize('Quick Links Title (AR)', 'ط¹ظ†ظˆط§ظ† ط§ظ„ط±ظˆط§ط¨ط· ط§ظ„ط³ط±ظٹط¹ط© (AR)') }}</Label>
                            <Input id="contact_page_quick_links_title_ar" v-model="form.contact_page.quick_links_title.ar" dir="rtl" />
                            <p v-if="form.errors['contact_page.quick_links_title.ar']" class="text-sm text-red-600">{{ form.errors['contact_page.quick_links_title.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <section class="rounded-lg border p-5 space-y-4">
                    <div>
                        <h2 class="text-lg font-semibold">{{ localize('Contact & Footer (MVP)', 'ط§ظ„طھظˆط§طµظ„ ظˆط§ظ„طھط°ظٹظٹظ„') }}</h2>
                        <p class="text-sm text-muted-foreground">{{ localize('Basic public contact info and footer description.', 'ظ…ط¹ظ„ظˆظ…ط§طھ ط§ظ„طھظˆط§طµظ„ ط§ظ„ط¹ط§ظ…ط© ظˆظˆطµظپ ط§ظ„طھط°ظٹظٹظ„.') }}</p>
                    </div>

                    <div class="grid gap-4 md:grid-cols-2">
                        <div class="space-y-2">
                            <Label for="contact_phone">{{ localize('Phone', 'ط§ظ„ظ‡ط§طھظپ') }}</Label>
                            <Input id="contact_phone" v-model="form.contact.phone" placeholder="+965 ..." />
                            <p v-if="form.errors['contact.phone']" class="text-sm text-red-600">{{ form.errors['contact.phone'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_email">{{ localize('Email', 'ط§ظ„ط¨ط±ظٹط¯ ط§ظ„ط¥ظ„ظƒطھط±ظˆظ†ظٹ') }}</Label>
                            <Input id="contact_email" v-model="form.contact.email" type="email" placeholder="hello@example.com" />
                            <p v-if="form.errors['contact.email']" class="text-sm text-red-600">{{ form.errors['contact.email'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="contact_address_en">{{ localize('Address (EN)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (EN)') }}</Label>
                            <Input id="contact_address_en" v-model="form.contact.address.en" />
                            <p v-if="form.errors['contact.address.en']" class="text-sm text-red-600">{{ form.errors['contact.address.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="contact_address_ar">{{ localize('Address (AR)', 'ط§ظ„ط¹ظ†ظˆط§ظ† (AR)') }}</Label>
                            <Input id="contact_address_ar" v-model="form.contact.address.ar" dir="rtl" />
                            <p v-if="form.errors['contact.address.ar']" class="text-sm text-red-600">{{ form.errors['contact.address.ar'] }}</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="footer_desc_en">{{ localize('Footer Description (EN)', 'ظˆطµظپ ط§ظ„طھط°ظٹظٹظ„ (EN)') }}</Label>
                            <textarea id="footer_desc_en" v-model="form.footer.description.en" rows="3" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['footer.description.en']" class="text-sm text-red-600">{{ form.errors['footer.description.en'] }}</p>
                        </div>
                        <div class="space-y-2">
                            <Label for="footer_desc_ar">{{ localize('Footer Description (AR)', 'ظˆطµظپ ط§ظ„طھط°ظٹظٹظ„ (AR)') }}</Label>
                            <textarea id="footer_desc_ar" v-model="form.footer.description.ar" rows="3" dir="rtl" class="w-full rounded-md border border-input bg-background px-3 py-2 text-sm" />
                            <p v-if="form.errors['footer.description.ar']" class="text-sm text-red-600">{{ form.errors['footer.description.ar'] }}</p>
                        </div>
                    </div>
                </section>

                <div class="flex justify-end">
                    <Button type="submit" :disabled="form.processing">
                        {{ form.processing ? localize('Saving...', 'ط¬ط§ط±ظچ ط§ظ„ط­ظپط¸...') : localize('Save Changes', 'ط­ظپط¸ ط§ظ„طھط؛ظٹظٹط±ط§طھ') }}
                    </Button>
                </div>
            </form>
        </main>
    </AdminLayout>
</template>

