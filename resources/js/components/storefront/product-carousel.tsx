import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import {
    ProductCard,
    type ProductCardData,
} from '@/pages/storefront/product-card';

export function ProductCarousel({
    products,
    eyebrow = 'Seleccion destacada',
    title,
    subtitle,
    className = '',
    compactHeading = false,
    showHeading = true,
}: {
    products: ProductCardData[];
    eyebrow?: string;
    title?: string;
    subtitle?: string;
    className?: string;
    compactHeading?: boolean;
    showHeading?: boolean;
}) {
    const [activePage, setActivePage] = useState(0);
    const [itemsPerPage, setItemsPerPage] = useState(4);
    const [paused, setPaused] = useState(false);

    useEffect(() => {
        const updateItemsPerPage = () => {
            if (window.matchMedia('(min-width: 1024px)').matches) {
                setItemsPerPage(4);

                return;
            }

            if (window.matchMedia('(min-width: 640px)').matches) {
                setItemsPerPage(2);

                return;
            }

            setItemsPerPage(1);
        };

        updateItemsPerPage();
        window.addEventListener('resize', updateItemsPerPage);

        return () => window.removeEventListener('resize', updateItemsPerPage);
    }, []);

    const pages = useMemo(() => {
        const chunks: ProductCardData[][] = [];

        for (let index = 0; index < products.length; index += itemsPerPage) {
            chunks.push(products.slice(index, index + itemsPerPage));
        }

        return chunks;
    }, [itemsPerPage, products]);

    useEffect(() => {
        setActivePage((currentPage) =>
            Math.min(currentPage, Math.max(pages.length - 1, 0)),
        );
    }, [pages.length]);

    if (products.length === 0) {
        return null;
    }

    const totalPages = Math.max(pages.length, 1);
    const currentProducts = pages[activePage] ?? pages[0] ?? [];
    const hasControls = totalPages > 1;
    const progress = ((activePage + 1) / totalPages) * 100;

    const goToPage = (page: number) => {
        setActivePage((page + totalPages) % totalPages);
    };

    // Avance automático cada 4s; se reinicia el contador en cada cambio de
    // página y se detiene al pasar el cursor o si el usuario prefiere menos
    // movimiento.
    useEffect(() => {
        if (!hasControls || paused) {
            return;
        }

        if (
            typeof window !== 'undefined' &&
            window.matchMedia('(prefers-reduced-motion: reduce)').matches
        ) {
            return;
        }

        const intervalId = window.setInterval(() => {
            setActivePage((current) => (current + 1) % totalPages);
        }, 4000);

        return () => window.clearInterval(intervalId);
    }, [hasControls, paused, totalPages, activePage]);

    // Columnas fijas según el breakpoint (1 / 2 / 4) para que el tamaño de la
    // card no cambie aunque la página tenga menos productos que columnas.
    const gridClassName = 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4';

    return (
        <section className={`mt-14 ${className}`}>
            {showHeading && (title || subtitle || eyebrow) && (
                <div
                    className={
                        compactHeading
                            ? 'mb-7 flex flex-col gap-4 md:flex-row md:items-end md:justify-between'
                            : 'mx-auto mb-9 max-w-3xl text-center'
                    }
                >
                    <div>
                        {eyebrow && (
                            <p className="text-xs font-black tracking-[0.24em] text-red-800 uppercase dark:text-red-300">
                                {eyebrow}
                            </p>
                        )}
                        {title && (
                            <h2
                                className={`mt-2 font-black tracking-normal text-neutral-950 dark:text-neutral-50 ${
                                    compactHeading
                                        ? 'text-2xl md:text-3xl'
                                        : 'text-3xl md:text-4xl'
                                }`}
                            >
                                {title}
                            </h2>
                        )}
                    </div>
                    {subtitle && (
                        <p
                            className={
                                compactHeading
                                    ? 'max-w-xl text-sm leading-6 text-neutral-600 dark:text-neutral-400'
                                    : 'mx-auto mt-4 max-w-2xl text-sm leading-6 text-neutral-600 dark:text-neutral-400'
                            }
                        >
                            {subtitle}
                        </p>
                    )}
                </div>
            )}

            <div
                onMouseEnter={() => setPaused(true)}
                onMouseLeave={() => setPaused(false)}
                onFocusCapture={() => setPaused(true)}
                onBlurCapture={() => setPaused(false)}
                className="rounded-lg border border-neutral-200 bg-white p-4 shadow-sm shadow-neutral-950/5 dark:border-neutral-800 dark:bg-neutral-950 dark:shadow-black/20 sm:p-5 lg:p-6"
            >
                <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                    <div className="flex items-center gap-3">
                        <span className="h-px w-12 bg-red-800 dark:bg-red-300" />
                        <span className="text-xs font-bold tracking-[0.18em] text-neutral-500 uppercase dark:text-neutral-400">
                            Pagina {activePage + 1} de {totalPages}
                        </span>
                    </div>

                    {hasControls && (
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                onClick={() => goToPage(activePage - 1)}
                                aria-label="Pagina anterior"
                                className="flex size-10 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-700 shadow-sm transition hover:border-red-800 hover:bg-red-800 hover:text-white dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:border-red-300 dark:hover:bg-red-300 dark:hover:text-neutral-950"
                            >
                                <ChevronLeft className="size-4" />
                            </button>
                            <button
                                type="button"
                                onClick={() => goToPage(activePage + 1)}
                                aria-label="Pagina siguiente"
                                className="flex size-10 items-center justify-center rounded-full border border-neutral-200 bg-white text-neutral-700 shadow-sm transition hover:border-red-800 hover:bg-red-800 hover:text-white dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-200 dark:hover:border-red-300 dark:hover:bg-red-300 dark:hover:text-neutral-950"
                            >
                                <ChevronRight className="size-4" />
                            </button>
                        </div>
                    )}
                </div>

                <div
                    key={`${itemsPerPage}-${activePage}`}
                    className={`grid gap-5 ${gridClassName}`}
                >
                    {currentProducts.map((product) => (
                        <div
                            key={product.sku}
                            className="min-w-0 animate-in fade-in slide-in-from-right-2 duration-300"
                        >
                            <ProductCard product={product} variant="carousel" />
                        </div>
                    ))}
                </div>

                <div className="mt-6 grid gap-4">
                    <div className="h-1 overflow-hidden rounded-full bg-neutral-100 dark:bg-neutral-800">
                        <div
                            className="h-full rounded-full bg-red-800 transition-all duration-300 dark:bg-red-300"
                            style={{ width: `${progress}%` }}
                        />
                    </div>

                    {hasControls && (
                        <div className="flex items-center justify-center gap-2">
                            {pages.map((page, index) => (
                                <button
                                    key={`${page[0]?.sku ?? 'page'}-${index}`}
                                    type="button"
                                    onClick={() => goToPage(index)}
                                    aria-label={`Ir a pagina ${index + 1}`}
                                    className={`h-2 rounded-full transition-all ${
                                        index === activePage
                                            ? 'w-8 bg-red-800 dark:bg-red-300'
                                            : 'w-2 bg-neutral-300 hover:bg-neutral-400 dark:bg-neutral-700 dark:hover:bg-neutral-600'
                                    }`}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}
