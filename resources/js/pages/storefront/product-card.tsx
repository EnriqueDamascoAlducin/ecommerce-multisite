import { Link } from '@inertiajs/react';
import { ImageIcon } from 'lucide-react';
import { ProductLabels, type ProductLabelData } from '@/components/product-labels';
import { Badge } from '@/components/ui/badge';
import { formatPrice, type Price, useStoreUrls } from '@/lib/storefront';

export type ProductCardData = {
    sku: string;
    name: string;
    slug: string;
    price: Price;
    thumbnail: string | null;
    in_stock: boolean;
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
        <Link
            href={urls.product(product.slug)}
            className="group flex flex-col overflow-hidden rounded-lg border border-neutral-200 transition-shadow hover:shadow-md dark:border-neutral-800"
        >
            <div className="relative flex aspect-square items-center justify-center overflow-hidden bg-neutral-100 dark:bg-neutral-900">
                {product.thumbnail ? (
                    <img src={product.thumbnail} alt={product.name} className="h-full w-full object-cover transition-transform group-hover:scale-105" />
                ) : (
                    <ImageIcon className="size-10 text-neutral-300" />
                )}
                <ProductLabels labels={product.labels} className="absolute left-2 top-2" />
            </div>
            <div className="flex flex-1 flex-col gap-1 p-3">
                <h3 className="line-clamp-2 text-sm font-medium">{product.name}</h3>
                <div className="mt-auto flex items-center gap-2">
                    {product.price.is_special && product.price.special_price ? (
                        <>
                            <span className="font-semibold text-red-600">{formatPrice(product.price.special_price)}</span>
                            <span className="text-xs text-neutral-400 line-through">{formatPrice(product.price.price)}</span>
                        </>
                    ) : (
                        <span className="font-semibold">{formatPrice(product.price.effective_price)}</span>
                    )}
                </div>
                {!product.in_stock && <Badge variant="outline" className="w-fit">Agotado</Badge>}
            </div>
        </Link>
    );
}
