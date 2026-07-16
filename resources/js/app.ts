import '../css/app.css';

import { createInertiaApp, router } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import type { DefineComponent } from 'vue';
import { createApp, h } from 'vue';
import { initializeTheme } from './composables/useAppearance';

import { setUrlDefaults } from './wayfinder';

let appName = import.meta.env.VITE_APP_NAME || 'Car4u';

const formatPageTitle = (title?: string): string => {
    const cleanTitle = String(title || '').trim();

    if (!cleanTitle || cleanTitle.toLowerCase() === 'laravel') {
        return appName;
    }

    return `${cleanTitle} - ${appName}`;
};

const syncDocumentLocale = (props: any) => {
    const locale = props?.locale || 'en';
    const direction = props?.direction || 'ltr';
    const currentAppName = props?.name || import.meta.env.VITE_APP_NAME || 'Car4u';

    document.documentElement.lang = locale;
    document.documentElement.dir = direction;
    appName = currentAppName;
};

createInertiaApp({
    title: formatPageTitle,
    resolve: (name) =>
        resolvePageComponent(
            `./pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const pageProps = props.initialPage.props as any;
        if (pageProps.current_tenant?.slug) {
            setUrlDefaults({ subdomain: pageProps.current_tenant.slug });
        }

        syncDocumentLocale(pageProps);

        router.on('navigate', (event: any) => {
            syncDocumentLocale(event.detail.page.props);
        });

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#f56100',
    },
});

// Force light mode on page load
initializeTheme();
