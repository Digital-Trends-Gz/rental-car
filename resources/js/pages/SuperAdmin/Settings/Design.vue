<script setup lang="ts">
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Textarea } from '@/components/ui/textarea';
import FileUpload from '@/components/ViltFilePond/FileUpload.vue';
import { useTrans } from '@/composables/useTrans';
import SuperAdminLayout from '@/layouts/SuperAdminLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import { ExternalLink, RefreshCw } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';

interface FeatureCard {
    title: string;
    image_url: string;
    icon_background_color: string;
    content: string;
}

interface StepItem {
    title: string;
    image_url: string;
    icon_background_color: string;
    description: string;
}

interface FaqItem {
    question: string;
    answer: string;
}

interface QuickLinkItem {
    label: string;
    href: string;
}

interface SocialLinkItem {
    label: string;
    platform: string;
    href: string;
}

interface MobileAppCard {
    title: string;
    subtitle: string;
    description: string;
    image_url: string;
    icon_url: string;
    app_store_url: string;
    google_play_url: string;
    features: string[];
}

interface LandingSettings {
    hero: {
        enabled: boolean;
        title: string;
        description: string;
        features: string[];
        image_url: string;
        localized_images: Record<string, string>;
    };
    cars_section: {
        enabled: boolean;
        fleet_button_icon_url: string;
    };
    features_section: {
        enabled: boolean;
        title: string;
        description: string;
        cards: FeatureCard[];
    };
    getting_started: {
        enabled: boolean;
        title: string;
        description: string;
        items: StepItem[];
    };
    mobile_apps_section: {
        enabled: boolean;
        eyebrow: string;
        title: string;
        description: string;
        ios_label: string;
        android_label: string;
        apps: MobileAppCard[];
    };
    clients_section: {
        enabled: boolean;
    };
    plans_section: {
        enabled: boolean;
        title: string;
        description: string;
    };
    faq_section: {
        enabled: boolean;
        title: string;
        description: string;
        items: FaqItem[];
    };
    contact_section: {
        enabled: boolean;
        title: string;
        description: string;
        form_title: string;
        name_label: string;
        name_placeholder: string;
        email_label: string;
        email_placeholder: string;
        subject_label: string;
        subject_placeholder: string;
        message_label: string;
        message_placeholder: string;
        submit_label: string;
        sending_label: string;
        success_message: string;
        error_message: string;
        direct_title: string;
        direct_email_label: string;
        direct_email: string;
        direct_phone_label: string;
        direct_phone: string;
        response_time_label: string;
        response_time: string;
        quick_links_title: string;
        quick_links: QuickLinkItem[];
    };
    footer: {
        enabled: boolean;
        title: string;
        description: string;
        copyright_text: string;
        show_social_links: boolean;
        show_app_buttons: boolean;
        android_label: string;
        android_url: string;
        ios_label: string;
        ios_url: string;
        social_links: SocialLinkItem[];
    };
    enabled_locales: string[];
}

const props = defineProps<{
    settings: LandingSettings;
    previewUrl: string;
    heroFiles: Array<{ id: number; url: string }>;
    heroLocalizedFiles: Record<string, Array<{ id: number; url: string }>>;
    featureFiles: Record<number, Array<{ id: number; url: string }>>;
    gettingStartedFiles: Record<number, Array<{ id: number; url: string }>>;
    carsFleetButtonIconFiles: Array<{ id: number; url: string }>;
    mobileAppFiles: Record<
        number,
        {
            image: Array<{ id: number; url: string }>;
            icon: Array<{ id: number; url: string }>;
        }
    >;
}>();

const { locale } = useTrans();
const localize = (en: string, ar: string) => (locale.value === 'ar' ? ar : en);

const previewNonce = ref(Date.now());

const form = useForm<{
    settings: LandingSettings;
    hero_temp_folders: string[];
    hero_removed_files: number[];
    hero_locale_temp_folders: Record<string, string[]>;
    hero_locale_removed_files: Record<string, number[]>;
    feature_card_temp_folders: Record<number, string[]>;
    feature_card_removed_files: Record<number, number[]>;
    getting_started_temp_folders: Record<number, string[]>;
    getting_started_removed_files: Record<number, number[]>;
    cars_fleet_button_icon_temp_folders: string[];
    cars_fleet_button_icon_removed_files: number[];
    mobile_app_temp_folders: Record<
        number,
        { image: string[]; icon: string[] }
    >;
    mobile_app_removed_files: Record<
        number,
        { image: number[]; icon: number[] }
    >;
    hero_direct_file: File | null;
    hero_locale_direct_files: Record<string, File | null>;
    feature_card_direct_files: Record<number, File | null>;
    getting_started_direct_files: Record<number, File | null>;
    cars_fleet_button_icon_direct_file: File | null;
    mobile_app_direct_files: Record<
        number,
        { image: File | null; icon: File | null }
    >;
}>({
    settings: JSON.parse(JSON.stringify(props.settings)),
    hero_temp_folders: [] as string[],
    hero_removed_files: [] as number[],
    hero_locale_temp_folders: {},
    hero_locale_removed_files: {},
    feature_card_temp_folders: {},
    feature_card_removed_files: {},
    getting_started_temp_folders: {},
    getting_started_removed_files: {},
    cars_fleet_button_icon_temp_folders: [],
    cars_fleet_button_icon_removed_files: [],
    mobile_app_temp_folders: {},
    mobile_app_removed_files: {},
    hero_direct_file: null,
    hero_locale_direct_files: {},
    feature_card_direct_files: {},
    getting_started_direct_files: {},
    cars_fleet_button_icon_direct_file: null,
    mobile_app_direct_files: {},
});

const fileUploadRef = ref<InstanceType<typeof FileUpload> | null>(null);
const heroTempFolders = ref<string[]>([]);
const heroRemovedFileIds = ref<number[]>([]);
const heroLocaleTempFolders = ref<Record<string, string[]>>({});
const heroLocaleRemovedFileIds = ref<Record<string, number[]>>({});
const featureCardTempFolders = ref<Record<number, string[]>>({});
const featureCardRemovedFileIds = ref<Record<number, number[]>>({});
const gettingStartedTempFolders = ref<Record<number, string[]>>({});
const gettingStartedRemovedFileIds = ref<Record<number, number[]>>({});
const carsFleetButtonIconTempFolders = ref<string[]>([]);
const carsFleetButtonIconRemovedFileIds = ref<number[]>([]);
const mobileAppTempFolders = ref<
    Record<number, { image: string[]; icon: string[] }>
>({});
const mobileAppRemovedFileIds = ref<
    Record<number, { image: number[]; icon: number[] }>
>({});
const heroDirectFile = ref<File | null>(null);
const heroLocaleDirectFiles = ref<Record<string, File | null>>({});
const featureCardDirectFiles = ref<Record<number, File | null>>({});
const gettingStartedDirectFiles = ref<Record<number, File | null>>({});
const carsFleetButtonIconDirectFile = ref<File | null>(null);
const mobileAppDirectFiles = ref<
    Record<number, { image: File | null; icon: File | null }>
>({});
const enabledLocales = computed(() =>
    Array.isArray(form.settings.enabled_locales) &&
    form.settings.enabled_locales.length
        ? form.settings.enabled_locales
        : ['en'],
);
const activeHeroLocale = computed(() => {
    const currentLocale = String(locale.value || '');

    return enabledLocales.value.includes(currentLocale)
        ? currentLocale
        : enabledLocales.value[0] || 'en';
});
const defaultHeroLocale = computed(() =>
    enabledLocales.value.includes('en') ? 'en' : enabledLocales.value[0] || 'en',
);
const activeHeroUsesDefaultMedia = computed(
    () => activeHeroLocale.value === defaultHeroLocale.value,
);
const heroSourceMode = ref<'upload' | 'url'>(
    activeHeroUsesDefaultMedia.value
        ? props.heroFiles?.length
            ? 'upload'
            : 'url'
        : props.heroLocalizedFiles?.[activeHeroLocale.value]?.length
        ? 'upload'
        : 'url',
);
const previewSrc = computed(() => {
    const separator = props.previewUrl.includes('?') ? '&' : '?';
    return `${props.previewUrl}${separator}preview=${previewNonce.value}`;
});
const uploadedHeroUrl = computed(() =>
    activeHeroUsesDefaultMedia.value
        ? props.heroFiles?.[0]?.url || null
        : props.heroLocalizedFiles?.[activeHeroLocale.value]?.[0]?.url || null,
);
const previewHeroUrl = computed(() =>
    heroSourceMode.value === 'url'
        ? activeHeroUsesDefaultMedia.value
            ? form.settings.hero.image_url || null
            : form.settings.hero.localized_images?.[activeHeroLocale.value] ||
              null
        : uploadedHeroUrl.value ||
          (activeHeroUsesDefaultMedia.value
              ? form.settings.hero.image_url
              : form.settings.hero.localized_images?.[activeHeroLocale.value]) ||
          null,
);
const heroIsVideo = computed(() => isVideoUrl(previewHeroUrl.value));

if (!form.settings.hero.localized_images) {
    form.settings.hero.localized_images = {};
}

if (!('fleet_button_icon_url' in form.settings.cars_section)) {
    form.settings.cars_section.fleet_button_icon_url = '';
}

for (const localeCode of enabledLocales.value) {
    form.settings.hero.localized_images[localeCode] =
        form.settings.hero.localized_images[localeCode] || '';
    heroLocaleTempFolders.value[localeCode] = [];
    heroLocaleRemovedFileIds.value[localeCode] = [];
    heroLocaleDirectFiles.value[localeCode] = null;
}

