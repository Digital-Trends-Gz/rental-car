import { createInertiaApp } from '@inertiajs/vue3';
import createServer from '@inertiajs/vue3/server';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createSSRApp, DefineComponent, h } from 'vue';
import { renderToString } from 'vue/server-renderer';

const appName = import.meta.env.VITE_APP_NAME || 'Car4u';

const formatPageTitle = (title?: string): string => {
    const cleanTitle = String(title || '').trim();

    if (!cleanTitle || cleanTitle.toLowerCase() === 'laravel') {
        return appName;
    }

    return `${cleanTitle} - ${appName}`;
};

createServer(
    (page) =>
        createInertiaApp({
            page,
            render: renderToString,
            title: formatPageTitle,
            resolve: (name) =>
                resolvePageComponent(
                    `./pages/${name}.vue`,
                    import.meta.glob<DefineComponent>('./pages/**/*.vue'),
                ),
            setup: ({ App, props, plugin }) =>
                createSSRApp({ render: () => h(App, props) }).use(plugin),
        }),
    { cluster: true },
);
