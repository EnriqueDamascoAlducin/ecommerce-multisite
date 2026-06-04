import { Form, Head, Link } from '@inertiajs/react';
import { ImageIcon } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPrice, type Price, useStoreUrls } from '@/lib/storefront';

type VariantInfo = {
    id: number;
    sku: string;
    price: number;
    special_price: number | null;
    is_special: boolean;
    options: Record<string, string>;
    in_stock: boolean;
    gallery: { url: string; alt: string }[];
};

type ConfigurableOption = {
    attribute: { id: number; code: string; name: string };
    options: { label: string; value: string }[];
};

type ProductDetail = {
    id: number;
    type: string;
    sku: string;
    name: string;
    slug: string;
    short_description: string | null;
    description: string | null;
    price: Price;
    in_stock: boolean;
    gallery: { url: string; alt: string }[];
    attributes: { name: string; value: string }[];
    categories: { name: string; slug: string }[];
    configurable_options?: ConfigurableOption[];
    variants?: VariantInfo[];
    bundle_items?: { name: string; sku: string; quantity: number }[];
};

export default function StorefrontProduct({ product }: { product: ProductDetail }) {
    const urls = useStoreUrls();
    const [activeImage, setActiveImage] = useState(0);
    const [selectedOptions, setSelectedOptions] = useState<Record<string, string>>({});

    const currentVariant = useMemo(() => {
        if (!product.variants || product.variants.length === 0) return null;

        const selectedKeys = Object.keys(selectedOptions);
        if (selectedKeys.length === 0) return null;

        const allSelected = product.configurable_options?.every(
            (opt) => selectedOptions[opt.attribute.code],
        );

        if (!allSelected) return null;

        return product.variants.find((v) =>
            Object.entries(selectedOptions).every(
                ([code, value]) => v.options[code] === value,
            ),
        ) ?? null;
    }, [selectedOptions, product.variants, product.configurable_options]);

    const displayPrice = currentVariant
        ? {
              price: currentVariant.price,
              special_price: currentVariant.special_price,
              effective_price: currentVariant.is_special ? currentVariant.special_price! : currentVariant.price,
              is_special: currentVariant.is_special,
          }
        : product.price;

    const inStock = currentVariant ? currentVariant.in_stock : product.in_stock;
    const currentGallery = currentVariant && currentVariant.gallery.length > 0
        ? currentVariant.gallery
        : product.gallery;

    const selectOption = (code: string, value: string) => {
        setSelectedOptions((prev) => ({ ...prev, [code]: value }));
        setActiveImage(0);
    };

    const addToCartProductId = currentVariant ? currentVariant.id : product.id;

    return (
        <>
            <Head title={product.name} />

            {product.categories.length > 0 && (
                <nav className="mb-4 flex flex-wrap gap-2 text-sm text-neutral-500">
                    {product.categories.map((category) => (
                        <Link key={category.slug} href={urls.category(category.slug)} className="hover:text-neutral-900 dark:hover:text-neutral-100">
                            {category.name}
                        </Link>
                    ))}
                </nav>
            )}

            <div className="grid gap-8 md:grid-cols-2">
                {/* Galería */}
                <div>
                    <div className="flex aspect-square items-center justify-center overflow-hidden rounded-lg bg-neutral-100 dark:bg-neutral-900">
                        {currentGallery.length > 0 ? (
                            <img src={currentGallery[activeImage]?.url} alt={currentGallery[activeImage]?.alt} className="h-full w-full object-cover" />
                        ) : (
                            <ImageIcon className="size-16 text-neutral-300" />
                        )}
                    </div>
                    {currentGallery.length > 1 && (
                        <div className="mt-3 flex gap-2">
                            {currentGallery.map((image, index) => (
                                <button
                                    key={index}
                                    type="button"
                                    onClick={() => setActiveImage(index)}
                                    className={`aspect-square w-16 overflow-hidden rounded border-2 ${index === activeImage ? 'border-neutral-900 dark:border-neutral-100' : 'border-transparent'}`}
                                >
                                    <img src={image.url} alt={image.alt} className="h-full w-full object-cover" />
                                </button>
                            ))}
                        </div>
                    )}
                </div>

                {/* Información */}
                <div>
                    <h1 className="text-2xl font-semibold">{product.name}</h1>
                    <p className="mt-1 font-mono text-xs text-neutral-400">{product.sku}</p>

                    <div className="mt-4 flex items-center gap-3">
                        {displayPrice.is_special && displayPrice.special_price ? (
                            <>
                                <span className="text-2xl font-bold text-red-600">{formatPrice(displayPrice.special_price)}</span>
                                <span className="text-neutral-400 line-through">{formatPrice(displayPrice.price)}</span>
                            </>
                        ) : (
                            <span className="text-2xl font-bold">{formatPrice(displayPrice.effective_price)}</span>
                        )}
                    </div>

                    <div className="mt-3">
                        {inStock ? (
                            <Badge>En stock</Badge>
                        ) : (
                            <Badge variant="outline">Agotado</Badge>
                        )}
                    </div>

                    {product.short_description && (
                        <p className="mt-4 text-neutral-600 dark:text-neutral-400">{product.short_description}</p>
                    )}

                    {/* Contenido del paquete (bundle) */}
                    {product.bundle_items && product.bundle_items.length > 0 && (
                        <div className="mt-6">
                            <h2 className="mb-2 text-sm font-semibold">Este paquete incluye</h2>
                            <ul className="divide-y divide-neutral-100 rounded-lg border border-neutral-100 text-sm dark:divide-neutral-800 dark:border-neutral-800">
                                {product.bundle_items.map((item) => (
                                    <li key={item.sku} className="flex justify-between px-3 py-2">
                                        <span>{item.name}</span>
                                        <span className="text-neutral-500">× {item.quantity}</span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {/* Selectores de variante */}
                    {product.configurable_options && product.configurable_options.length > 0 && (
                        <div className="mt-6 space-y-4">
                            {product.configurable_options.map((opt) => (
                                <div key={opt.attribute.code}>
                                    <label className="mb-1 block text-sm font-medium">{opt.attribute.name}</label>
                                    <div className="flex flex-wrap gap-2">
                                        {opt.options.map((option) => {
                                            const isSelected = selectedOptions[opt.attribute.code] === option.value;
                                            return (
                                                <button
                                                    key={option.value}
                                                    type="button"
                                                    onClick={() => selectOption(opt.attribute.code, option.value)}
                                                    className={`rounded-md border px-3 py-1.5 text-sm transition-colors ${
                                                        isSelected
                                                            ? 'border-neutral-900 bg-neutral-900 text-white dark:border-neutral-100 dark:bg-neutral-100 dark:text-neutral-900'
                                                            : 'border-neutral-300 hover:border-neutral-500 dark:border-neutral-700 dark:hover:border-neutral-500'
                                                    }`}
                                                >
                                                    {option.label}
                                                </button>
                                            );
                                        })}
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {inStock && (
                        <Form action="/carrito" method="post" className="mt-6 flex items-end gap-3">
                            {({ processing }) => (
                                <>
                                    <input type="hidden" name="product_id" value={addToCartProductId} />
                                    <div className="grid gap-1">
                                        <label htmlFor="quantity" className="text-xs text-neutral-500">Cantidad</label>
                                        <Input id="quantity" name="quantity" type="number" min={1} max={999} defaultValue={1} className="w-24" />
                                    </div>
                                    <Button disabled={processing || (!currentVariant && !!product.configurable_options?.length)}>
                                        Agregar al carrito
                                    </Button>
                                </>
                            )}
                        </Form>
                    )}

                    {product.attributes.length > 0 && (
                        <dl className="mt-6 divide-y divide-neutral-100 border-t border-neutral-100 text-sm dark:divide-neutral-800 dark:border-neutral-800">
                            {product.attributes.map((attribute) => (
                                <div key={attribute.name} className="flex justify-between py-2">
                                    <dt className="text-neutral-500">{attribute.name}</dt>
                                    <dd className="font-medium">{attribute.value}</dd>
                                </div>
                            ))}
                        </dl>
                    )}
                </div>
            </div>

            {product.description && (
                <section className="mt-10">
                    <h2 className="mb-2 text-lg font-semibold">Descripción</h2>
                    <p className="whitespace-pre-line text-neutral-600 dark:text-neutral-400">{product.description}</p>
                </section>
            )}
        </>
    );
}
