import { Head, usePage } from '@inertiajs/react';
import { ProductCard, type ProductCardData } from './product-card';
import {
    SectionRenderer,
    type CmsPage,
} from '@/components/storefront/page-sections';

export default function StorefrontHome({
    featured,
    contentPage,
}: {
    featured: ProductCardData[];
    contentPage?: CmsPage;
}) {
    const { store } = usePage().props;

    return (
        <>
            <Head
                title={
                    contentPage?.title ?? (store ? store.store.name : 'Inicio')
                }
            />

            {contentPage && contentPage.sections.length > 0 ? (
                <>
                    {contentPage.sections.map((section) => (
                        <SectionRenderer key={section.id} section={section} />
                    ))}
                </>
            ) : (
                <FallbackHome featured={featured} />
            )}
        </>
    );
}

function FallbackHome({ featured }: { featured: ProductCardData[] }) {
    const { store } = usePage().props;

    return (
        <>
            <section className="mb-10 rounded-xl bg-neutral-100 px-6 py-16 text-center dark:bg-neutral-900">
                <h1 className="text-3xl font-bold">
                    {store ? `Bienvenido a ${store.store.name}` : 'Bienvenido'}
                </h1>
                <p className="mx-auto mt-2 max-w-md text-neutral-500 dark:text-neutral-400">
                    Descubre nuestros productos.
                </p>
            </section>
            <section>
                <h2 className="mb-4 text-xl font-semibold">Destacados</h2>
                {featured.length === 0 ? (
                    <p className="py-8 text-center text-neutral-500">
                        Aun no hay productos en esta tienda.
                    </p>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {featured.map((product) => (
                            <ProductCard key={product.sku} product={product} />
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}
