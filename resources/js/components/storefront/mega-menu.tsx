import { Link } from '@inertiajs/react';
import { ArrowRight, ChevronDown, ImageIcon } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
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

export type MegaMenuColors = {
    text?: string | null;
    background?: string | null;
};

export function MegaMenu({
    items,
    colors,
}: {
    items: MenuItem[];
    colors?: MegaMenuColors;
}) {
    const urls = useStoreUrls();

    return (
        <nav
            className="border-t border-neutral-100 dark:border-neutral-900"
            style={{ backgroundColor: colors?.background ?? undefined }}
        >
            <div className="mx-auto flex w-full max-w-6xl gap-1 overflow-x-auto px-4 text-sm md:overflow-visible">
                {items.map((item) => (
                    <MenuItemComponent
                        key={item.id}
                        item={item}
                        urls={urls}
                        textColor={colors?.text ?? undefined}
                    />
                ))}
            </div>
        </nav>
    );
}

function MenuItemComponent({
    item,
    urls,
    textColor,
}: {
    item: MenuItem;
    urls: ReturnType<typeof useStoreUrls>;
    textColor?: string;
}) {
    const [open, setOpen] = useState(false);
    const [dropdownTop, setDropdownTop] = useState(0);
    const hasChildren = item.children.length > 0;
    const hasProducts = item.products.length > 0;
    const hasDropdown = hasChildren || hasProducts;
    const topItemClassName =
        'flex h-11 items-center gap-1.5 whitespace-nowrap rounded-md px-3 text-sm font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-red-700/30';
    const openMenu = (target: HTMLElement) => {
        setDropdownTop(target.getBoundingClientRect().bottom);
        setOpen(true);
    };

    return (
        <div
            className="group relative"
            onMouseEnter={(event) => openMenu(event.currentTarget)}
            onMouseLeave={() => setOpen(false)}
            onFocus={(event) => openMenu(event.currentTarget)}
            onBlur={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    setOpen(false);
                }
            }}
            onKeyDown={(event) => {
                if (event.key === 'Escape') {
                    setOpen(false);
                }
            }}
        >
            {hasDropdown ? (
                <button
                    type="button"
                    onClick={(event) => {
                        if (open) {
                            setOpen(false);
                            return;
                        }

                        openMenu(event.currentTarget);
                    }}
                    className={cn(
                        topItemClassName,
                        open
                            ? 'bg-red-50 text-red-900 dark:bg-red-950/30 dark:text-red-100'
                            : 'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950 dark:text-neutral-400 dark:hover:bg-neutral-900 dark:hover:text-neutral-100',
                    )}
                    style={{ color: textColor }}
                    aria-expanded={open}
                >
                    {item.label}
                    <ChevronDown
                        className={cn(
                            'size-3.5 transition-transform',
                            open ? 'rotate-180' : '',
                        )}
                    />
                </button>
            ) : (
                <Link
                    href={item.url ?? urls.home()}
                    className={cn(
                        topItemClassName,
                        'text-neutral-600 hover:bg-neutral-50 hover:text-neutral-950 dark:text-neutral-400 dark:hover:bg-neutral-900 dark:hover:text-neutral-100',
                    )}
                    style={{ color: textColor }}
                >
                    {item.label}
                </Link>
            )}

            {hasDropdown && open && (
                <div
                    className="fixed left-1/2 z-50 w-[min(72rem,calc(100vw-2rem))] -translate-x-1/2 overflow-hidden rounded-lg border border-neutral-200 bg-white shadow-2xl shadow-neutral-950/10 ring-1 ring-black/5 dark:border-neutral-800 dark:bg-neutral-950 dark:shadow-black/30"
                    style={{ top: dropdownTop }}
                >
                    <div className="grid max-h-[min(34rem,calc(100vh-11rem))] gap-0 overflow-hidden md:grid-cols-[17rem_minmax(0,1fr)]">
                        <div className="border-b border-neutral-100 bg-neutral-50 p-5 dark:border-neutral-800 dark:bg-neutral-900/70 md:border-r md:border-b-0">
                            <p className="text-xs font-semibold tracking-wide text-red-800 uppercase dark:text-red-300">
                                {item.label}
                            </p>
                            <p className="mt-2 text-sm leading-5 text-neutral-600 dark:text-neutral-400">
                                Explora categorias y productos destacados.
                            </p>
                            {item.url && (
                                <Link
                                    href={item.url}
                                    className="mt-4 inline-flex items-center gap-1 text-sm font-semibold text-red-800 transition hover:text-red-950 dark:text-red-300 dark:hover:text-red-100"
                                >
                                    Ver todo
                                    <ArrowRight className="size-4" />
                                </Link>
                            )}
                        </div>

                        <div className="min-h-0 overflow-y-auto p-5">
                            {hasChildren && (
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {item.children.map((child) => (
                                        <Link
                                            key={child.id}
                                            href={child.url ?? urls.home()}
                                            className="group/link rounded-md border border-transparent p-3 transition hover:border-red-900/10 hover:bg-red-50/70 dark:hover:border-red-200/10 dark:hover:bg-red-950/20"
                                        >
                                            <span className="flex items-center justify-between gap-3 text-sm font-semibold text-neutral-900 dark:text-neutral-100">
                                                {child.label}
                                                <ArrowRight className="size-4 text-neutral-300 transition group-hover/link:translate-x-0.5 group-hover/link:text-red-700 dark:text-neutral-600 dark:group-hover/link:text-red-300" />
                                            </span>
                                        </Link>
                                    ))}
                                </div>
                            )}

                            {hasProducts && (
                                <div className={cn('grid gap-3', hasChildren ? 'mt-5' : '')}>
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs font-semibold tracking-wide text-neutral-500 uppercase dark:text-neutral-400">
                                            Productos destacados
                                        </p>
                                        <span className="rounded-full bg-neutral-100 px-2 py-0.5 text-[11px] font-semibold text-neutral-500 dark:bg-neutral-900 dark:text-neutral-400">
                                            {item.products.length}
                                        </span>
                                        <span className="h-px flex-1 bg-neutral-100 dark:bg-neutral-800" />
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                                        {item.products.map((product) => (
                                            <ProductMenuCard
                                                key={product.id}
                                                product={product}
                                            />
                                        ))}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            )}
        </div>
    );
}

