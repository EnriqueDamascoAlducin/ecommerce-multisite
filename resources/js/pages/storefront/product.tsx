import { Form, Head, Link } from '@inertiajs/react';
import { ImageIcon } from 'lucide-react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPrice, type Price, useStoreUrls } from '@/lib/storefront';

type ProductDetail = {
    id: number;
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
};

export default function StorefrontProduct({ product }: { product: ProductDetail }) {
    const urls = useStoreUrls();
    const [activeImage, setActiveImage] = useState(0);

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
                        {product.gallery.length > 0 ? (
                            <img src={product.gallery[activeImage]?.url} alt={product.gallery[activeImage]?.alt} className="h-full w-full object-cover" />
                        ) : (
                            <ImageIcon className="size-16 text-neutral-300" />
                        )}
                    </div>
                    {product.gallery.length > 1 && (
                        <div className="mt-3 flex gap-2">
                            {product.gallery.map((image, index) => (
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
                        {product.price.is_special && product.price.special_price ? (
                            <>
                                <span className="text-2xl font-bold text-red-600">{formatPrice(product.price.special_price)}</span>
                                <span className="text-neutral-400 line-through">{formatPrice(product.price.price)}</span>
                            </>
                        ) : (
                            <span className="text-2xl font-bold">{formatPrice(product.price.effective_price)}</span>
                        )}
                    </div>

                    <div className="mt-3">
                        {product.in_stock ? (
                            <Badge>En stock</Badge>
                        ) : (
                            <Badge variant="outline">Agotado</Badge>
                        )}
                    </div>

                    {product.short_description && (
                        <p className="mt-4 text-neutral-600 dark:text-neutral-400">{product.short_description}</p>
                    )}

                    {product.in_stock && (
                        <Form action="/carrito" method="post" className="mt-6 flex items-end gap-3">
                            {({ processing }) => (
                                <>
                                    <input type="hidden" name="product_id" value={product.id} />
                                    <div className="grid gap-1">
                                        <label htmlFor="quantity" className="text-xs text-neutral-500">Cantidad</label>
                                        <Input id="quantity" name="quantity" type="number" min={1} max={999} defaultValue={1} className="w-24" />
                                    </div>
                                    <Button disabled={processing}>Agregar al carrito</Button>
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