form.settings.mobile_apps_section.apps.forEach((app, index) => {
    if (!('icon_url' in app)) {
        app.icon_url = '';
    }

    mobileAppTempFolders.value[index] = { image: [], icon: [] };
    mobileAppRemovedFileIds.value[index] = { image: [], icon: [] };
    mobileAppDirectFiles.value[index] = { image: null, icon: null };
});

form.settings.features_section.cards.forEach((card, index) => {
    if (!('icon_background_color' in card)) {
        card.icon_background_color = '#f3f4f6';
    }

    featureCardTempFolders.value[index] = [];
    featureCardRemovedFileIds.value[index] = [];
    featureCardDirectFiles.value[index] = null;
});

form.settings.getting_started.items.forEach((item, index) => {
    if (!('image_url' in item)) {
        item.image_url = '';
    }
    if (!('icon_background_color' in item)) {
        item.icon_background_color = '#f3f4f6';
    }

    gettingStartedTempFolders.value[index] = [];
    gettingStartedRemovedFileIds.value[index] = [];
    gettingStartedDirectFiles.value[index] = null;
});

watch(
    heroTempFolders,
    (value) => {
        form.hero_temp_folders = [...value];
    },
    { deep: true },
);

watch(
    heroLocaleTempFolders,
    (value) => {
        form.hero_locale_temp_folders = JSON.parse(JSON.stringify(value));
    },
    { deep: true },
);

watch(
    mobileAppTempFolders,
    (value) => {
        form.mobile_app_temp_folders = JSON.parse(JSON.stringify(value));
    },
    { deep: true },
);

watch(
    featureCardTempFolders,
    (value) => {
        form.feature_card_temp_folders = JSON.parse(JSON.stringify(value));
    },
    { deep: true },
);

watch(
    gettingStartedTempFolders,
    (value) => {
        form.getting_started_temp_folders = JSON.parse(JSON.stringify(value));
    },
    { deep: true },
);

watch(
    carsFleetButtonIconTempFolders,
    (value) => {
        form.cars_fleet_button_icon_temp_folders = [...value];
    },
    { deep: true },
);

watch(heroSourceMode, (value) => {
    if (value !== 'url') {
        return;
    }

    if (activeHeroUsesDefaultMedia.value) {
        const existingIds = (props.heroFiles || [])
            .map((file) => file.id)
            .filter(Boolean);
        heroRemovedFileIds.value = [
            ...new Set([...heroRemovedFileIds.value, ...existingIds]),
        ];
        form.hero_removed_files = [...heroRemovedFileIds.value];
        return;
    }

    const localeCode = activeHeroLocale.value;
    const existingIds = (props.heroLocalizedFiles?.[localeCode] || [])
        .map((file) => file.id)
        .filter(Boolean);
    heroLocaleRemovedFileIds.value[localeCode] = [
        ...new Set([
            ...(heroLocaleRemovedFileIds.value[localeCode] || []),
            ...existingIds,
        ]),
    ];
    form.hero_locale_removed_files = JSON.parse(
        JSON.stringify(heroLocaleRemovedFileIds.value),
    );
});

watch(
    activeHeroLocale,
    () => {
        heroSourceMode.value = activeHeroUsesDefaultMedia.value
            ? props.heroFiles?.length
                ? 'upload'
                : 'url'
            : props.heroLocalizedFiles?.[activeHeroLocale.value]?.length
            ? 'upload'
            : 'url';
    },
);

function isVideoUrl(url: string | null): boolean {
    if (!url) {
        return false;
    }

    return /\.(mp4|webm|ogg|mov)(?:$|[?#])/i.test(url);
}

const handleHeroFileRemoved = (data: { type: string; fileId?: number }) => {
    if (data.type === 'existing' && data.fileId) {
        heroRemovedFileIds.value.push(data.fileId);
        form.hero_removed_files = [...new Set(heroRemovedFileIds.value)];
    }
};

const handleHeroLocalFileAdded = (file: File) => {
    if (activeHeroUsesDefaultMedia.value) {
        heroDirectFile.value = file;
        form.hero_direct_file = file;
        return;
    }

    heroLocaleDirectFiles.value[activeHeroLocale.value] = file;
    form.hero_locale_direct_files = { ...heroLocaleDirectFiles.value };
};

const handleHeroLocaleFileRemoved = (
    localeCode: string,
    data: { type: string; fileId?: number },
) => {
    if (data.type === 'existing' && data.fileId) {
        heroLocaleRemovedFileIds.value[localeCode] = [
            ...new Set([
                ...(heroLocaleRemovedFileIds.value[localeCode] || []),
                data.fileId,
            ]),
        ];
        form.hero_locale_removed_files = JSON.parse(
            JSON.stringify(heroLocaleRemovedFileIds.value),
        );
    }
};

const mobileAppFileList = (index: number, type: 'image' | 'icon') =>
    props.mobileAppFiles?.[index]?.[type] || [];

const featureCardFileList = (index: number) =>
    props.featureFiles?.[index] || [];

const gettingStartedFileList = (index: number) =>
    props.gettingStartedFiles?.[index] || [];

const handleCarsFleetButtonIconFileRemoved = (data: {
    type: string;
    fileId?: number;
}) => {
    if (data.type !== 'existing' || !data.fileId) {
        return;
    }

    carsFleetButtonIconRemovedFileIds.value = [
        ...new Set([...carsFleetButtonIconRemovedFileIds.value, data.fileId]),
    ];
    form.cars_fleet_button_icon_removed_files = [
        ...carsFleetButtonIconRemovedFileIds.value,
    ];
};

const handleCarsFleetButtonIconLocalFileAdded = (file: File) => {
    carsFleetButtonIconDirectFile.value = file;
    form.cars_fleet_button_icon_direct_file = file;
};

const handleFeatureCardFileRemoved = (
    index: number,
    data: { type: string; fileId?: number },
) => {
    if (data.type !== 'existing' || !data.fileId) {
        return;
    }

    featureCardRemovedFileIds.value[index] = [
        ...new Set([...(featureCardRemovedFileIds.value[index] || []), data.fileId]),
    ];
    form.feature_card_removed_files = JSON.parse(
        JSON.stringify(featureCardRemovedFileIds.value),
    );
};

const handleFeatureCardLocalFileAdded = (index: number, file: File) => {
    featureCardDirectFiles.value[index] = file;
    form.feature_card_direct_files = { ...featureCardDirectFiles.value };
};

const handleGettingStartedFileRemoved = (
    index: number,
    data: { type: string; fileId?: number },
) => {
    if (data.type !== 'existing' || !data.fileId) {
        return;
    }

    gettingStartedRemovedFileIds.value[index] = [
        ...new Set([...(gettingStartedRemovedFileIds.value[index] || []), data.fileId]),
    ];
    form.getting_started_removed_files = JSON.parse(
        JSON.stringify(gettingStartedRemovedFileIds.value),
    );
};

const handleGettingStartedLocalFileAdded = (index: number, file: File) => {
    gettingStartedDirectFiles.value[index] = file;
    form.getting_started_direct_files = { ...gettingStartedDirectFiles.value };
};

const handleMobileAppFileRemoved = (
    index: number,
    type: 'image' | 'icon',
    data: { type: string; fileId?: number },
) => {
    if (data.type !== 'existing' || !data.fileId) {
        return;
    }

    if (!mobileAppRemovedFileIds.value[index]) {
        mobileAppRemovedFileIds.value[index] = { image: [], icon: [] };
    }

    mobileAppRemovedFileIds.value[index][type] = [
        ...new Set([
            ...(mobileAppRemovedFileIds.value[index][type] || []),
            data.fileId,
        ]),
    ];
    form.mobile_app_removed_files = JSON.parse(
        JSON.stringify(mobileAppRemovedFileIds.value),
    );
};

const handleMobileAppLocalFileAdded = (
    index: number,
    type: 'image' | 'icon',
    file: File,
) => {
    if (!mobileAppDirectFiles.value[index]) {
        mobileAppDirectFiles.value[index] = { image: null, icon: null };
    }

    mobileAppDirectFiles.value[index][type] = file;
    form.mobile_app_direct_files = { ...mobileAppDirectFiles.value };
};

const localeDisplayName = (localeCode: string) =>
    (
        {
            en: 'English',
            ar: 'Arabic',
            ur: 'Urdu',
        } as Record<string, string>
    )[String(localeCode || '').toLowerCase()] ||
    String(localeCode || '').toUpperCase();

const heroLocaleFiles = (localeCode: string) =>
    props.heroLocalizedFiles?.[localeCode] || [];

const refreshPreview = () => {
    previewNonce.value = Date.now();
};

const syncUploadStateToForm = () => {
    form.hero_temp_folders = [...heroTempFolders.value];
    form.hero_removed_files = [...new Set(heroRemovedFileIds.value)];
    form.hero_direct_file = heroDirectFile.value;
    form.hero_locale_direct_files = { ...heroLocaleDirectFiles.value };
    form.hero_locale_temp_folders = JSON.parse(
        JSON.stringify(heroLocaleTempFolders.value),
    );
    form.hero_locale_removed_files = JSON.parse(
        JSON.stringify(heroLocaleRemovedFileIds.value),
    );
    form.feature_card_temp_folders = JSON.parse(
        JSON.stringify(featureCardTempFolders.value),
    );
    form.feature_card_removed_files = JSON.parse(
        JSON.stringify(featureCardRemovedFileIds.value),
    );
    form.feature_card_direct_files = { ...featureCardDirectFiles.value };
    form.getting_started_temp_folders = JSON.parse(
        JSON.stringify(gettingStartedTempFolders.value),
    );
    form.getting_started_removed_files = JSON.parse(
        JSON.stringify(gettingStartedRemovedFileIds.value),
    );
    form.getting_started_direct_files = { ...gettingStartedDirectFiles.value };
    form.cars_fleet_button_icon_temp_folders = [
        ...carsFleetButtonIconTempFolders.value,
    ];
    form.cars_fleet_button_icon_removed_files = [
        ...new Set(carsFleetButtonIconRemovedFileIds.value),
    ];
    form.cars_fleet_button_icon_direct_file = carsFleetButtonIconDirectFile.value;
    form.mobile_app_temp_folders = JSON.parse(
        JSON.stringify(mobileAppTempFolders.value),
    );
    form.mobile_app_removed_files = JSON.parse(
        JSON.stringify(mobileAppRemovedFileIds.value),
    );
    form.mobile_app_direct_files = { ...mobileAppDirectFiles.value };
};

const submit = () => {
    syncUploadStateToForm();

    form.transform((data) => {
        // Strip keys from settings that the design endpoint doesn't use.
        // When Inertia serializes nested objects to multipart/form-data,
        // each leaf becomes a separate input variable. The translations
        // alone produce 2000+ variables, which exceeds PHP's max_input_vars
        // (default 1000) and causes PHP to silently drop ALL subsequent
        // fields — including every file upload and _method.
        const { translations, enabled_locales, navigation, ...designSettings } = data.settings as Record<string, any>;

        return {
            ...data,
            settings: designSettings,
            _method: 'put',
        };
    }).post('/superadmin/settings/design', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            heroTempFolders.value = [];
            form.hero_temp_folders = [];
            form.hero_removed_files = [];
            form.hero_locale_temp_folders = {};
            form.hero_locale_removed_files = {};
            form.feature_card_temp_folders = {};
            form.feature_card_removed_files = {};
            form.getting_started_temp_folders = {};
            form.getting_started_removed_files = {};
            form.getting_started_direct_files = {};
            form.cars_fleet_button_icon_temp_folders = [];
            form.cars_fleet_button_icon_removed_files = [];
            form.cars_fleet_button_icon_direct_file = null;
            form.mobile_app_temp_folders = {};
            form.mobile_app_removed_files = {};
            heroRemovedFileIds.value = [];
            heroDirectFile.value = null;
            carsFleetButtonIconTempFolders.value = [];
            carsFleetButtonIconRemovedFileIds.value = [];
            carsFleetButtonIconDirectFile.value = null;
            heroLocaleTempFolders.value = Object.fromEntries(
                enabledLocales.value.map((localeCode) => [localeCode, []]),
            );
            heroLocaleRemovedFileIds.value = Object.fromEntries(
                enabledLocales.value.map((localeCode) => [localeCode, []]),
            );
            heroLocaleDirectFiles.value = Object.fromEntries(
                enabledLocales.value.map((localeCode) => [localeCode, null]),
            );
            mobileAppTempFolders.value = Object.fromEntries(
                form.settings.mobile_apps_section.apps.map((_app, index) => [
                    index,
                    { image: [], icon: [] },
                ]),
            );
            featureCardTempFolders.value = Object.fromEntries(
                form.settings.features_section.cards.map((_card, index) => [
                    index,
                    [],
                ]),
            );
            featureCardRemovedFileIds.value = Object.fromEntries(
                form.settings.features_section.cards.map((_card, index) => [
                    index,
                    [],
                ]),
            );
            featureCardDirectFiles.value = Object.fromEntries(
                form.settings.features_section.cards.map((_card, index) => [
                    index,
                    null,
                ]),
            );
            gettingStartedTempFolders.value = Object.fromEntries(
                form.settings.getting_started.items.map((_item, index) => [
                    index,
                    [],
                ]),
            );
            gettingStartedRemovedFileIds.value = Object.fromEntries(
                form.settings.getting_started.items.map((_item, index) => [
                    index,
                    [],
                ]),
            );
            gettingStartedDirectFiles.value = Object.fromEntries(
                form.settings.getting_started.items.map((_item, index) => [
                    index,
                    null,
                ]),
            );
            mobileAppRemovedFileIds.value = Object.fromEntries(
                form.settings.mobile_apps_section.apps.map((_app, index) => [
                    index,
                    { image: [], icon: [] },
                ]),
            );
            mobileAppDirectFiles.value = Object.fromEntries(
                form.settings.mobile_apps_section.apps.map((_app, index) => [
                    index,
                    { image: null, icon: null },
                ]),
            );
            fileUploadRef.value?.resetFiles();
            refreshPreview();
        },
    });
};

