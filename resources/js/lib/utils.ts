import { InertiaLinkProps } from '@inertiajs/vue3';
import { clsx, type ClassValue } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function urlIsActive(
    urlToCheck: NonNullable<InertiaLinkProps['href']>,
    currentUrl: string,
) {
    const target = normalizeUrlPath(toUrl(urlToCheck) as string);
    const current = normalizeUrlPath(currentUrl);

    if (target === '/') return current === '/';

    // Active if exact match or if the current path is a subpath of target
    return current === target || current.startsWith(target + '/');
}

export function toUrl(href: NonNullable<InertiaLinkProps['href']>) {
    return typeof href === 'string' ? href : href?.url;
}

export function withLocalePrefix(
    url: string,
    locale: string | null | undefined,
    availableLocales: unknown,
): string {
    const localeCode = String(locale || '').trim();
    const locales = Array.isArray(availableLocales)
        ? availableLocales.map(String).filter(Boolean)
        : [];

    if (!localeCode || !locales.includes(localeCode)) {
        return url;
    }

    const localeRegex = new RegExp(`^/(${locales.map(escapeRegExp).join('|')})(?=/|$)`);

    try {
        const baseUrl = typeof window !== 'undefined' ? window.location.origin : 'https://localhost';
        const parsedUrl = new URL(url, baseUrl);
        const path = parsedUrl.pathname.replace(localeRegex, '') || '/';

        parsedUrl.pathname = `/${localeCode}${path}`;

        return typeof window !== 'undefined' && parsedUrl.origin === window.location.origin
            ? `${parsedUrl.pathname}${parsedUrl.search}${parsedUrl.hash}`
            : parsedUrl.toString();
    } catch {
        const path = (url.startsWith('/') ? url : `/${url}`).replace(localeRegex, '') || '/';

        return `/${localeCode}${path}`;
    }
}

function normalizeUrlPath(url: string): string {
    if (!url) return '/';

    let path = url;

    // If absolute URL, strip protocol and host
    const schemeIndex = path.indexOf('://');
    if (schemeIndex !== -1) {
        const pathStart = path.indexOf('/', schemeIndex + 3);
        path = pathStart !== -1 ? path.substring(pathStart) : '/';
    }

    // Strip hash and query
    const hashIndex = path.indexOf('#');
    if (hashIndex !== -1) path = path.substring(0, hashIndex);
    const queryIndex = path.indexOf('?');
    if (queryIndex !== -1) path = path.substring(0, queryIndex);

    // Ensure leading slash
    if (path.length === 0 || path[0] !== '/') path = '/' + path;

    // Remove trailing slashes except for root
    if (path.length > 1) path = path.replace(/\/+$/, '');

    // Treat localized and non-localized paths as the same route for active-link checks.
    const localeSegment = path.split('/')[1];
    const supportedLocales = getSupportedLocales();
    if (localeSegment && supportedLocales.includes(localeSegment)) {
        path = path.replace(new RegExp(`^/${escapeRegExp(localeSegment)}(?=/|$)`), '') || '/';
    }

    return path;
}

function getSupportedLocales(): string[] {
    if (typeof window === 'undefined') {
        return [];
    }

    const initialPage = (window as { __INITIAL_PAGE__?: any }).__INITIAL_PAGE__;
    const serializedPage = document.getElementById('app')?.dataset?.page;
    const parsedPage = safeParseJson(serializedPage);
    const page = initialPage ?? parsedPage;

    const locales = page?.props?.available_locales;
    if (Array.isArray(locales)) {
        return locales.filter((value): value is string => typeof value === 'string' && value.length > 0);
    }

    if (locales && typeof locales === 'object') {
        return Object.keys(locales);
    }

    return [];
}

function safeParseJson(value: string | undefined): any | null {
    if (!value) return null;
    try {
        return JSON.parse(value);
    } catch {
        return null;
    }
}

function escapeRegExp(value: string): string {
    return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}
