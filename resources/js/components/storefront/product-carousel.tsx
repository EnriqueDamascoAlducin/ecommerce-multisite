import { ArrowLeft, ArrowRight } from 'lucide-react';
import { useCallback, useRef } from 'react';
import { ProductCard, type ProductCardData } from '@/pages/storefront/product-card';
import { Button } from '@/components/ui/button';

export function ProductCarousel({ products }: { products: ProductCardData[] }) {
    const scrollRef = useRef<HTMLDivElement>(null);

    const scroll = useCallback((direction: 'left' | 'right') => {
        const container = scrollRef.current;
        if (!container) return;

        const scrollAmount = container.clientWidth * 0.75;
        container.scrollBy({
            left: direction === 'left' ? -scrollAmount : scrollAmount,
            behavior: 'smooth',
        });
    }, []);

    return (
        <div className="relative">
            <button
                type="button"
                onClick={() => scroll('left')}
                className="absolute -left-3 top-1/2 z-10 hidden -translate-y-1/2 items-center justify-center rounded-full bg-white shadow-md ring-1 ring-neutral-200 transition-colors hover:bg-neutral-50 md:flex size-10"
                aria-label="Anterior"
            >
                <ArrowLeft className="size-5 text-neutral-600" />
            </button>

            <button
                type="button"
                onClick={() => scroll('right')}
                className="absolute -right-3 top-1/2 z-10 hidden -translate-y-1/2 items-center justify-center rounded-full bg-white shadow-md ring-1 ring-neutral-200 transition-colors hover:bg-neutral-50 md:flex size-10"
                aria-label="Siguiente"
            >
                <ArrowRight className="size-5 text-neutral-600" />
            </button>

            <div
                ref={scrollRef}
                className="flex gap-4 overflow-x-auto scroll-smooth snap-x snap-mandatory pb-2 [&::-webkit-scrollbar]:hidden"
            >
                {products.map((product) => (
                    <div
                        key={product.sku}
                        className="min-w-[220px] max-w-[260px] flex-1 snap-start"
                    >
                        <ProductCard product={product} />
                    </div>
                ))}
            </div>
        </div>
    );
}
