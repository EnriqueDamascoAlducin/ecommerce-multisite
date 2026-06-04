import { Link } from '@inertiajs/react';
import { ChevronDown, ImageIcon } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { formatPrice, useStoreUrls } from '@/lib/storefront';

type MenuProduct = {
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
};

type MenuItem = {
    id: number | string;
    type: string;
    label: string;
    url: string | null;
    expand_products: boolean;
    children: MenuItem[];
    products: MenuProduct[];
};

export function MegaMenu({ items }: { items: MenuItem[] }) {
    const urls = useStoreUrls();

    return (
        <nav className="border-t border-neutral-100 dark:border-neutral-900">
            <div className="mx-auto flex w-full max-w-6xl gap-0 px-4 text-sm">
                {items.map((item) => (
                    <MenuItemComponent key={item.id} item={item} urls={urls} />
                ))}
            </div>
        </nav>
    );
}

function MenuItemComponent({
    item,
    urls,
}: {
    item: MenuItem;
    urls: ReturnType<typeof useStoreUrls>;
}) {
    const [open, setOpen] = useState(false);
    const hasChildren = item.children.length > 0;
    const hasProducts = item.products.length > 0;

    return (
        <div
            className="group relative"
            onMouseEnter={() => setOpen(true)}
            onMouseLeave={() => setOpen(false)}
        >
            <Link
                href={item.url ?? urls.home()}
                className="flex items-center gap-1 px-3 py-2 text-neutral-600 hover:text-neutral-900 dark:text-neutral-400 dark:hover:text-neutral-100"
            >
                {item.label}
                {(hasChildren || hasProducts) && (
                    <ChevronDown className="size-3.5" />
                )}
            </Link>

            {(hasChildren || hasProducts) && open && (
                <div className="absolute top-full left-0 z-50 min-w-[220px] rounded-lg border border-neutral-200 bg-white p-3 shadow-lg dark:border-neutral-800 dark:bg-neutral-950">
                    {hasProducts && (
                        <div className="grid grid-cols-3 gap-3">
                            {item.products.map((product) => (
                                <Link
                                    key={product.id}
                                    href={product.url}
                                    className="group/card flex flex-col overflow-hidden rounded-md border border-neutral-100 transition-shadow hover:shadow-md dark:border-neutral-800"
                                >
                                    <div className="flex aspect-square items-center justify-center overflow-hidden bg-neutral-50 dark:bg-neutral-900">
                                        {product.thumbnail ? (
                                            <img
                                                src={product.thumbnail}
                                                alt={product.name}
                                                className="h-full w-full object-cover transition-transform group-hover/card:scale-105"
                                            />
                                        ) : (
                                            <ImageIcon className="size-6 text-neutral-300" />
                                        )}
                                    </div>
                                    <div className="flex flex-col gap-0.5 p-2">
                                        <span className="line-clamp-1 text-xs font-medium">
                                            {product.name}
                                        </span>
                                        <span className="text-xs font-semibold">
                                            {product.price.is_special &&
                                            product.price.special_price ? (
                                                <>
                                                    <span className="text-red-600">
                                                        {formatPrice(
                                                            product.price
                                                                .special_price,
                                                        )}
                                                    </span>
                                                    <span className="ml-1 text-neutral-400 line-through">
                                                        {formatPrice(
                                                            product.price.price,
                                                        )}
                                                    </span>
                                                </>
                                            ) : (
                                                formatPrice(
                                                    product.price
                                                        .effective_price,
                                                )
                                            )}
                                        </span>
                                        {!product.in_stock && (
                                            <Badge
                                                variant="outline"
                                                className="w-fit text-[10px]"
                                            >
                                                Agotado
                                            </Badge>
                                        )}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    )}
                    {hasChildren && (
                        <div className="flex flex-col gap-1">
                            {item.children.map((child) => (
                                <Link
                                    key={child.id}
                                    href={child.url ?? urls.home()}
                                    className="rounded-md px-2 py-1.5 text-sm text-neutral-600 hover:bg-neutral-50 hover:text-neutral-900 dark:text-neutral-400 dark:hover:bg-neutral-800 dark:hover:text-neutral-100"
                                >
                                    {child.label}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
