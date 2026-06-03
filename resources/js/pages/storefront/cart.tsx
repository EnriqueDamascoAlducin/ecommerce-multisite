import { Head, Link, router } from '@inertiajs/react';
import { ImageIcon } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { formatPrice, useStoreUrls } from '@/lib/storefront';

type CartItemRow = {
    id: number;
    product_slug: string | null;
    sku: string;
    name: string;
    quantity: number;
    unit_price: string;
    line_total: string;
    thumbnail: string | null;
    in_stock: boolean;
};

type Totals = {
    items_count: number;
    subtotal: string;
    discount: string;
    shipping: string;
    total: string;
};

type ShippingOption = { code: string; label: string; type: string; amount: string };

export default function StorefrontCart({
    items,
    totals,
    shippingOptions,
    selectedShipping,
}: {
    items: CartItemRow[];
    totals: Totals;
    shippingOptions: ShippingOption[];
    selectedShipping: string | null;
}) {
    const urls = useStoreUrls();

    const setQuantity = (item: CartItemRow, quantity: number) => {
        router.patch(`/carrito/${item.id}`, { quantity }, { preserveScroll: true });
    };

    const remove = (item: CartItemRow) => {
        router.delete(`/carrito/${item.id}`, { preserveScroll: true });
    };

    const setShipping = (code: string) => {
        router.post('/carrito/envio', { shipping_method_code: code }, { preserveScroll: true });
    };

    return (
        <>
            <Head title="Carrito" />
            <h1 className="mb-6 text-2xl font-semibold">Tu carrito</h1>

            {items.length === 0 ? (
                <div className="rounded-lg border border-neutral-200 py-16 text-center dark:border-neutral-800">
                    <p className="text-neutral-500">Tu carrito está vacío.</p>
                    <Button className="mt-4" asChild>
                        <Link href={urls.home()}>Seguir comprando</Link>
                    </Button>
                </div>
            ) : (
                <div className="grid gap-8 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <div className="divide-y divide-neutral-100 rounded-lg border border-neutral-200 dark:divide-neutral-800 dark:border-neutral-800">
                            {items.map((item) => (
                                <div key={item.id} className="flex gap-4 p-4">
                                    <div className="flex size-20 shrink-0 items-center justify-center overflow-hidden rounded bg-neutral-100 dark:bg-neutral-900">
                                        {item.thumbnail ? (
                                            <img src={item.thumbnail} alt={item.name} className="h-full w-full object-cover" />
                                        ) : (
                                            <ImageIcon className="size-6 text-neutral-400" />
                                        )}
                                    </div>
                                    <div className="flex flex-1 flex-col">
                                        <div className="flex items-start justify-between gap-2">
                                            <div>
                                                {item.product_slug ? (
                                                    <Link href={urls.product(item.product_slug)} className="font-medium hover:underline">
                                                        {item.name}
                                                    </Link>
                                                ) : (
                                                    <span className="font-medium">{item.name}</span>
                                                )}
                                                <p className="font-mono text-xs text-neutral-400">{item.sku}</p>
                                                {!item.in_stock && <Badge variant="outline" className="mt-1">Sin stock suficiente</Badge>}
                                            </div>
                                            <span className="font-semibold">{formatPrice(item.line_total)}</span>
                                        </div>
                                        <div className="mt-auto flex items-center gap-3 pt-2">
                                            <div className="flex items-center rounded-md border border-neutral-300 dark:border-neutral-700">
                                                <button type="button" onClick={() => setQuantity(item, item.quantity - 1)} className="px-2 py-1 text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100">−</button>
                                                <span className="w-8 text-center text-sm">{item.quantity}</span>
                                                <button type="button" onClick={() => setQuantity(item, item.quantity + 1)} className="px-2 py-1 text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100">+</button>
                                            </div>
                                            <span className="text-xs text-neutral-400">{formatPrice(item.unit_price)} c/u</span>
                                            <button type="button" onClick={() => remove(item)} className="ml-auto text-sm text-red-600 hover:underline">
                                                Eliminar
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <aside className="h-fit rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                        <h2 className="mb-4 text-lg font-medium">Resumen</h2>

                        {shippingOptions.length > 0 && (
                            <div className="mb-4 border-b border-neutral-100 pb-4 dark:border-neutral-800">
                                <p className="mb-2 text-sm font-medium">Envío</p>
                                <div className="space-y-1.5">
                                    {shippingOptions.map((option) => (
                                        <label key={option.code} className="flex items-center justify-between gap-2 text-sm">
                                            <span className="flex items-center gap-2">
                                                <input
                                                    type="radio"
                                                    name="shipping"
                                                    checked={selectedShipping === option.code}
                                                    onChange={() => setShipping(option.code)}
                                                    className="size-4"
                                                />
                                                {option.label}
                                            </span>
                                            <span className="text-neutral-500">
                                                {Number(option.amount) === 0 ? 'Gratis' : formatPrice(option.amount)}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        <dl className="space-y-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Subtotal ({totals.items_count})</dt>
                                <dd>{formatPrice(totals.subtotal)}</dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Envío</dt>
                                <dd>{Number(totals.shipping) === 0 ? '—' : formatPrice(totals.shipping)}</dd>
                            </div>
                            <div className="flex justify-between border-t border-neutral-100 pt-2 text-base font-semibold dark:border-neutral-800">
                                <dt>Total</dt>
                                <dd>{formatPrice(totals.total)}</dd>
                            </div>
                        </dl>
                        <Button className="mt-4 w-full" asChild>
                            <Link href="/checkout">Finalizar compra</Link>
                        </Button>
                    </aside>
                </div>
            )}
        </>
    );
}
