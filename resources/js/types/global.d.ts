import type { Auth } from '@/types/auth';

declare module 'react' {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface InputHTMLAttributes<T> {
        passwordrules?: string;
    }
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
                menu: { name: string; slug: string }[];
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
