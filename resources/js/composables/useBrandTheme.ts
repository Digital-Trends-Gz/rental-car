import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

type BrandingSource = {
    primary_color?: string | null;
    secondary_color?: string | null;
};

const colorOrFallback = (value: unknown, fallback: string) => {
    if (typeof value !== 'string') {
        return fallback;
    }

    const trimmed = value.trim();

    return /^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/.test(trimmed) ? trimmed : fallback;
};

export function useBrandTheme(defaultPrimary = '#f97316', defaultSecondary = '#ea580c') {
    const page = usePage<any>();

    const tenantBranding = computed<BrandingSource | null>(() => page.props.tenant_site_settings ?? null);
    const appBranding = computed<BrandingSource | null>(() => page.props.app_branding ?? null);
    const branding = computed<BrandingSource>(() => tenantBranding.value ?? appBranding.value ?? {});

    const primaryColor = computed(() => colorOrFallback(branding.value.primary_color, defaultPrimary));
    const secondaryColor = computed(() => colorOrFallback(branding.value.secondary_color, defaultSecondary));

    const themeVars = computed<Record<string, string>>(() => ({
        '--primary': primaryColor.value,
        '--primary-foreground': '#ffffff',
        '--ring': primaryColor.value,
        '--sidebar-primary': primaryColor.value,
        '--sidebar-ring': primaryColor.value,
        '--tenant-primary': primaryColor.value,
        '--tenant-secondary': secondaryColor.value,
        '--tenant-gradient': `linear-gradient(90deg, ${primaryColor.value}, ${secondaryColor.value})`,
        '--gradient-primary': `linear-gradient(135deg, ${primaryColor.value}, ${secondaryColor.value})`,
    }));

    return {
        primaryColor,
        secondaryColor,
        themeVars,
    };
}
