import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { ProductCard, type ProductCardData } from './product-card';

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export default function StorefrontCategory({
    category,
    products,
}: {
    category: { name: string; slug: string; description: string | null };
    products: Paginated<ProductCardData>;
}) {
    return (
        <>
            <Head title={category.name} />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold">{category.name}</h1>
                {category.description && (
                    <p className="mt-2 text-neutral-500 dark:text-neutral-400">{category.description}</p>
                )}
            </div>

            {products.data.length === 0 ? (
                <p className="py-12 text-center text-neutral-500">No hay productos en esta categoría.</p>
            ) : (
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    {products.data.map((product) => (
                        <ProductCard key={product.sku} product={product} />
                    ))}
                </div>
            )}

            <div className="mt-8 flex items-center justify-between text-sm text-neutral-500">
                <span>{products.total} productos</span>
                <div className="flex gap-2">
                    {products.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={products.prev_page_url} preserveScroll>Anterior</Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Anterior</Button>
                    )}
                    {products.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={products.next_page_url} preserveScroll>Siguiente</Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Siguiente</Button>
                    )}
                </div>
            </div>
        </>
    );
}
