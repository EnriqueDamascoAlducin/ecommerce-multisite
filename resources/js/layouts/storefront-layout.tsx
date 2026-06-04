import { Link, usePage } from '@inertiajs/react';
import { ShoppingCart } from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { MegaMenu } from '@/components/storefront/mega-menu';
import { useStoreUrls } from '@/lib/storefront';

export default function StorefrontLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { store, customer, cart, flash } = usePage().props;
    const urls = useStoreUrls();

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    return (
        <div className="flex min-h-screen flex-col bg-white text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
            <header className="border-b border-neutral-200 dark:border-neutral-800">
                <div className="mx-auto flex w-full max-w-6xl items-center justify-between px-4 py-4">
                    <Link href={urls.home()} className="text-lg font-semibold">
                        {store?.store.name ?? 'Tienda'}
                    </Link>

                    <div className="flex items-center gap-4 text-sm text-neutral-600 dark:text-neutral-400">
                        {customer ? (
                            <>
                                <Link href="/cuenta" className="hover:text-neutral-900 dark:hover:text-neutral-100">
                                    Hola, {customer.name.split(' ')[0]}
                                </Link>
                                <Link href="/cuenta/logout" method="post" as="button" className="hover:text-neutral-900 dark:hover:text-neutral-100">
                                    Salir
                                </Link>
                            </>
                        ) : (
                            <>
                                <Link href="/cuenta/login" className="hover:text-neutral-900 dark:hover:text-neutral-100">
                                    Iniciar sesión
                                </Link>
                                <Link href="/cuenta/registro" className="hover:text-neutral-900 dark:hover:text-neutral-100">
                                    Registrarse
                                </Link>
                            </>
                        )}

                        <Link href="/carrito" className="relative flex items-center gap-1 hover:text-neutral-900 dark:hover:text-neutral-100">
                            <ShoppingCart className="size-5" />
                            {cart && cart.count > 0 && (
                                <span className="absolute -right-2 -top-2 flex size-4 items-center justify-center rounded-full bg-neutral-900 text-[10px] text-white dark:bg-neutral-100 dark:text-neutral-900">
                                    {cart.count}
                                </span>
                            )}
                        </Link>
                    </div>
                </div>

                {store && store.menu.length > 0 && (
                    <MegaMenu items={store.menu} />
                )}
            </header>

            <main className="mx-auto w-full max-w-6xl flex-1 px-4 py-8">{children}</main>

            <footer className="border-t border-neutral-200 py-6 text-center text-sm text-neutral-500 dark:border-neutral-800">
                &copy; {new Date().getFullYear()} {store?.website.name ?? 'Ecommerce Multisitio'}
            </footer>
        </div>
    );
}
