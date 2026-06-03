import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPrice } from '@/lib/storefront';

type Item = { name: string; sku: string; quantity: number; unit_price: string; line_total: string };
type Totals = { items_count: number; subtotal: string; discount: string; shipping: string; total: string };
type ShippingOption = { code: string; label: string; type: string; amount: string };
type PaymentMethod = { code: string; label: string };
type Address = {
    first_name: string; last_name: string; company: string | null; phone: string | null;
    line1: string; line2: string | null; city: string; state: string; postal_code: string; country: string;
    is_default_shipping?: boolean;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function Checkout({
    items,
    totals,
    shippingOptions,
    selectedShipping,
    paymentMethods,
    customer,
    addresses,
}: {
    items: Item[];
    totals: Totals;
    shippingOptions: ShippingOption[];
    selectedShipping: string | null;
    paymentMethods: PaymentMethod[];
    customer: { name: string; email: string } | null;
    addresses: Address[];
}) {
    const [billingSame, setBillingSame] = useState(true);
    const prefill = addresses.find((a) => a.is_default_shipping) ?? addresses[0] ?? null;

    return (
        <>
            <Head title="Checkout" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Checkout</h1>
                <Button variant="outline" asChild>
                    <Link href="/carrito">Volver al carrito</Link>
                </Button>
            </div>

            <Form action="/checkout" method="post" className="grid gap-8 lg:grid-cols-3">
                {({ processing, errors }) => (
                    <>
                        <div className="space-y-6 lg:col-span-2">
                            <section>
                                <h2 className="mb-3 text-lg font-medium">Contacto</h2>
                                <div className="grid gap-2">
                                    <Label htmlFor="email">Correo</Label>
                                    <Input id="email" name="email" type="email" defaultValue={customer?.email ?? ''} required />
                                    <InputError message={errors.email} />
                                </div>
                            </section>

                            <section>
                                <h2 className="mb-3 text-lg font-medium">Dirección de envío</h2>
                                <AddressFields prefix="shipping" prefill={prefill} errors={errors} />
                            </section>

                            <section>
                                <h2 className="mb-3 text-lg font-medium">Envío</h2>
                                {shippingOptions.length === 0 ? (
                                    <p className="text-sm text-neutral-500">No hay métodos de envío disponibles.</p>
                                ) : (
                                    <div className="space-y-2">
                                        {shippingOptions.map((option) => (
                                            <label key={option.code} className="flex items-center justify-between gap-2 rounded-md border border-neutral-200 p-3 text-sm dark:border-neutral-800">
                                                <span className="flex items-center gap-2">
                                                    <input type="radio" name="shipping_method_code" value={option.code} defaultChecked={selectedShipping === option.code} className="size-4" />
                                                    {option.label}
                                                </span>
                                                <span>{Number(option.amount) === 0 ? 'Gratis' : formatPrice(option.amount)}</span>
                                            </label>
                                        ))}
                                    </div>
                                )}
                            </section>

                            <section>
                                <h2 className="mb-3 text-lg font-medium">Facturación</h2>
                                <label className="mb-3 flex items-center gap-2 text-sm">
                                    <input type="hidden" name="billing_same" value={billingSame ? '1' : '0'} />
                                    <input type="checkbox" checked={billingSame} onChange={(e) => setBillingSame(e.target.checked)} className="size-4 rounded" />
                                    Igual que la dirección de envío
                                </label>
                                {!billingSame && <AddressFields prefix="billing" prefill={null} errors={errors} />}
                            </section>

                            <section>
                                <h2 className="mb-3 text-lg font-medium">Pago</h2>
                                <div className="space-y-2">
                                    {paymentMethods.map((method, index) => (
                                        <label key={method.code} className="flex items-center gap-2 rounded-md border border-neutral-200 p-3 text-sm dark:border-neutral-800">
                                            <input type="radio" name="payment_method" value={method.code} defaultChecked={index === 0} className="size-4" />
                                            {method.label}
                                        </label>
                                    ))}
                                </div>
                                <InputError message={errors.payment_method} />
                            </section>
                        </div>

                        <aside className="h-fit rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                            <h2 className="mb-4 text-lg font-medium">Tu pedido</h2>
                            <ul className="mb-4 space-y-2 text-sm">
                                {items.map((item) => (
                                    <li key={item.sku} className="flex justify-between gap-2">
                                        <span className="text-neutral-500">{item.quantity} × {item.name}</span>
                                        <span>{formatPrice(item.line_total)}</span>
                                    </li>
                                ))}
                            </ul>
                            <dl className="space-y-2 border-t border-neutral-100 pt-3 text-sm dark:border-neutral-800">
                                <div className="flex justify-between"><dt className="text-neutral-500">Subtotal</dt><dd>{formatPrice(totals.subtotal)}</dd></div>
                                <div className="flex justify-between"><dt className="text-neutral-500">Envío</dt><dd>{Number(totals.shipping) === 0 ? '—' : formatPrice(totals.shipping)}</dd></div>
                                <div className="flex justify-between border-t border-neutral-100 pt-2 text-base font-semibold dark:border-neutral-800"><dt>Total</dt><dd>{formatPrice(totals.total)}</dd></div>
                            </dl>
                            <Button className="mt-4 w-full" disabled={processing}>Realizar pedido</Button>
                        </aside>
                    </>
                )}
            </Form>
        </>
    );
}

function AddressFields({
    prefix,
    prefill,
    errors,
}: {
    prefix: string;
    prefill: Address | null;
    errors: Record<string, string>;
}) {
    const field = (name: keyof Address, label: string, required = false) => (
        <div className="grid gap-1.5">
            <Label htmlFor={`${prefix}-${name}`}>{label}</Label>
            <Input
                id={`${prefix}-${name}`}
                name={`${prefix}[${name}]`}
                defaultValue={(prefill?.[name] as string) ?? (name === 'country' ? 'MX' : '')}
                required={required}
                className={fieldClass}
            />
            <InputError message={errors[`${prefix}.${name}`]} />
        </div>
    );

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            {field('first_name', 'Nombre', true)}
            {field('last_name', 'Apellidos', true)}
            {field('company', 'Empresa')}
            {field('phone', 'Teléfono')}
            {field('line1', 'Calle y número', true)}
            {field('line2', 'Interior / referencia')}
            {field('city', 'Ciudad', true)}
            {field('state', 'Estado', true)}
            {field('postal_code', 'Código postal', true)}
            {field('country', 'País (ISO2)', true)}
        </div>
    );
}
