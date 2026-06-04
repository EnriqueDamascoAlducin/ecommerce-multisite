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

export function ProductCard({ product }: { product: ProductCardData }) {
    const urls = useStoreUrls();

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
