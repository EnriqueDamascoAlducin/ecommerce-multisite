import type { CintilloData } from '@/components/storefront/cintillo';
import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
}

interface StoreMenuProduct {
    id: number;
    slug: string;
    name: string;
    sku: string;
    url: string;
    thumbnail: string | null;
    in_stock: boolean;
    price: {
        price: string | null;
        special_price: string | null;
        effective_price: string | null;
        is_special: boolean;
    };
}

interface StoreMenuItem {
    id: number | string;
    type: string;
    label: string;
    url: string | null;
    expand_products: boolean;
    children: StoreMenuItem[];
    products: StoreMenuProduct[];
}

interface StoreFooter {
    enabled: boolean;
    description: string;
    copyright: string;
    background_color: string | null;
    text_color: string | null;
    columns: {
        title: string;
        links: { label: string; url: string }[];
    }[];
    contact: { label: string; value: string }[];
    social: { platform: string; url: string }[];
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            flash: {
                success: string | null;
                error: string | null;
            };
            store: {
                website: {
                    id: number;
                    code: string;
                    name: string;
                    logo_url?: string | null;
                    favicon_url?: string | null;
                };
                store: {
                    id: number;
                    code: string;
                    name: string;
                    logo_url: string | null;
                };
                pwa: {
                    apple_touch_icon_url: string | null;
                };
                locale: string | null;
                pathPrefix: string;
                menu: StoreMenuItem[];
                header: {
                    cintillo: CintilloData;
                    colors: {
                        header_text_color: string | null;
                        header_background_color: string | null;
                        menu_text_color: string | null;
                        menu_background_color: string | null;
                    };
                    footer: StoreFooter;
                };
            } | null;
            customer: { id: number; name: string; email: string } | null;
            cart: { count: number; total: string } | null;
            adminScope: {
                current: { type: string; id: number; label: string };
                options: { type: string; id: number; label: string }[];
            } | null;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}
