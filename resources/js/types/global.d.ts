import type { Auth } from '@/types/auth';

declare module 'react' {
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
    id: number;
    type: string;
    label: string;
    url: string | null;
    expand_products: boolean;
    children: StoreMenuItem[];
    products: StoreMenuProduct[];
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
                website: { id: number; code: string; name: string };
                store: { id: number; code: string; name: string };
                locale: string | null;
                pathPrefix: string;
                menu: StoreMenuItem[];
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