const openPreview = () => {
    window.open(previewSrc.value, '_blank', 'noopener,noreferrer');
};

const addHeroFeature = () => form.settings.hero.features.push('');
const removeHeroFeature = (index: number) =>
    form.settings.hero.features.splice(index, 1);

const addFeatureCard = () => {
    form.settings.features_section.cards.push({
        title: '',
        image_url: '',
        icon_background_color: '#f3f4f6',
        content: '',
    });

    const index = form.settings.features_section.cards.length - 1;
    featureCardTempFolders.value[index] = [];
    featureCardRemovedFileIds.value[index] = [];
    featureCardDirectFiles.value[index] = null;
};
const removeFeatureCard = (index: number) => {
    form.settings.features_section.cards.splice(index, 1);
    featureCardTempFolders.value = Object.fromEntries(
        form.settings.features_section.cards.map((_card, cardIndex) => [
            cardIndex,
            featureCardTempFolders.value[cardIndex >= index ? cardIndex + 1 : cardIndex] || [],
        ]),
    );
    featureCardRemovedFileIds.value = Object.fromEntries(
        form.settings.features_section.cards.map((_card, cardIndex) => [
            cardIndex,
            featureCardRemovedFileIds.value[cardIndex >= index ? cardIndex + 1 : cardIndex] || [],
        ]),
    );
    featureCardDirectFiles.value = Object.fromEntries(
        form.settings.features_section.cards.map((_card, cardIndex) => [
            cardIndex,
            featureCardDirectFiles.value[cardIndex >= index ? cardIndex + 1 : cardIndex] || null,
        ]),
    );
    form.feature_card_temp_folders = JSON.parse(
        JSON.stringify(featureCardTempFolders.value),
    );
    form.feature_card_removed_files = JSON.parse(
        JSON.stringify(featureCardRemovedFileIds.value),
    );
    form.feature_card_direct_files = { ...featureCardDirectFiles.value };
};

const addStepItem = () => {
    form.settings.getting_started.items.push({
        title: '',
        image_url: '',
        icon_background_color: '#f3f4f6',
        description: '',
    });

    const index = form.settings.getting_started.items.length - 1;
    gettingStartedTempFolders.value[index] = [];
    gettingStartedRemovedFileIds.value[index] = [];
    gettingStartedDirectFiles.value[index] = null;
};
const removeStepItem = (index: number) => {
    form.settings.getting_started.items.splice(index, 1);
    gettingStartedTempFolders.value = Object.fromEntries(
        form.settings.getting_started.items.map((_item, itemIndex) => [
            itemIndex,
            gettingStartedTempFolders.value[
                itemIndex >= index ? itemIndex + 1 : itemIndex
            ] || [],
        ]),
    );
    gettingStartedRemovedFileIds.value = Object.fromEntries(
        form.settings.getting_started.items.map((_item, itemIndex) => [
            itemIndex,
            gettingStartedRemovedFileIds.value[
                itemIndex >= index ? itemIndex + 1 : itemIndex
            ] || [],
        ]),
    );
    gettingStartedDirectFiles.value = Object.fromEntries(
        form.settings.getting_started.items.map((_item, itemIndex) => [
            itemIndex,
            gettingStartedDirectFiles.value[
                itemIndex >= index ? itemIndex + 1 : itemIndex
            ] || null,
        ]),
    );
    form.getting_started_temp_folders = JSON.parse(
        JSON.stringify(gettingStartedTempFolders.value),
    );
    form.getting_started_removed_files = JSON.parse(
        JSON.stringify(gettingStartedRemovedFileIds.value),
    );
    form.getting_started_direct_files = { ...gettingStartedDirectFiles.value };
};

const resetMobileAppUploadState = () => {
    mobileAppTempFolders.value = Object.fromEntries(
        form.settings.mobile_apps_section.apps.map((_app, index) => [
            index,
            { image: [], icon: [] },
        ]),
    );
    mobileAppRemovedFileIds.value = Object.fromEntries(
        form.settings.mobile_apps_section.apps.map((_app, index) => [
            index,
            { image: [], icon: [] },
        ]),
    );
    mobileAppDirectFiles.value = Object.fromEntries(
        form.settings.mobile_apps_section.apps.map((_app, index) => [
            index,
            { image: null, icon: null },
        ]),
    );
    form.mobile_app_temp_folders = {};
    form.mobile_app_removed_files = {};
    form.mobile_app_direct_files = {};
};

const addMobileApp = () => {
    form.settings.mobile_apps_section.apps.push({
        title: '',
        subtitle: '',
        description: '',
        image_url: '',
        icon_url: '',
        app_store_url: '',
        google_play_url: '',
        features: [''],
    });

    const index = form.settings.mobile_apps_section.apps.length - 1;
    mobileAppTempFolders.value[index] = { image: [], icon: [] };
    mobileAppRemovedFileIds.value[index] = { image: [], icon: [] };
    mobileAppDirectFiles.value[index] = { image: null, icon: null };
};
const removeMobileApp = (index: number) => {
    form.settings.mobile_apps_section.apps.splice(index, 1);
    resetMobileAppUploadState();
};
const addMobileAppFeature = (index: number) =>
    form.settings.mobile_apps_section.apps[index].features.push('');