function ProductMenuCard({ product }: { product: MenuProduct }) {
    const hasSpecialPrice =
        product.price.is_special &&
        product.price.special_price !== null &&
        product.price.special_price !== '' &&
        product.price.price !== null &&
        product.price.price !== '';
    const hasEffectivePrice =
        product.price.effective_price !== null &&
        product.price.effective_price !== '';

    return (
        <Link
            href={product.url}
            className="group/card flex min-w-0 gap-3 rounded-lg border border-neutral-100 bg-white p-2.5 transition hover:-translate-y-0.5 hover:border-red-900/15 hover:shadow-md dark:border-neutral-800 dark:bg-neutral-950 dark:hover:border-red-200/20"
        >
            <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md bg-neutral-100 dark:bg-neutral-900">
                {product.thumbnail ? (
                    <img
                        src={product.thumbnail}
                        alt={product.name}
                        className="size-full object-cover transition-transform group-hover/card:scale-105"
                    />
                ) : (
                    <ImageIcon className="size-6 text-neutral-300" />
                )}
            </div>
            <div className="min-w-0 flex-1">
                <p className="line-clamp-2 text-sm leading-5 font-semibold text-neutral-900 dark:text-neutral-100">
                    {product.name}
                </p>
                <p className="mt-1 text-[11px] text-neutral-500">
                    {product.sku}
                </p>
                <div className="mt-2 flex flex-wrap items-center gap-2 text-xs font-semibold">
                    {hasSpecialPrice ? (
                        <>
                            <span className="text-red-700 dark:text-red-300">
                                {formatPrice(product.price.special_price)}
                            </span>
                            <span className="text-neutral-400 line-through">
                                {formatPrice(product.price.price)}
                            </span>
                        </>
                    ) : hasEffectivePrice ? (
                        <span>{formatPrice(product.price.effective_price)}</span>
                    ) : (
                        <span className="text-neutral-400">Consultar precio</span>
                    )}
                    {!product.in_stock && (
                        <Badge variant="outline" className="text-[10px]">
                            Agotado
                        </Badge>
                    )}
                </div>
            </div>
        </Link>
    );
}
