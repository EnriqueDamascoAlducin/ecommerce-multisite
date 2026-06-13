import { Form, Link } from '@inertiajs/react';
import { ImageIcon, ShoppingCart, SlidersHorizontal } from 'lucide-react';
import { ProductLabels, type ProductLabelData } from '@/components/product-labels';
import { Badge } from '@/components/ui/badge';
import cart from '@/routes/cart';
import { formatPrice, type Price, useStoreUrls } from '@/lib/storefront';

export type ProductCardData = {
    id: number;
    sku: string;
    name: string;
    slug: string;
    price: Price;
    thumbnail: string | null;
    in_stock: boolean;
    requires_options?: boolean;
    labels?: ProductLabelData[];
};

export function ProductCard({
    product,
    variant = 'grid',
}: {
    product: ProductCardData;
    variant?: 'grid' | 'carousel';
}) {
    const urls = useStoreUrls();

    if (variant === 'carousel') {
        return (
            <Link
                href={urls.product(product.slug)}
                className="group flex h-full flex-col overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-sm shadow-neutral-950/5 transition duration-200 hover:-translate-y-1 hover:border-red-800/50 hover:shadow-xl hover:shadow-neutral-950/10 dark:border-neutral-800 dark:bg-neutral-950 dark:shadow-black/20 dark:hover:border-red-400/60"
            >
                <div className="relative flex aspect-[4/3] items-center justify-center overflow-hidden bg-neutral-100 dark:bg-neutral-900">
                    {product.thumbnail ? (
                        <img
                            src={product.thumbnail}
                            alt={product.name}
                            className="h-full w-full object-cover transition duration-500 group-hover:scale-105"
                        />
                    ) : (
                        <ImageIcon className="size-12 text-neutral-300 dark:text-neutral-700" />
                    )}
                    <ProductLabels
                        labels={product.labels}
                        className="absolute top-3 left-3"
                    />
                    {!product.in_stock && (
                        <Badge className="absolute right-3 bottom-3 border border-white/50 bg-neutral-950/85 text-white shadow-sm backdrop-blur dark:bg-white/90 dark:text-neutral-950">
                            Agotado
                        </Badge>
                    )}
                </div>

                <div className="flex flex-1 flex-col gap-4 p-4">
                    <div className="grid gap-2">
                        <div className="flex items-center justify-between gap-3">
                            <span className="truncate text-[11px] font-bold tracking-[0.18em] text-neutral-400 uppercase dark:text-neutral-500">
                                {product.sku}
                            </span>
                            {product.in_stock && (
                                <span className="shrink-0 rounded-full bg-emerald-50 px-2 py-1 text-[11px] font-semibold text-emerald-700 dark:bg-emerald-950/40 dark:text-emerald-300">
                                    En stock
                                </span>
                            )}
                        </div>
                        <h3 className="line-clamp-2 min-h-11 text-base leading-6 font-semibold text-neutral-950 transition group-hover:text-red-800 dark:text-neutral-50 dark:group-hover:text-red-300">
                            {product.name}
                        </h3>
                    </div>

                    <div className="mt-auto flex items-end justify-between gap-3 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        <div className="grid gap-1">
                            <span className="text-[11px] font-semibold tracking-[0.16em] text-neutral-400 uppercase dark:text-neutral-500">
                                Precio
                            </span>
                            <div className="flex flex-wrap items-baseline gap-2">
                                {product.price.is_special &&
                                product.price.special_price ? (
                                    <>
                                        <span className="text-lg font-black text-red-700 dark:text-red-300">
                                            {formatPrice(
                                                product.price.special_price,
                                            )}
                                        </span>
                                        <span className="text-xs text-neutral-400 line-through">
                                            {formatPrice(product.price.price)}
                                        </span>
                                    </>
                                ) : (
                                    <span className="text-lg font-black text-neutral-950 dark:text-neutral-50">
                                        {formatPrice(
                                            product.price.effective_price,
                                        )}
                                    </span>
                                )}
                            </div>
                        </div>
                        <span className="h-9 w-1 rounded-full bg-red-800 transition group-hover:h-12 dark:bg-red-300" />
                    </div>
                </div>
            </Link>
        );
    }

    return (
        <div className="group relative flex flex-col overflow-hidden rounded-xl border border-neutral-200 bg-white shadow-sm transition-all duration-300 hover:shadow-lg dark:border-neutral-800 dark:bg-neutral-950">
            <Link
                href={urls.product(product.slug)}
                className="relative flex aspect-square items-center justify-center overflow-hidden bg-neutral-100 dark:bg-neutral-900"
            >
                {product.thumbnail ? (
                    <img
                        src={product.thumbnail}
                        alt={product.name}
                        className="h-full w-full object-cover transition-transform duration-500 group-hover:scale-105"
                    />
                ) : (
                    <ImageIcon className="size-10 text-neutral-300 dark:text-neutral-700" />
                )}
                <ProductLabels labels={product.labels} className="absolute top-4 left-4" />
            </Link>
            <div className="flex flex-1 flex-col p-5">
                <span className="mb-1 text-[11px] font-bold tracking-[0.16em] text-neutral-400 uppercase dark:text-neutral-500">
                    {product.sku}
                </span>
                <Link href={urls.product(product.slug)}>
                    <h3 className="mb-4 line-clamp-2 text-base leading-tight font-semibold text-neutral-950 transition group-hover:text-red-700 dark:text-neutral-50 dark:group-hover:text-red-300">
                        {product.name}
                    </h3>
                </Link>
                <div className="mt-auto flex items-end justify-between gap-3">
                    <div className="flex flex-col">
                        {product.price.is_special && product.price.special_price ? (
                            <>
                                <span className="text-xs text-neutral-400 line-through">
                                    {formatPrice(product.price.price)}
                                </span>
                                <span className="text-lg font-bold text-red-700 dark:text-red-300">
                                    {formatPrice(product.price.special_price)}
                                </span>
                            </>
                        ) : (
                            <span className="text-lg font-bold text-neutral-950 dark:text-neutral-50">
                                {formatPrice(product.price.effective_price)}
                            </span>
                        )}
                    </div>
                    {!product.in_stock ? (
                        <Badge variant="outline" className="shrink-0">
                            Agotado
                        </Badge>
                    ) : product.requires_options ? (
                        <Link
                            href={urls.product(product.slug)}
                            aria-label="Elegir opciones"
                            title="Elegir opciones"
                            className="flex size-12 items-center justify-center rounded-lg border-2 border-red-700 text-red-700 transition hover:bg-red-700 hover:text-white dark:border-red-300 dark:text-red-300 dark:hover:bg-red-300 dark:hover:text-neutral-950"
                        >
                            <SlidersHorizontal className="size-5" />
                        </Link>
                    ) : (
                        <Form
                            action={cart.store.url()}
                            method="post"
                            options={{ preserveScroll: true }}
                        >
                            <input
                                type="hidden"
                                name="product_id"
                                value={product.id}
                            />
                            <input type="hidden" name="quantity" value="1" />
                            <button
                                type="submit"
                                aria-label="Agregar al carrito"
                                title="Agregar al carrito"
                                className="flex size-12 cursor-pointer items-center justify-center rounded-lg bg-red-700 text-white shadow-md transition hover:scale-105 hover:bg-red-800 active:scale-95"
                            >
                                <ShoppingCart className="size-5" />
                            </button>
                        </Form>
                    )}
                </div>
            </div>
        </div>
    );
}