const removeMobileAppFeature = (appIndex: number, featureIndex: number) =>
    form.settings.mobile_apps_section.apps[appIndex].features.splice(
        featureIndex,
        1,
    );

const addFaqItem = () => {
    form.settings.faq_section.items.push({
        question: '',
        answer: '',
    });
};
const removeFaqItem = (index: number) =>
    form.settings.faq_section.items.splice(index, 1);

const addQuickLink = () => {
    form.settings.contact_section.quick_links.push({
        label: '',
        href: '#',
    });
};
const removeQuickLink = (index: number) =>
    form.settings.contact_section.quick_links.splice(index, 1);
const addFooterSocialLink = () => {
    form.settings.footer.social_links.push({
        label: '',
        platform: 'facebook',
        href: '',
    });
};
const removeFooterSocialLink = (index: number) =>
    form.settings.footer.social_links.splice(index, 1);
const toggleSection = (
    key:
        | 'hero'
        | 'cars_section'
        | 'features_section'
        | 'getting_started'
        | 'mobile_apps_section'
        | 'clients_section'
        | 'plans_section'
        | 'faq_section'
        | 'contact_section'
        | 'footer',
) => {
    form.settings[key].enabled = !form.settings[key].enabled;
};
</script>

<template>
    <Head :title="localize('Design Settings', 'إعدادات التصميم')" />
    <SuperAdminLayout>
        <main class="flex-1 space-y-6 p-8">
            <div
                class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between"
            >
                <div>
                    <h1 class="text-2xl font-semibold">
                        {{ localize('Design Settings', 'إعدادات التصميم') }}
                    </h1>
                    <p class="text-sm text-muted-foreground">
                        {{
                            localize(
                                'Edit the public landing page and preview the design from inside super admin.',
                                'عدّل صفحة الهبوط العامة واعرض المعاينة من داخل لوحة السوبر أدمن.',
                            )
                        }}
                    </p>
                </div>
                <div class="flex items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        @click="refreshPreview"
                    >
                        <RefreshCw class="mr-2 h-4 w-4" />
                        {{ localize('Refresh Preview', 'تحديث المعاينة') }}
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        @click="openPreview"
                    >
                        <ExternalLink class="mr-2 h-4 w-4" />
                        {{
                            localize(
                                'Open Full Preview',
                                'فتح المعاينة الكاملة',
                            )
                        }}
                    </Button>
                    <Button :disabled="form.processing" @click="submit">
                        {{
                            form.processing
                                ? localize('Saving...', 'جارٍ الحفظ...')
                                : localize('Save Design', 'حفظ التصميم')
                        }}
                    </Button>
                </div>
            </div>

            <div
                class="grid gap-6 xl:grid-cols-[minmax(0,1.05fr)_minmax(420px,0.95fr)]"
            >
                <form class="space-y-6" @submit.prevent="submit">
                    <Card>
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-4 space-y-0"
                        >
                            <div class="space-y-1.5">
                                <CardTitle>{{
                                    localize('Hero', 'القسم الرئيسي')
                                }}</CardTitle>
                                <CardDescription>{{
                                    localize(
                                        'Main title, description, image, and quick highlights.',
                                        'العنوان الرئيسي والوصف والصورة وأبرز النقاط السريعة.',
                                    )
                                }}</CardDescription>
                            </div>
                            <div class="flex items-center gap-3">
                                <Label class="text-sm">{{
                                    form.settings.hero.enabled
                                        ? localize('Visible', 'ظاهر')
                                        : localize('Hidden', 'مخفي')
                                }}</Label>
                                <Switch
                                    v-model:checked="form.settings.hero.enabled"
                                />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-lg border border-dashed p-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        localize(
                                            'Control whether this section appears on the landing page.',
                                            'تحكم بظهور هذا القسم في صفحة الهبوط.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        form.settings.hero.enabled
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    @click="toggleSection('hero')"
                                >
                                    {{
                                        form.settings.hero.enabled
                                            ? localize(
                                                  'Hide Section',
                                                  'إخفاء القسم',
                                              )
                                            : localize(
                                                  'Show Section',
                                                  'إظهار القسم',
                                              )
                                    }}
                                </Button>
                            </div>
                            <div class="space-y-2">
                                <Label for="hero_title">{{
                                    localize('Title', 'العنوان')
                                }}</Label>
                                <Input
                                    id="hero_title"
                                    v-model="form.settings.hero.title"
                                />
                                <p
                                    v-if="form.errors['settings.hero.title']"
                                    class="text-sm text-red-600"
                                >
                                    {{ form.errors['settings.hero.title'] }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <Label for="hero_description">{{
                                    localize('Description', 'الوصف')
                                }}</Label>
                                <Textarea
                                    id="hero_description"
                                    v-model="form.settings.hero.description"
                                    rows="4"
                                />
                                <p
                                    v-if="
                                        form.errors['settings.hero.description']
                                    "
                                    class="text-sm text-red-600"
                                >
                                    {{
                                        form.errors['settings.hero.description']
                                    }}
                                </p>
                            </div>

                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <Label>{{
                                        localize(
                                            'Hero Media Source',
                                            'مصدر وسائط القسم الرئيسي',
                                        )
                                    }}</Label>
                                    <span class="text-xs text-muted-foreground">
                                        {{
                                            localize(
                                                'Current page language:',
                                                'لغة الصفحة الحالية:',
                                            )
                                        }}
                                        {{ localeDisplayName(activeHeroLocale) }}
                                    </span>
                                </div>
                                <Select v-model="heroSourceMode">
                                    <SelectTrigger>
                                        <SelectValue
                                            :placeholder="
                                                localize(
                                                    'Select source',
                                                    'اختر المصدر',
                                                )
                                            "
                                        />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="upload">{{
                                            localize(
                                                'Upload image or video',
                                                'رفع صورة أو فيديو',
                                            )
                                        }}</SelectItem>
                                        <SelectItem value="url">{{
                                            localize(
                                                'External URL',
                                                'رابط خارجي',
                                            )
                                        }}</SelectItem>
                                    </SelectContent>
                                </Select>

                                <div
                                    v-if="heroSourceMode === 'upload'"
                                    class="space-y-2"
                                >
                                    <FileUpload
                                        v-if="activeHeroUsesDefaultMedia"
                                        ref="fileUploadRef"
                                        v-model="heroTempFolders"
                                        :initial-files="heroFiles || []"
                                        :allow-multiple="false"
                                        :max-files="1"
                                        :instant-upload="false"
                                        :max-file-size="1024 * 1024 * 50"
                                        :allowed-file-types="[
                                            'image/jpeg',
                                            'image/png',
                                            'image/webp',
                                            'image/gif',
                                            'video/mp4',
                                            'video/webm',
                                            'video/ogg',
                                            'video/quicktime',
                                        ]"
                                        collection="hero"
                                        theme="light"
                                        width="100%"
                                        @local-file-added="
                                            handleHeroLocalFileAdded
                                        "
                                        @file-removed="handleHeroFileRemoved"
                                    />
                                    <FileUpload
                                        v-else
                                        ref="fileUploadRef"
                                        v-model="
                                            heroLocaleTempFolders[
                                                activeHeroLocale
                                            ]
                                        "
                                        :initial-files="
                                            heroLocaleFiles(activeHeroLocale)
                                        "
                                        :allow-multiple="false"
                                        :max-files="1"
                                        :instant-upload="false"
                                        :max-file-size="1024 * 1024 * 50"
                                        :allowed-file-types="[
                                            'image/jpeg',
                                            'image/png',
                                            'image/webp',
                                            'image/gif',
                                            'video/mp4',
                                            'video/webm',
                                            'video/ogg',
                                            'video/quicktime',
                                        ]"
                                        :collection="`hero_${activeHeroLocale}`"
                                        theme="light"
                                        width="100%"
                                        @file-removed="
                                            (data) =>
                                                handleHeroLocaleFileRemoved(
                                                    activeHeroLocale,
                                                    data,
                                                )
                                        "
                                        @local-file-added="
                                            handleHeroLocalFileAdded
                                        "
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        {{
                                            localize(
                                                'Upload one image or video for the hero section. Recommended image size: 1200 x 689 px. A new upload replaces the previous file.',
                                                'ارفع صورة أو فيديو واحد للقسم الرئيسي. أي رفع جديد سيستبدل الملف السابق.',
                                            )
                                        }}
                                    </p>
                                </div>

                                <div v-else class="space-y-2">
                                    <Input
                                        v-if="activeHeroUsesDefaultMedia"
                                        id="hero_image_url"
                                        v-model="form.settings.hero.image_url"
                                        placeholder="https://..."
                                    />
                                    <Input
                                        v-else
                                        v-model="
                                            form.settings.hero.localized_images[
                                                activeHeroLocale
                                            ]
                                        "
                                        placeholder="https://..."
                                    />
                                </div>

                                <div
                                    v-if="previewHeroUrl"
                                    class="overflow-hidden rounded-lg border bg-muted/20"
                                >
                                    <video
                                        v-if="heroIsVideo"
                                        :src="previewHeroUrl"
                                        class="h-44 w-full object-cover"
                                        controls
                                        muted
                                        playsinline
                                    />
                                    <img
                                        v-else
                                        :src="previewHeroUrl"
                                        alt="hero preview"
                                        class="h-44 w-full object-cover"
                                    />
                                </div>

                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{
                                        localize(
                                            'Hero Features',
                                            'مزايا القسم الرئيسي',
                                        )
                                    }}</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addHeroFeature"
                                        >{{
                                            localize(
                                                'Add Feature',
                                                'إضافة ميزة',
                                            )
                                        }}</Button
                                    >
                                </div>
                                <div
                                    v-for="(_item, index) in form.settings.hero
                                        .features"
                                    :key="`hero-feature-${index}`"
                                    class="flex items-center gap-2"
                                >
                                    <Input
                                        v-model="
                                            form.settings.hero.features[index]
                                        "
                                        :placeholder="
                                            localize(
                                                'Feature text',
                                                'نص الميزة',
                                            )
                                        "
                                    />
                                    <Button
                                        type="button"
                                        variant="destructive"
                                        size="sm"
                                        @click="removeHeroFeature(index)"
                                        >{{ localize('Remove', 'حذف') }}</Button
                                    >
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>{{
                                localize('Additional Sections', 'أقسام إضافية')
                            }}</CardTitle>
                            <CardDescription>{{
                                localize(
                                    'Show or hide landing sections that do not have editable text here.',
                                    'إظهار أو إخفاء أقسام صفحة الهبوط التي لا تحتوي على نصوص قابلة للتعديل هنا.',
                                )
                            }}</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-3">
                            <div
                                class="flex items-center justify-between rounded-lg border border-dashed p-3"
                            >
                                <div>
                                    <p class="font-medium text-foreground">
                                        {{
                                            localize(
                                                'Cars Section',
                                                'قسم السيارات',
                                            )
                                        }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            localize(
                                                'Featured cars search and listing block on the landing page.',
                                                'قسم البحث والسيارات المعروضة في صفحة الهبوط.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        form.settings.cars_section.enabled
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    @click="toggleSection('cars_section')"
                                >
                                    {{
                                        form.settings.cars_section.enabled
                                            ? localize(
                                                  'Hide Section',
                                                  'إخفاء القسم',
                                              )
                                            : localize(
                                                  'Show Section',
                                                  'إظهار القسم',
                                              )
                                    }}
                                </Button>
                            </div>

                            <div class="space-y-3 rounded-lg border p-4">
                                <div class="flex flex-wrap items-center justify-between gap-3">
                                    <div>
                                        <Label>{{
                                            localize(
                                                'View Complete Fleet Button Icon',
                                                'أيقونة زر عرض الأسطول الكامل',
                                            )
                                        }}</Label>
                                        <p class="mt-1 text-sm text-muted-foreground">
                                            {{
                                                localize(
                                                    'Upload the optional icon shown inside the fleet button.',
                                                    'ارفع الأيقونة الاختيارية التي تظهر داخل زر عرض الأسطول.',
                                                )
                                            }}
                                        </p>
                                    </div>
                                    <img
                                        v-if="
                                            form.settings.cars_section
                                                .fleet_button_icon_url
                                        "
                                        :src="
                                            form.settings.cars_section
                                                .fleet_button_icon_url
                                        "
                                        alt=""
                                        class="h-8 w-8 rounded-md border bg-white object-contain p-1"
                                    />
                                </div>
                                <FileUpload
                                    v-model="carsFleetButtonIconTempFolders"
                                    :initial-files="
                                        carsFleetButtonIconFiles || []
                                    "
                                    :allow-multiple="false"
                                    :max-files="1"
                                    :instant-upload="false"
                                    :max-file-size="1024 * 1024 * 5"
                                    :allowed-file-types="[
                                        'image/svg+xml',
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                        'image/gif',
                                    ]"
                                    collection="cars_fleet_button_icon"
                                    theme="light"
                                    width="100%"
                                    @file-removed="
                                        handleCarsFleetButtonIconFileRemoved
                                    "
                                    @local-file-added="
                                        handleCarsFleetButtonIconLocalFileAdded
                                    "
                                />
                                <p
                                    v-if="
                                        form.errors[
                                            'cars_fleet_button_icon_direct_file'
                                        ]
                                    "
                                    class="text-sm text-red-600"
                                >
                                    {{
                                        form.errors[
                                            'cars_fleet_button_icon_direct_file'
                                        ]
                                    }}
                                </p>
                            </div>

                            <div
                                class="flex items-center justify-between rounded-lg border border-dashed p-3"
                            >
                                <div>
                                    <p class="font-medium text-foreground">
                                        {{
                                            localize(
                                                'Clients Section',
                                                'قسم العملاء',
                                            )
                                        }}
                                    </p>
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            localize(
                                                'Trusted clients and tenants logos block on the landing page.',
                                                'قسم العملاء والشعارات الموثوقة في صفحة الهبوط.',
                                            )
                                        }}
                                    </p>
                                </div>
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        form.settings.clients_section.enabled
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    @click="toggleSection('clients_section')"
                                >
                                    {{
                                        form.settings.clients_section.enabled
                                            ? localize(
                                                  'Hide Section',
                                                  'إخفاء القسم',
                                              )
                                            : localize(
                                                  'Show Section',
                                                  'إظهار القسم',
                                              )
                                    }}
                                </Button>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-4 space-y-0"
                        >
                            <div class="space-y-1.5">
                                <CardTitle>{{
                                    localize('Mobile Apps', 'تطبيقات الجوال')
                                }}</CardTitle>
                                <CardDescription>{{
                                    localize(
                                        'Three role-based apps shown after the setup section.',
                                        'ثلاثة تطبيقات حسب الدور تظهر بعد قسم البدء.',
                                    )
                                }}</CardDescription>
                            </div>
                            <div class="flex items-center gap-3">
                                <Label class="text-sm">{{
                                    form.settings.mobile_apps_section.enabled
                                        ? localize('Visible', 'ظاهر')
                                        : localize('Hidden', 'مخفي')
                                }}</Label>
                                <Switch
                                    v-model:checked="
                                        form.settings.mobile_apps_section
                                            .enabled
                                    "
                                />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-lg border border-dashed p-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        localize(
                                            'Control whether this section appears on the landing page.',
                                            'تحكم بظهور هذا القسم في صفحة الهبوط.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        form.settings.mobile_apps_section
                                            .enabled
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    @click="
                                        toggleSection('mobile_apps_section')
                                    "
                                >
                                    {{
                                        form.settings.mobile_apps_section
                                            .enabled
                                            ? localize(
                                                  'Hide Section',
                                                  'إخفاء القسم',
                                              )
                                            : localize(
                                                  'Show Section',
                                                  'إظهار القسم',
                                              )
                                    }}
                                </Button>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Eyebrow', 'النص العلوي')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.mobile_apps_section
                                                .eyebrow
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Section Title', 'عنوان القسم')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.mobile_apps_section
                                                .title
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('iOS Button Label', 'نص زر iOS')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.mobile_apps_section
                                                .ios_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Android Button Label',
                                            'نص زر Android',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.mobile_apps_section
                                                .android_label
                                        "
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>{{
                                    localize(
                                        'Section Description',
                                        'وصف القسم',
                                    )
                                }}</Label>
                                <Textarea
                                    v-model="
                                        form.settings.mobile_apps_section
                                            .description
                                    "
                                    rows="3"
                                />
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{
                                        localize('App Cards', 'بطاقات التطبيقات')
                                    }}</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addMobileApp"
                                        >{{
                                            localize(
                                                'Add App',
                                                'إضافة تطبيق',
                                            )
                                        }}</Button
                                    >
                                </div>

                                <div
                                    v-for="(app, appIndex) in form.settings
                                        .mobile_apps_section.apps"
                                    :key="`mobile-app-${appIndex}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label>{{
                                                localize(
                                                    'App Image',
                                                    'صورة التطبيق',
                                                )
                                            }}</Label>
                                            <FileUpload
                                                v-model="
                                                    mobileAppTempFolders[
                                                        appIndex
                                                    ].image
                                                "
                                                :initial-files="
                                                    mobileAppFileList(
                                                        appIndex,
                                                        'image',
                                                    )
                                                "
                                                :allow-multiple="false"
                                                :max-files="1"
                                                :instant-upload="false"
                                                :max-file-size="1024 * 1024 * 10"
                                                :allowed-file-types="[
                                                    'image/jpeg',
                                                    'image/jpg',
                                                    'image/png',
                                                    'image/svg+xml',
                                                ]"
                                                :collection="`mobile_app_${appIndex}_image`"
                                                theme="light"
                                                width="100%"
                                                @file-removed="
                                                    (data) =>
                                                        handleMobileAppFileRemoved(
                                                            appIndex,
                                                            'image',
                                                            data,
                                                        )
                                                "
                                                @local-file-added="
                                                    (file) =>
                                                        handleMobileAppLocalFileAdded(
                                                            appIndex,
                                                            'image',
                                                            file,
                                                        )
                                                "
                                            />
                                            <img
                                                v-if="app.image_url"
                                                :src="app.image_url"
                                                alt="app image preview"
                                                class="h-28 w-full rounded-lg border object-contain p-2"
                                            />
                                        </div>

                                        <div class="space-y-2">
                                            <Label>{{
                                                localize(
                                                    'Title Icon SVG',
                                                    'أيقونة العنوان SVG',
                                                )
                                            }}</Label>
                                            <FileUpload
                                                v-model="
                                                    mobileAppTempFolders[
                                                        appIndex
                                                    ].icon
                                                "
                                                :initial-files="
                                                    mobileAppFileList(
                                                        appIndex,
                                                        'icon',
                                                    )
                                                "
                                                :allow-multiple="false"
                                                :max-files="1"
                                                :instant-upload="false"
                                                :max-file-size="1024 * 1024 * 2"
                                                :allowed-file-types="[
                                                    'image/svg+xml',
                                                ]"
                                                :collection="`mobile_app_${appIndex}_icon`"
                                                theme="light"
                                                width="100%"
                                                @file-removed="
                                                    (data) =>
                                                        handleMobileAppFileRemoved(
                                                            appIndex,
                                                            'icon',
                                                            data,
                                                        )
                                                "
                                                @local-file-added="
                                                    (file) =>
                                                        handleMobileAppLocalFileAdded(
                                                            appIndex,
                                                            'icon',
                                                            file,
                                                        )
                                                "
                                            />
                                            <img
                                                v-if="app.icon_url"
                                                :src="app.icon_url"
                                                alt="app icon preview"
                                                class="h-16 w-16 rounded-xl border object-contain p-2"
                                            />
                                        </div>
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label>{{
                                                localize(
                                                    'App Title',
                                                    'عنوان التطبيق',
                                                )
                                            }}</Label>
                                            <Input v-model="app.title" />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>{{
                                                localize(
                                                    'Subtitle',
                                                    'العنوان الفرعي',
                                                )
                                            }}</Label>
                                            <Input v-model="app.subtitle" />
                                        </div>
                                    </div>

                                    <div v-if="false" class="space-y-2">
                                        <Label>{{
                                            localize(
                                                'Image URL',
                                                'رابط الصورة',
                                            )
                                        }}</Label>
                                        <Input
                                            v-model="app.image_url"
                                            placeholder="https://..."
                                        />
                                    </div>

                                    <div class="grid gap-3 md:grid-cols-2">
                                        <div class="space-y-2">
                                            <Label>{{
                                                localize(
                                                    'App Store URL',
                                                    'رابط App Store',
                                                )
                                            }}</Label>
                                            <Input
                                                v-model="app.app_store_url"
                                                placeholder="https://..."
                                            />
                                        </div>
                                        <div class="space-y-2">
                                            <Label>{{
                                                localize(
                                                    'Google Play URL',
                                                    'رابط Google Play',
                                                )
                                            }}</Label>
                                            <Input
                                                v-model="app.google_play_url"
                                                placeholder="https://..."
                                            />
                                        </div>
                                    </div>

                                    <div class="space-y-2">
                                        <Label>{{
                                            localize('Description', 'الوصف')
                                        }}</Label>
                                        <Textarea
                                            v-model="app.description"
                                            rows="3"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <div
                                            class="flex items-center justify-between"
                                        >
                                            <Label>{{
                                                localize(
                                                    'Features',
                                                    'المميزات',
                                                )
                                            }}</Label>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                @click="
                                                    addMobileAppFeature(
                                                        appIndex,
                                                    )
                                                "
                                                >{{
                                                    localize(
                                                        'Add Feature',
                                                        'إضافة ميزة',
                                                    )
                                                }}</Button
                                            >
                                        </div>
                                        <div
                                            v-for="(
                                                _feature, featureIndex
                                            ) in app.features"
                                            :key="`mobile-app-${appIndex}-feature-${featureIndex}`"
                                            class="flex items-center gap-2"
                                        >
                                            <Input
                                                v-model="
                                                    app.features[featureIndex]
                                                "
                                            />
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="destructive"
                                                @click="
                                                    removeMobileAppFeature(
                                                        appIndex,
                                                        featureIndex,
                                                    )
                                                "
                                                >{{
                                                    localize(
                                                        'Remove',
                                                        'حذف',
                                                    )
                                                }}</Button
                                            >
                                        </div>
                                    </div>

                                    <div class="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            @click="removeMobileApp(appIndex)"
                                            >{{
                                                localize(
                                                    'Remove App',
                                                    'حذف التطبيق',
                                                )
                                            }}</Button
                                        >
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-4 space-y-0"
                        >
                            <div class="space-y-1.5">
                                <CardTitle>{{
                                    localize('Features Section', 'قسم المزايا')
                                }}</CardTitle>
                                <CardDescription>{{
                                    localize(
                                        'Section intro and the feature cards shown on the landing page.',
                                        'مقدمة القسم وبطاقات المزايا المعروضة في صفحة الهبوط.',
                                    )
                                }}</CardDescription>
                            </div>
                            <div class="flex items-center gap-3">
                                <Label class="text-sm">{{
                                    form.settings.features_section.enabled
                                        ? localize('Visible', 'ظاهر')
                                        : localize('Hidden', 'مخفي')
                                }}</Label>
                                <Switch
                                    v-model:checked="
                                        form.settings.features_section.enabled
                                    "
                                />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-lg border border-dashed p-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        localize(
                                            'Control whether this section appears on the landing page.',
                                            'تحكم بظهور هذا القسم في صفحة الهبوط.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        form.settings.features_section.enabled
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    @click="toggleSection('features_section')"
                                >
                                    {{
                                        form.settings.features_section.enabled
                                            ? localize(
                                                  'Hide Section',
                                                  'إخفاء القسم',
                                              )
                                            : localize(
                                                  'Show Section',
                                                  'إظهار القسم',
                                              )
                                    }}
                                </Button>
                            </div>
                            <div class="space-y-2">
                                <Label for="features_title">{{
                                    localize('Title', 'العنوان')
                                }}</Label>
                                <Input
                                    id="features_title"
                                    v-model="
                                        form.settings.features_section.title
                                    "
                                />
                            </div>

                            <div class="space-y-2">
                                <Label for="features_description">{{
                                    localize('Description', 'الوصف')
                                }}</Label>
                                <Textarea
                                    id="features_description"
                                    v-model="
                                        form.settings.features_section
                                            .description
                                    "
                                    rows="3"
                                />
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{
                                        localize(
                                            'Feature Cards',
                                            'بطاقات المزايا',
                                        )
                                    }}</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addFeatureCard"
                                        >{{
                                            localize('Add Card', 'إضافة بطاقة')
                                        }}</Button
                                    >
                                </div>
                                <div
                                    v-for="(card, index) in form.settings
                                        .features_section.cards"
                                    :key="`feature-card-${index}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize(
                                                'Card Title',
                                                'عنوان البطاقة',
                                            )
                                        }}</Label>
                                        <Input v-model="card.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize('Image URL', 'رابط الصورة')
                                        }}</Label>
                                        <FileUpload
                                            v-model="
                                                featureCardTempFolders[index]
                                            "
                                            :initial-files="
                                                featureCardFileList(index)
                                            "
                                            :allow-multiple="false"
                                            :max-files="1"
                                            :instant-upload="false"
                                            :max-file-size="1024 * 1024 * 5"
                                            :allowed-file-types="[
                                                'image/jpeg',
                                                'image/jpg',
                                                'image/png',
                                                'image/svg+xml',
                                            ]"
                                            :collection="`feature_card_${index}_image`"
                                            theme="light"
                                            width="100%"
                                            @file-removed="
                                                (data) =>
                                                    handleFeatureCardFileRemoved(
                                                        index,
                                                        data,
                                                    )
                                            "
                                            @local-file-added="
                                                (file) =>
                                                    handleFeatureCardLocalFileAdded(
                                                        index,
                                                        file,
                                                    )
                                            "
                                        />
                                        <img
                                            v-if="card.image_url"
                                            :src="card.image_url"
                                            alt="feature card image preview"
                                            class="h-28 w-full rounded-lg border object-contain p-2"
                                        />
                                        <Input
                                            v-model="card.image_url"
                                            class="hidden"
                                            placeholder="https://..."
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize(
                                                'Icon Background',
                                                'خلفية الأيقونة',
                                            )
                                        }}</Label>
                                        <div class="flex items-center gap-3">
                                            <Input
                                                v-model="
                                                    card.icon_background_color
                                                "
                                                type="color"
                                                class="h-10 w-16 cursor-pointer p-1"
                                            />
                                            <Input
                                                v-model="
                                                    card.icon_background_color
                                                "
                                                placeholder="#f3f4f6"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize('Content', 'المحتوى')
                                        }}</Label>
                                        <Textarea
                                            v-model="card.content"
                                            rows="3"
                                        />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            @click="removeFeatureCard(index)"
                                            >{{
                                                localize(
                                                    'Remove Card',
                                                    'حذف البطاقة',
                                                )
                                            }}</Button
                                        >
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-4 space-y-0"
                        >
                            <div class="space-y-1.5">
                                <CardTitle>{{
                                    localize('Getting Started', 'البدء')
                                }}</CardTitle>
                                <CardDescription>{{
                                    localize(
                                        'Control the section that explains the setup steps.',
                                        'تحكم في القسم الذي يشرح خطوات الإعداد.',
                                    )
                                }}</CardDescription>
                            </div>
                            <div class="flex items-center gap-3">
                                <Label class="text-sm">{{
                                    form.settings.getting_started.enabled
                                        ? localize('Visible', 'ظاهر')
                                        : localize('Hidden', 'مخفي')
                                }}</Label>
                                <Switch
                                    v-model:checked="
                                        form.settings.getting_started.enabled
                                    "
                                />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-lg border border-dashed p-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        localize(
                                            'Control whether this section appears on the landing page.',
                                            'تحكم بظهور هذا القسم في صفحة الهبوط.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        form.settings.getting_started.enabled
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    @click="toggleSection('getting_started')"
                                >
                                    {{
                                        form.settings.getting_started.enabled
                                            ? localize(
                                                  'Hide Section',
                                                  'إخفاء القسم',
                                              )
                                            : localize(
                                                  'Show Section',
                                                  'إظهار القسم',
                                              )
                                    }}
                                </Button>
                            </div>
                            <div class="space-y-2">
                                <Label>{{
                                    localize('Section Title', 'عنوان القسم')
                                }}</Label>
                                <Input
                                    v-model="
                                        form.settings.getting_started.title
                                    "
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>{{
                                    localize('Section Description', 'وصف القسم')
                                }}</Label>
                                <Textarea
                                    v-model="
                                        form.settings.getting_started
                                            .description
                                    "
                                    rows="3"
                                />
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{
                                        localize('Steps', 'الخطوات')
                                    }}</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addStepItem"
                                        >{{
                                            localize('Add Step', 'إضافة خطوة')
                                        }}</Button
                                    >
                                </div>
                                <div
                                    v-for="(item, index) in form.settings
                                        .getting_started.items"
                                    :key="`step-${index}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize(
                                                'Step Image',
                                                'صورة الخطوة',
                                            )
                                        }}</Label>
                                        <FileUpload
                                            v-model="
                                                gettingStartedTempFolders[
                                                    index
                                                ]
                                            "
                                            :initial-files="
                                                gettingStartedFileList(index)
                                            "
                                            :allow-multiple="false"
                                            :max-files="1"
                                            :instant-upload="false"
                                            :max-file-size="1024 * 1024 * 5"
                                            :allowed-file-types="[
                                                'image/jpeg',
                                                'image/jpg',
                                                'image/png',
                                                'image/svg+xml',
                                            ]"
                                            :collection="`getting_started_step_${index}_image`"
                                            theme="light"
                                            width="100%"
                                            @file-removed="
                                                (data) =>
                                                    handleGettingStartedFileRemoved(
                                                        index,
                                                        data,
                                                    )
                                            "
                                            @local-file-added="
                                                (file) =>
                                                    handleGettingStartedLocalFileAdded(
                                                        index,
                                                        file,
                                                    )
                                            "
                                        />
                                        <img
                                            v-if="item.image_url"
                                            :src="item.image_url"
                                            alt="getting started step image preview"
                                            class="h-28 w-full rounded-lg border object-contain p-2"
                                        />
                                        <Input
                                            v-model="item.image_url"
                                            class="hidden"
                                        />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize(
                                                'Icon Background',
                                                'خلفية الأيقونة',
                                            )
                                        }}</Label>
                                        <div class="flex items-center gap-3">
                                            <Input
                                                v-model="
                                                    item.icon_background_color
                                                "
                                                type="color"
                                                class="h-10 w-16 cursor-pointer p-1"
                                            />
                                            <Input
                                                v-model="
                                                    item.icon_background_color
                                                "
                                                placeholder="#f3f4f6"
                                            />
                                        </div>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize(
                                                'Step Title',
                                                'عنوان الخطوة',
                                            )
                                        }}</Label>
                                        <Input v-model="item.title" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize(
                                                'Step Description',
                                                'وصف الخطوة',
                                            )
                                        }}</Label>
                                        <Textarea
                                            v-model="item.description"
                                            rows="2"
                                        />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            @click="removeStepItem(index)"
                                            >{{
                                                localize(
                                                    'Remove Step',
                                                    'حذف الخطوة',
                                                )
                                            }}</Button
                                        >
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-4 space-y-0"
                        >
                            <div class="space-y-1.5">
                                <CardTitle>{{
                                    localize(
                                        'Plans & FAQ',
                                        'الخطط والأسئلة الشائعة',
                                    )
                                }}</CardTitle>
                                <CardDescription>{{
                                    localize(
                                        'Pricing section heading plus FAQ content.',
                                        'عنوان قسم التسعير مع محتوى الأسئلة الشائعة.',
                                    )
                                }}</CardDescription>
                            </div>
                            <div class="flex flex-col items-end gap-3">
                                <div class="flex items-center gap-3">
                                    <Label class="text-sm">{{
                                        localize('Plans', 'الخطط')
                                    }}</Label>
                                    <Switch
                                        v-model:checked="
                                            form.settings.plans_section.enabled
                                        "
                                    />
                                </div>
                                <div class="flex items-center gap-3">
                                    <Label class="text-sm">{{
                                        localize('FAQ', 'الأسئلة')
                                    }}</Label>
                                    <Switch
                                        v-model:checked="
                                            form.settings.faq_section.enabled
                                        "
                                    />
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                class="grid gap-3 rounded-lg border border-dashed p-3 md:grid-cols-2"
                            >
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            localize(
                                                'Show or hide the plans section.',
                                                'إظهار أو إخفاء قسم الخطط.',
                                            )
                                        }}
                                    </p>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="
                                            form.settings.plans_section.enabled
                                                ? 'destructive'
                                                : 'outline'
                                        "
                                        @click="toggleSection('plans_section')"
                                    >
                                        {{
                                            form.settings.plans_section.enabled
                                                ? localize(
                                                      'Hide Plans',
                                                      'إخفاء الخطط',
                                                  )
                                                : localize(
                                                      'Show Plans',
                                                      'إظهار الخطط',
                                                  )
                                        }}
                                    </Button>
                                </div>
                                <div
                                    class="flex items-center justify-between gap-3"
                                >
                                    <p class="text-sm text-muted-foreground">
                                        {{
                                            localize(
                                                'Show or hide the FAQ section.',
                                                'إظهار أو إخفاء قسم الأسئلة.',
                                            )
                                        }}
                                    </p>
                                    <Button
                                        type="button"
                                        size="sm"
                                        :variant="
                                            form.settings.faq_section.enabled
                                                ? 'destructive'
                                                : 'outline'
                                        "
                                        @click="toggleSection('faq_section')"
                                    >
                                        {{
                                            form.settings.faq_section.enabled
                                                ? localize(
                                                      'Hide FAQ',
                                                      'إخفاء الأسئلة',
                                                  )
                                                : localize(
                                                      'Show FAQ',
                                                      'إظهار الأسئلة',
                                                  )
                                        }}
                                    </Button>
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Plans Title', 'عنوان الخطط')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.plans_section.title
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Plans Description',
                                            'وصف الخطط',
                                        )
                                    }}</Label>
                                    <Textarea
                                        v-model="
                                            form.settings.plans_section
                                                .description
                                        "
                                        rows="2"
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>{{
                                    localize(
                                        'FAQ Title',
                                        'عنوان الأسئلة الشائعة',
                                    )
                                }}</Label>
                                <Input
                                    v-model="form.settings.faq_section.title"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>{{
                                    localize(
                                        'FAQ Description',
                                        'وصف الأسئلة الشائعة',
                                    )
                                }}</Label>
                                <Textarea
                                    v-model="
                                        form.settings.faq_section.description
                                    "
                                    rows="2"
                                />
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{
                                        localize(
                                            'FAQ Items',
                                            'عناصر الأسئلة الشائعة',
                                        )
                                    }}</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addFaqItem"
                                        >{{
                                            localize('Add FAQ', 'إضافة سؤال')
                                        }}</Button
                                    >
                                </div>
                                <div
                                    v-for="(faq, index) in form.settings
                                        .faq_section.items"
                                    :key="`faq-${index}`"
                                    class="space-y-3 rounded-lg border p-4"
                                >
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize('Question', 'السؤال')
                                        }}</Label>
                                        <Input v-model="faq.question" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize('Answer', 'الإجابة')
                                        }}</Label>
                                        <Textarea
                                            v-model="faq.answer"
                                            rows="3"
                                        />
                                    </div>
                                    <div class="flex justify-end">
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            @click="removeFaqItem(index)"
                                            >{{
                                                localize(
                                                    'Remove FAQ',
                                                    'حذف السؤال',
                                                )
                                            }}</Button
                                        >
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-4 space-y-0"
                        >
                            <div class="space-y-1.5">
                                <CardTitle>{{
                                    localize('Contact Section', 'قسم التواصل')
                                }}</CardTitle>
                                <CardDescription>{{
                                    localize(
                                        'Edit the English source text for the contact form, direct contact card, and quick links.',
                                        'عدّل النص الإنجليزي الأساسي لنموذج التواصل وبطاقة التواصل المباشر والروابط السريعة.',
                                    )
                                }}</CardDescription>
                            </div>
                            <div class="flex items-center gap-3">
                                <Label class="text-sm">{{
                                    form.settings.contact_section.enabled
                                        ? localize('Visible', 'ظاهر')
                                        : localize('Hidden', 'مخفي')
                                }}</Label>
                                <Switch
                                    v-model:checked="
                                        form.settings.contact_section.enabled
                                    "
                                />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-lg border border-dashed p-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        localize(
                                            'Control whether this section appears on the landing page.',
                                            'تحكم بظهور هذا القسم في صفحة الهبوط.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        form.settings.contact_section.enabled
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    @click="toggleSection('contact_section')"
                                >
                                    {{
                                        form.settings.contact_section.enabled
                                            ? localize(
                                                  'Hide Section',
                                                  'إخفاء القسم',
                                              )
                                            : localize(
                                                  'Show Section',
                                                  'إظهار القسم',
                                              )
                                    }}
                                </Button>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Section Title', 'عنوان القسم')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section.title
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Form Card Title',
                                            'عنوان بطاقة النموذج',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .form_title
                                        "
                                    />
                                </div>
                            </div>

                            <div class="space-y-2">
                                <Label>{{
                                    localize('Section Description', 'وصف القسم')
                                }}</Label>
                                <Textarea
                                    v-model="
                                        form.settings.contact_section
                                            .description
                                    "
                                    rows="2"
                                />
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Name Label',
                                            'عنوان حقل الاسم',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .name_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Name Placeholder',
                                            'تلميح حقل الاسم',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .name_placeholder
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Email Label',
                                            'عنوان حقل البريد',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .email_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Email Placeholder',
                                            'تلميح حقل البريد',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .email_placeholder
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Subject Label',
                                            'عنوان حقل الموضوع',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .subject_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Subject Placeholder',
                                            'تلميح حقل الموضوع',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .subject_placeholder
                                        "
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Message Label',
                                            'عنوان حقل الرسالة',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .message_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Message Placeholder',
                                            'تلميح حقل الرسالة',
                                        )
                                    }}</Label>
                                    <Textarea
                                        v-model="
                                            form.settings.contact_section
                                                .message_placeholder
                                        "
                                        rows="2"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Submit Label',
                                            'نص زر الإرسال',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .submit_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Sending Label',
                                            'نص حالة الإرسال',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .sending_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Success Message',
                                            'رسالة النجاح',
                                        )
                                    }}</Label>
                                    <Textarea
                                        v-model="
                                            form.settings.contact_section
                                                .success_message
                                        "
                                        rows="2"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Error Message', 'رسالة الخطأ')
                                    }}</Label>
                                    <Textarea
                                        v-model="
                                            form.settings.contact_section
                                                .error_message
                                        "
                                        rows="2"
                                    />
                                </div>
                            </div>

                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Direct Contact Title',
                                            'عنوان التواصل المباشر',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .direct_title
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Quick Links Title',
                                            'عنوان الروابط السريعة',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .quick_links_title
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Email Label', 'عنوان البريد')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .direct_email_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Email Address', 'البريد')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .direct_email
                                        "
                                        type="email"
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Phone Label', 'عنوان الهاتف')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .direct_phone_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize('Phone Number', 'رقم الهاتف')
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .direct_phone
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Response Time Label',
                                            'عنوان زمن الاستجابة',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .response_time_label
                                        "
                                    />
                                </div>
                                <div class="space-y-2">
                                    <Label>{{
                                        localize(
                                            'Response Time',
                                            'زمن الاستجابة',
                                        )
                                    }}</Label>
                                    <Input
                                        v-model="
                                            form.settings.contact_section
                                                .response_time
                                        "
                                    />
                                </div>
                            </div>

                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{
                                        localize(
                                            'Quick Links',
                                            'الروابط السريعة',
                                        )
                                    }}</Label>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        size="sm"
                                        @click="addQuickLink"
                                        >{{
                                            localize('Add Link', 'إضافة رابط')
                                        }}</Button
                                    >
                                </div>
                                <div
                                    v-for="(link, index) in form.settings
                                        .contact_section.quick_links"
                                    :key="`contact-link-${index}`"
                                    class="grid gap-3 rounded-lg border p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
                                >
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize('Label', 'النص')
                                        }}</Label>
                                        <Input v-model="link.label" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{
                                            localize('Href', 'الرابط')
                                        }}</Label>
                                        <Input
                                            v-model="link.href"
                                            placeholder="#cars"
                                        />
                                    </div>
                                    <div class="flex items-end">
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="sm"
                                            @click="removeQuickLink(index)"
                                            >{{
                                                localize('Remove', 'حذف')
                                            }}</Button
                                        >
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader
                            class="flex flex-row items-start justify-between gap-4 space-y-0"
                        >
                            <div class="space-y-1.5">
                                <CardTitle>{{
                                    localize('Footer', 'التذييل')
                                }}</CardTitle>
                                <CardDescription>{{
                                    localize(
                                        'Final call to action shown at the bottom of the landing page.',
                                        'الدعوة النهائية لاتخاذ الإجراء في أسفل صفحة الهبوط.',
                                    )
                                }}</CardDescription>
                            </div>
                            <div class="flex items-center gap-3">
                                <Label class="text-sm">{{
                                    form.settings.footer.enabled
                                        ? localize('Visible', 'ظاهر')
                                        : localize('Hidden', 'مخفي')
                                }}</Label>
                                <Switch
                                    v-model:checked="
                                        form.settings.footer.enabled
                                    "
                                />
                            </div>
                        </CardHeader>
                        <CardContent class="space-y-4">
                            <div
                                class="flex items-center justify-between rounded-lg border border-dashed p-3"
                            >
                                <p class="text-sm text-muted-foreground">
                                    {{
                                        localize(
                                            'Control whether this footer appears on the landing page.',
                                            'تحكم بظهور هذا التذييل في صفحة الهبوط.',
                                        )
                                    }}
                                </p>
                                <Button
                                    type="button"
                                    size="sm"
                                    :variant="
                                        form.settings.footer.enabled
                                            ? 'destructive'
                                            : 'outline'
                                    "
                                    @click="toggleSection('footer')"
                                >
                                    {{
                                        form.settings.footer.enabled
                                            ? localize(
                                                  'Hide Footer',
                                                  'إخفاء التذييل',
                                              )
                                            : localize(
                                                  'Show Footer',
                                                  'إظهار التذييل',
                                              )
                                    }}
                                </Button>
                            </div>
                            <div class="space-y-2">
                                <Label>{{
                                    localize('Footer Title', 'عنوان التذييل')
                                }}</Label>
                                <Input v-model="form.settings.footer.title" />
                            </div>
                            <div class="space-y-2">
                                <Label>{{
                                    localize(
                                        'Footer Description',
                                        'وصف التذييل',
                                    )
                                }}</Label>
                                <Textarea
                                    v-model="form.settings.footer.description"
                                    rows="3"
                                />
                            </div>
                            <div class="space-y-2">
                                <Label>{{
                                    localize('Copyright Text', 'نص حقوق النشر')
                                }}</Label>
                                <Input
                                    v-model="form.settings.footer.copyright_text"
                                    placeholder="All rights reserved."
                                />
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="flex items-center justify-between rounded-lg border p-3">
                                    <div>
                                        <p class="text-sm font-medium">{{ localize('Show social icons', 'إظهار أيقونات السوشال') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ localize('Control footer social buttons.', 'تحكم بأزرار السوشال في الفوتر.') }}</p>
                                    </div>
                                    <Switch v-model:checked="form.settings.footer.show_social_links" />
                                </div>
                                <div class="flex items-center justify-between rounded-lg border p-3">
                                    <div>
                                        <p class="text-sm font-medium">{{ localize('Show app buttons', 'إظهار أزرار التطبيقات') }}</p>
                                        <p class="text-xs text-muted-foreground">{{ localize('Use store links from the mobile apps section.', 'استخدم روابط المتاجر من قسم تطبيقات الموبايل.') }}</p>
                                    </div>
                                    <Switch v-model:checked="form.settings.footer.show_app_buttons" />
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div class="space-y-3 rounded-lg border p-3">
                                    <Label>{{ localize('Android Button', 'زر Android') }}</Label>
                                    <div class="space-y-2">
                                        <Label class="text-xs">{{ localize('Label', 'النص') }}</Label>
                                        <Input v-model="form.settings.footer.android_label" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-xs">{{ localize('URL', 'الرابط') }}</Label>
                                        <Input v-model="form.settings.footer.android_url" placeholder="https://..." />
                                    </div>
                                </div>
                                <div class="space-y-3 rounded-lg border p-3">
                                    <Label>{{ localize('iOS Button', 'زر iOS') }}</Label>
                                    <div class="space-y-2">
                                        <Label class="text-xs">{{ localize('Label', 'النص') }}</Label>
                                        <Input v-model="form.settings.footer.ios_label" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label class="text-xs">{{ localize('URL', 'الرابط') }}</Label>
                                        <Input v-model="form.settings.footer.ios_url" placeholder="https://..." />
                                    </div>
                                </div>
                            </div>
                            <div class="space-y-3">
                                <div class="flex items-center justify-between">
                                    <Label>{{ localize('Footer Social Links', 'روابط السوشال في الفوتر') }}</Label>
                                    <Button type="button" size="sm" variant="outline" @click="addFooterSocialLink">
                                        {{ localize('Add Social Link', 'إضافة رابط سوشال') }}
                                    </Button>
                                </div>
                                <div
                                    v-for="(link, index) in form.settings.footer.social_links"
                                    :key="`footer-social-${index}`"
                                    class="grid gap-3 rounded-lg border p-3 md:grid-cols-[1fr_1fr_2fr_auto]"
                                >
                                    <div class="space-y-2">
                                        <Label>{{ localize('Label', 'النص') }}</Label>
                                        <Input v-model="link.label" />
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ localize('Platform', 'المنصة') }}</Label>
                                        <Select v-model="link.platform">
                                            <SelectTrigger>
                                                <SelectValue :placeholder="localize('Select platform', 'اختر المنصة')" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="facebook">Facebook</SelectItem>
                                                <SelectItem value="instagram">Instagram</SelectItem>
                                                <SelectItem value="linkedin">LinkedIn</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="space-y-2">
                                        <Label>{{ localize('URL', 'الرابط') }}</Label>
                                        <Input v-model="link.href" placeholder="https://..." />
                                    </div>
                                    <div class="flex items-end">
                                        <Button type="button" size="sm" variant="destructive" @click="removeFooterSocialLink(index)">
                                            {{ localize('Remove', 'حذف') }}
                                        </Button>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>
                </form>

                <div class="space-y-4">
                    <Card class="sticky top-6 overflow-hidden">
                        <CardHeader class="border-b">
                            <CardTitle>{{
                                localize('Live Preview', 'معاينة مباشرة')
                            }}</CardTitle>
                            <CardDescription>
                                {{
                                    localize(
                                        'The preview renders the public landing page on the main domain. Save changes to refresh it.',
                                        'تعرض المعاينة صفحة الهبوط العامة على الدومين الرئيسي. احفظ التغييرات لتحديثها.',
                                    )
                                }}
                            </CardDescription>
                        </CardHeader>
                        <CardContent class="p-0">
                            <iframe
                                :key="previewSrc"
                                :src="previewSrc"
                                class="h-[calc(100vh-16rem)] min-h-[720px] w-full bg-white"
                                :title="
                                    localize(
                                        'Landing page preview',
                                        'معاينة صفحة الهبوط',
                                    )
                                "
                            />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </main>
    </SuperAdminLayout>
</template>
