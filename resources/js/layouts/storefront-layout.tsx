import { Head, Link, router, usePage } from '@inertiajs/react';
import { Search, ShoppingCart } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { CintilloBar } from '@/components/storefront/cintillo';
import { MegaMenu } from '@/components/storefront/mega-menu';
import { useStoreUrls } from '@/lib/storefront';

export default function StorefrontLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { store, customer, cart, flash } = usePage().props;
    const urls = useStoreUrls();
    const colors = store?.header.colors;
    const [searchQuery, setSearchQuery] = useState('');
    const pwaBase = store?.pathPrefix ? `/${store.pathPrefix}` : '';
    const manifestUrl = `${pwaBase}/manifest.webmanifest`;
    const serviceWorkerUrl = `${pwaBase}/service-worker.js`;
    const iconUrl =
        store?.website.favicon_url ?? store?.website.logo_url ?? null;

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    useEffect(() => {
        if (!store || !('serviceWorker' in navigator)) {
            return;
        }

        void navigator.serviceWorker
            .register(serviceWorkerUrl)
            .catch(() => undefined);
    }, [serviceWorkerUrl, store]);

    return (
        <div className="flex min-h-screen flex-col bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
            {store && (
                <Head>
                    <link
                        head-key="manifest"
                        rel="manifest"
                        href={manifestUrl}
                    />
                    <meta
                        head-key="theme-color"
                        name="theme-color"
                        content="#991b1b"
                    />
                    <meta
                        head-key="apple-mobile-web-app-capable"
                        name="apple-mobile-web-app-capable"
                        content="yes"
                    />
                    <meta
                        head-key="apple-mobile-web-app-title"
                        name="apple-mobile-web-app-title"
                        content={store.website.name}
                    />
                    {iconUrl && (
                        <link head-key="app-icon" rel="icon" href={iconUrl} />
                    )}
                    {iconUrl && (
                        <link
                            head-key="apple-touch-icon"
                            rel="apple-touch-icon"
                            href={iconUrl}
                        />
                    )}
                </Head>
            )}

            {store && <CintilloBar cintillo={store.header.cintillo} />}

            <header
                className="border-b border-neutral-200 dark:border-neutral-800"
                style={{
                    color: colors?.header_text_color ?? undefined,
                    backgroundColor:
                        colors?.header_background_color ?? undefined,
                }}
            >
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between gap-4 px-4 py-4">
                    <Link
                        href={urls.home()}
                        className="flex shrink-0 items-center gap-2 text-lg font-semibold"
                    >
                        {store?.website.logo_url ? (
                            <img
                                src={store.website.logo_url}
                                alt={store?.store.name ?? 'Tienda'}
                                className="h-8 w-auto"
                            />
                        ) : (
                            (store?.store.name ?? 'Tienda')
                        )}
                    </Link>

                    <div className="hidden flex-1 md:block">
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();
                                router.get(urls.search(), { q: searchQuery });
                                setSearchQuery('');
                            }}
                            className="relative"
                        >
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-neutral-400" />
                            <input
                                type="text"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                                placeholder="Buscar por nombre o SKU"
                                className="w-full rounded-full border border-neutral-300 bg-neutral-50 py-2 pr-4 pl-9 text-sm outline-none focus:border-neutral-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                            />
                        </form>
                    </div>

                    <div className="flex shrink-0 items-center gap-4 text-sm text-neutral-600 dark:text-neutral-400">
                        {customer ? (
                            <>
                                <Link
                                    href="/cuenta"
                                    className="hover:text-neutral-900 dark:hover:text-neutral-100"
                                >
                                    Hola, {customer.name.split(' ')[0]}
                                </Link>
                                <Link
                                    href="/cuenta/logout"
                                    method="post"
                                    as="button"
                                    className="hover:text-neutral-900 dark:hover:text-neutral-100"
                                >
                                    Salir
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link
                                    href="/cuenta/login"
                                    className="hover:text-neutral-900 dark:hover:text-neutral-100"
                                >
                                    Iniciar sesión
                                </Link>
                                <Link
                                    href="/cuenta/registro"
                                    className="hover:text-neutral-900 dark:hover:text-neutral-100"
                                >
                                    Registrarse
                                </Link>
                            </>
                        )}

                        <Link
                            href="/carrito"
                            className="relative flex items-center gap-1 hover:text-neutral-900 dark:hover:text-neutral-100"
                        >
                            <ShoppingCart className="size-5" />
                            {cart && cart.count > 0 && (
                                <span className="absolute -top-2 -right-2 flex size-4 items-center justify-center rounded-full bg-neutral-900 text-[10px] text-white dark:bg-neutral-100 dark:text-neutral-900">
                                    {cart.count}
                                </span>
                            )}
                        </Link>
                    </div>
                </div>

                <div className="px-4 pb-4 md:hidden">
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            router.get(urls.search(), { q: searchQuery });
                            setSearchQuery('');
                        }}
                        className="relative"
                    >
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-neutral-400" />
                        <input
                            type="text"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Buscar por nombre o SKU"
                            className="w-full rounded-full border border-neutral-300 bg-neutral-50 py-2 pr-4 pl-9 text-sm outline-none focus:border-neutral-500 focus:bg-white dark:border-neutral-700 dark:bg-neutral-900 dark:focus:bg-neutral-950"
                        />
                    </form>
                </div>

                {store && store.menu.length > 0 && (
                    <MegaMenu
                        items={store.menu}
                        colors={{
                            text: colors?.menu_text_color,
                            background: colors?.menu_background_color,
                        }}
                    />
                )}
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">
                {children}
            </main>

            <footer className="border-t border-neutral-200 py-6 text-center text-sm text-neutral-500 dark:border-neutral-800">
                &copy; {new Date().getFullYear()}{' '}
                {store?.website.name ?? 'Ecommerce Multisitio'}
            </footer>
        </div>
    );
}
