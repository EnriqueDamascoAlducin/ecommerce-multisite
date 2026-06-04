import { usePage } from '@inertiajs/react';

/**
 * Construye URLs del storefront respetando el prefijo de la tienda actual
 * cuando ésta se resolvió por path (multisitio por prefijo, p. ej. /sports).
 */
export type Price = {
    price: string | null;
    special_price: string | null;
    effective_price: string | null;
    is_special: boolean;
};

const formatter = new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' });

export function formatPrice(value: string | number | null): string {
    if (value === null || value === '') {
        return '—';
    }

    return formatter.format(Number(value));
}

export function useStoreUrls() {
    const { store } = usePage().props;
    const prefix = store?.pathPrefix ? `/${store.pathPrefix}` : '';

    return {
        home: () => prefix || '/',
        category: (slug: string) => `${prefix}/c/${slug}`,
        product: (slug: string) => `${prefix}/p/${slug}`,
        path: (path: string) => `${prefix}${path.startsWith('/') ? path : `/${path}`}`,
    };
}
