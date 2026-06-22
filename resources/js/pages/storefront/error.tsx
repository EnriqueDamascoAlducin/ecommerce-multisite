import { Head, Link } from '@inertiajs/react';
import { Home, Search, SearchX } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { useStoreUrls } from '@/lib/storefront';

export default function StorefrontError({ status }: { status: 404 }) {
    const urls = useStoreUrls();

    return (
        <>
            <Head title={'P\u00e1gina no encontrada'}>
                <meta
                    head-key="robots"
                    name="robots"
                    content="noindex, nofollow"
                />
            </Head>

            <section
                aria-labelledby="not-found-title"
                className="relative isolate grid min-h-[60vh] place-items-center overflow-hidden py-12 sm:py-16"
            >
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 flex items-center justify-center overflow-hidden"
                >
                    <span className="text-9xl leading-none font-black text-neutral-100 select-none sm:text-[12rem] lg:text-[16rem]">
                        {status}
                    </span>
                </div>

                <div className="relative mx-auto grid max-w-2xl justify-items-center gap-6 text-center">
                    <div className="flex size-14 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-700 shadow-sm">
                        <SearchX className="size-6" aria-hidden="true" />
                    </div>

                    <div className="grid gap-3">
                        <p className="text-sm font-semibold text-neutral-500">
                            Error {status}
                        </p>
                        <h1
                            id="not-found-title"
                            className="text-3xl font-bold text-neutral-950 sm:text-4xl"
                        >
                            No encontramos esta p&aacute;gina
                        </h1>
                        <p className="mx-auto max-w-lg text-base leading-7 text-neutral-600">
                            Puede que el enlace haya cambiado o que el contenido
                            ya no est&eacute; disponible. Puedes volver al
                            inicio o explorar los productos de la tienda.
                        </p>
                    </div>

                    <div className="flex w-full flex-col justify-center gap-3 sm:w-auto sm:flex-row">
                        <Button asChild size="lg">
                            <Link href={urls.home()}>
                                <Home aria-hidden="true" />
                                Volver al inicio
                            </Link>
                        </Button>
                        <Button asChild size="lg" variant="outline">
                            <Link href={urls.search()}>
                                <Search aria-hidden="true" />
                                Explorar productos
                            </Link>
                        </Button>
                    </div>
                </div>
            </section>
        </>
    );
}
