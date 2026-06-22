import { Head, Link, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatPrice } from '@/lib/storefront';
import postalCodeLookup from '@/routes/checkout/postal-code';
import { ChevronRight } from 'lucide-react';

type Item = { name: string; sku: string; quantity: number; unit_price: string; line_total: string };
type Totals = { items_count: number; subtotal: string; discount: string; shipping: string; total: string };
type ShippingOption = { code: string; label: string; type: string; amount: string };
type PaymentMethod = { code: string; label: string };
type Address = {
    id?: number;
    label?: string;
    first_name: string; last_name: string; company: string | null; phone: string | null;
    line1: string; line2: string | null; neighborhood: string | null; city: string; state: string; postal_code: string; country: string;
    is_default_shipping?: boolean;
};

type AddressFields = Record<string, string>;
type PostalSettlement = { name: string; type: string | null; city: string; state: string; zone: string | null };
type PostalLookup = { loading: boolean; settlements: PostalSettlement[]; error: string | null };

type CheckoutForm = {
    email: string;
    payment_method: string;
    shipping_method_code: string;
    billing_same: number;
    shipping: AddressFields;
    billing: AddressFields;
};

const STEPS = ['Datos de entrega', 'Pago y revisión'];

function emptyAddress(): AddressFields {
    return { first_name: '', last_name: '', company: '', phone: '', line1: '', line2: '', neighborhood: '', city: '', state: '', postal_code: '', country: 'MX' };
}

function addressToFields(addr: Address): AddressFields {
    return {
        first_name: addr.first_name ?? '',
        last_name: addr.last_name ?? '',
        company: addr.company ?? '',
        phone: addr.phone ?? '',
        line1: addr.line1 ?? '',
        line2: addr.line2 ?? '',
        neighborhood: addr.neighborhood ?? '',
        city: addr.city ?? '',
        state: addr.state ?? '',
        postal_code: addr.postal_code ?? '',
        country: 'MX',
    };
}

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
    const [step, setStep] = useState(0);
    const [billingSame, setBillingSame] = useState(true);
    const [shippingPostalLookup, setShippingPostalLookup] = useState<PostalLookup>({
        loading: false,
        settlements: [],
        error: null,
    });
    const [billingPostalLookup, setBillingPostalLookup] = useState<PostalLookup>({
        loading: false,
        settlements: [],
        error: null,
    });

    const defaultAddr = addresses.find((a) => a.is_default_shipping) ?? addresses[0] ?? null;
    const [selectedAddressId, setSelectedAddressId] = useState<number | null>(defaultAddr?.id ?? null);
    const [useNewAddress, setUseNewAddress] = useState(defaultAddr === null);

    const { data, setData, post, processing, errors } = useForm<CheckoutForm>({
        email: customer?.email ?? '',
        payment_method: paymentMethods[0]?.code ?? '',
        shipping_method_code: selectedShipping ?? (shippingOptions[0]?.code ?? ''),
        billing_same: 1,
        shipping: defaultAddr ? addressToFields(defaultAddr) : emptyAddress(),
        billing: {},
    });
    const checkoutDataRef = useRef(data);

    checkoutDataRef.current = data;

    useEffect(() => {
        return lookupPostalCode('shipping', data.shipping.postal_code, setShippingPostalLookup);
    }, [data.shipping.postal_code]);

    useEffect(() => {
        if (!billingSame) {
            return lookupPostalCode('billing', data.billing.postal_code ?? '', setBillingPostalLookup);
        }

        setBillingPostalLookup({ loading: false, settlements: [], error: null });
    }, [billingSame, data.billing.postal_code]);

    function lookupPostalCode(
        prefix: 'shipping' | 'billing',
        postalCode: string,
        setLookup: React.Dispatch<React.SetStateAction<PostalLookup>>,
    ): void | (() => void) {
        const normalizedPostalCode = postalCode.trim();

        if (!/^\d{5}$/.test(normalizedPostalCode)) {
            setLookup({ loading: false, settlements: [], error: null });

            return;
        }

        const controller = new AbortController();

        setLookup((lookup) => ({ ...lookup, loading: true, error: null }));

        fetch(postalCodeLookup.show(normalizedPostalCode).url, {
            headers: { Accept: 'application/json' },
            signal: controller.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error('Código postal no encontrado');
                }

                return response.json() as Promise<{
                    state: string;
                    city: string;
                    settlements: PostalSettlement[];
                }>;
            })
            .then((payload) => {
                const current = checkoutDataRef.current[prefix];
                const selectedNeighborhood =
                    payload.settlements.length === 1
                        ? payload.settlements[0].name
                        : payload.settlements.some((settlement) => settlement.name === current.neighborhood)
                          ? current.neighborhood
                          : '';

                setData(prefix, {
                    ...current,
                    neighborhood: selectedNeighborhood,
                    city: payload.city,
                    state: payload.state,
                    country: 'MX',
                });
                setLookup({
                    loading: false,
                    settlements: payload.settlements,
                    error: null,
                });
            })
            .catch((error) => {
                if (error.name === 'AbortError') {
                    return;
                }

                setLookup({
                    loading: false,
                    settlements: [],
                    error: 'No encontramos colonias para este código postal.',
                });
            });

        return () => controller.abort();
    }

    function selectAddress(addr: Address) {
        setSelectedAddressId(addr.id ?? null);
        setUseNewAddress(false);
        setData('shipping', addressToFields(addr));
    }

    function selectNewAddress() {
        setSelectedAddressId(null);
        setUseNewAddress(true);
        setData('shipping', emptyAddress());
    }

    function updateShippingField(name: string, value: string) {
        setData('shipping', { ...data.shipping, [name]: value });
    }

    function updateBillingField(name: string, value: string) {
        setData('billing', { ...data.billing, [name]: value });
    }

    function handleBillingToggle(checked: boolean) {
        setBillingSame(checked);
        setData('billing_same', checked ? 1 : 0);
        if (!checked) {
            setData('billing', { ...data.shipping });
        }
    }

    function field(
        prefix: 'shipping' | 'billing',
        name: string,
        label: string,
        required = false,
        disabled = false,
    ) {
        const formData = data[prefix];
        const value = formData[name] ?? '';
        const onChange = prefix === 'shipping'
            ? (e: React.ChangeEvent<HTMLInputElement>) => updateShippingField(name, e.target.value)
            : (e: React.ChangeEvent<HTMLInputElement>) => updateBillingField(name, e.target.value);

        return (
            <div className="grid gap-1.5">
                <Label htmlFor={`${prefix}-${name}`}>{label}</Label>
                <Input
                    id={`${prefix}-${name}`}
                    value={value}
                    onChange={onChange}
                    required={required}
                    disabled={disabled}
                    className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                />
                <InputError message={errors[`${prefix}.${name}` as keyof typeof errors]} />
            </div>
        );
    }

    function neighborhoodField(prefix: 'shipping' | 'billing', lookup: PostalLookup) {
        const value = data[prefix].neighborhood ?? '';
        const error = errors[`${prefix}.neighborhood` as keyof typeof errors];

        if (lookup.settlements.length <= 1) {
            return (
                <div className="grid gap-1.5">
                    <Label htmlFor={`${prefix}-neighborhood`}>Colonia</Label>
                    <Input
                        id={`${prefix}-neighborhood`}
                        value={value}
                        onChange={(event) => setData(prefix, { ...data[prefix], neighborhood: event.target.value })}
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    />
                    {lookup.loading && <p className="text-xs text-neutral-500">Buscando colonias...</p>}
                    {lookup.error && <p className="text-xs text-amber-600">{lookup.error}</p>}
                    <InputError message={error} />
                </div>
            );
        }

        return (
            <div className="grid gap-1.5">
                <Label htmlFor={`${prefix}-neighborhood`}>Colonia</Label>
                <select
                    id={`${prefix}-neighborhood`}
                    value={value}
                    onChange={(event) => setData(prefix, { ...data[prefix], neighborhood: event.target.value })}
                    className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Selecciona una colonia</option>
                    {lookup.settlements.map((settlement) => (
                        <option key={settlement.name} value={settlement.name}>
                            {settlement.name}
                        </option>
                    ))}
                </select>
                <InputError message={error} />
            </div>
        );
    }

    function canAdvance(): boolean {
        if (step === 0) {
            const s = data.shipping;

            return Boolean(
                data.email.trim().length > 0 &&
                    s.first_name &&
                    s.last_name &&
                    s.line1 &&
                    s.city &&
                    s.state &&
                    s.postal_code &&
                    s.country &&
                    (shippingOptions.length === 0 || data.shipping_method_code),
            );
        }

        return true;
    }

    function handleSubmit() {
        post('/checkout');
    }

    return (
        <>
            <Head title="Checkout" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Checkout</h1>
                <Button variant="outline" asChild>
                    <Link href="/carrito">Volver al carrito</Link>
                </Button>
            </div>

            {/* Progress indicator */}
            <div className="mb-8">
                <ol className="flex items-center gap-1 text-sm">
                    {STEPS.map((label, i) => (
                        <li key={label} className="flex items-center gap-1">
                            <span
                                className={`flex size-6 items-center justify-center rounded-full text-xs font-medium ${
                                    i < step
                                        ? 'bg-neutral-900 text-white dark:bg-white dark:text-neutral-900'
                                        : i === step
                                          ? 'border-2 border-neutral-900 dark:border-white text-neutral-900 dark:text-white'
                                          : 'border-2 border-neutral-300 text-neutral-400 dark:border-neutral-600'
                                }`}
                            >
                                {i < step ? '✓' : i + 1}
                            </span>
                            <span className={`hidden sm:inline ${i <= step ? 'text-neutral-900 dark:text-white' : 'text-neutral-400'}`}>
                                {label}
                            </span>
                            {i < STEPS.length - 1 && <ChevronRight className="size-3.5 text-neutral-300 dark:text-neutral-600" />}
                        </li>
                    ))}
                </ol>
            </div>

            {/* Step 0 — Datos de entrega */}
            {step === 0 && (
                <div className="space-y-8">
                    <section className="max-w-lg">
                        <h2 className="mb-3 text-lg font-medium">Información de contacto</h2>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Correo electrónico</Label>
                            <Input
                                id="email"
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                required
                                className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            />
                            <InputError message={errors.email} />
                        </div>
                        {customer && (
                            <p className="mt-2 text-sm text-neutral-500">
                                Conectado como <strong>{customer.name}</strong>
                            </p>
                        )}
                    </section>

                    <section>
                        <h2 className="mb-3 text-lg font-medium">Dirección de envío</h2>

                        {addresses.length > 0 && (
                            <div className="mb-4">
                                <p className="mb-2 text-sm text-neutral-500">Seleccionar una dirección guardada</p>
                                <div className="grid gap-2 sm:grid-cols-2">
                                    {addresses.map((addr) => (
                                        <button
                                            key={addr.id}
                                            type="button"
                                            onClick={() => selectAddress(addr)}
                                            className={`rounded-md border p-3 text-left text-sm transition-colors ${
                                                selectedAddressId === addr.id && !useNewAddress
                                                    ? 'border-neutral-900 bg-neutral-50 dark:border-white dark:bg-neutral-800'
                                                    : 'border-neutral-200 dark:border-neutral-700'
                                            }`}
                                        >
                                            <div className="font-medium">{addr.first_name} {addr.last_name}</div>
                                            <div className="mt-1 text-neutral-500">{addr.line1}</div>
                                            <div className="text-neutral-500">
                                                {addr.city}, {addr.state} {addr.postal_code}
                                            </div>
                                        </button>
                                    ))}
                                    <button
                                        type="button"
                                        onClick={selectNewAddress}
                                        className={`rounded-md border border-dashed p-3 text-sm transition-colors ${
                                            useNewAddress
                                                ? 'border-neutral-900 bg-neutral-50 dark:border-white dark:bg-neutral-800'
                                                : 'border-neutral-300 dark:border-neutral-600'
                                        }`}
                                    >
                                        + Nueva dirección
                                    </button>
                                </div>
                            </div>
                        )}

                        <div className="grid gap-3 sm:grid-cols-2">
                            {field('shipping', 'first_name', 'Nombre', true)}
                            {field('shipping', 'last_name', 'Apellidos', true)}
                            {field('shipping', 'company', 'Empresa')}
                            {field('shipping', 'phone', 'Teléfono')}
                            {field('shipping', 'line1', 'Calle y número', true)}
                            {field('shipping', 'line2', 'Interior / referencia')}
                            {field('shipping', 'postal_code', 'Código postal', true)}
                            {neighborhoodField('shipping', shippingPostalLookup)}
                            {field('shipping', 'city', 'Ciudad', true)}
                            {field('shipping', 'state', 'Estado', true)}
                            {field('shipping', 'country', 'País', true, true)}
                        </div>
                    </section>

                    <section>
                        <h2 className="mb-3 text-lg font-medium">Método de envío</h2>
                        {shippingOptions.length === 0 ? (
                            <p className="text-sm text-neutral-500">No hay métodos de envío disponibles.</p>
                        ) : shippingOptions.length === 1 ? (
                            <div className="flex items-center justify-between gap-3 rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                                <div>
                                    <p className="font-medium">{shippingOptions[0].label}</p>
                                    <p className="text-neutral-500">Método seleccionado automáticamente.</p>
                                </div>
                                <span className="font-medium">
                                    {Number(shippingOptions[0].amount) === 0 ? 'Gratis' : formatPrice(shippingOptions[0].amount)}
                                </span>
                            </div>
                        ) : (
                            <div className="space-y-2">
                                {shippingOptions.map((option) => (
                                    <label
                                        key={option.code}
                                        className="flex cursor-pointer items-center justify-between gap-2 rounded-md border border-neutral-200 p-3 text-sm dark:border-neutral-800"
                                    >
                                        <span className="flex items-center gap-2">
                                            <input
                                                type="radio"
                                                name="shipping_method_code"
                                                value={option.code}
                                                checked={data.shipping_method_code === option.code}
                                                onChange={(e) => setData('shipping_method_code', e.target.value)}
                                                className="size-4"
                                            />
                                            {option.label}
                                        </span>
                                        <span>{Number(option.amount) === 0 ? 'Gratis' : formatPrice(option.amount)}</span>
                                    </label>
                                ))}
                            </div>
                        )}
                    </section>
                </div>
            )}

            {/* Step 1 — Pago y revisión */}
            {step === 1 && (
                <div className="grid gap-8 lg:grid-cols-3">
                    <div className="space-y-6 lg:col-span-2">
                        <section>
                            <h2 className="mb-3 text-lg font-medium">Pago</h2>
                            <div className="space-y-2">
                                {paymentMethods.map((method) => (
                                    <label
                                        key={method.code}
                                        className="flex cursor-pointer items-center gap-2 rounded-md border border-neutral-200 p-3 text-sm dark:border-neutral-800"
                                    >
                                        <input
                                            type="radio"
                                            name="payment_method"
                                            value={method.code}
                                            checked={data.payment_method === method.code}
                                            onChange={(e) => setData('payment_method', e.target.value)}
                                            className="size-4"
                                        />
                                        {method.label}
                                    </label>
                                ))}
                            </div>
                            <InputError message={errors.payment_method} />
                        </section>

                        <section>
                            <h2 className="mb-3 text-lg font-medium">Facturación</h2>
                            <label className="mb-3 flex cursor-pointer items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={billingSame}
                                    onChange={(e) => handleBillingToggle(e.target.checked)}
                                    className="size-4 rounded"
                                />
                                Igual que la dirección de envío
                            </label>
                            {!billingSame && (
                                <div className="grid gap-3 sm:grid-cols-2">
                                    {field('billing', 'first_name', 'Nombre', true)}
                                    {field('billing', 'last_name', 'Apellidos', true)}
                                    {field('billing', 'company', 'Empresa')}
                                    {field('billing', 'phone', 'Teléfono')}
                                    {field('billing', 'line1', 'Calle y número', true)}
                                    {field('billing', 'line2', 'Interior / referencia')}
                                    {field('billing', 'postal_code', 'Código postal', true)}
                                    {neighborhoodField('billing', billingPostalLookup)}
                                    {field('billing', 'city', 'Ciudad', true)}
                                    {field('billing', 'state', 'Estado', true)}
                                    {field('billing', 'country', 'País', true, true)}
                                </div>
                            )}
                        </section>

                        <section>
                            <h2 className="mb-3 text-lg font-medium">Resumen de envío</h2>
                            <div className="rounded-md border border-neutral-200 bg-neutral-50 p-3 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                                <p className="font-medium">
                                    {data.shipping.first_name} {data.shipping.last_name}
                                </p>
                                <p className="text-neutral-500">{data.shipping.line1}</p>
                                {data.shipping.neighborhood && (
                                    <p className="text-neutral-500">{data.shipping.neighborhood}</p>
                                )}
                                <p className="text-neutral-500">
                                    {data.shipping.city}, {data.shipping.state} {data.shipping.postal_code}
                                </p>
                            </div>
                        </section>
                    </div>

                    <aside className="h-fit rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                        <h2 className="mb-4 text-lg font-medium">Tu pedido</h2>
                        <ul className="mb-4 space-y-2 text-sm">
                            {items.map((item) => (
                                <li key={item.sku} className="flex justify-between gap-2">
                                    <span className="text-neutral-500">
                                        {item.quantity} × {item.name}
                                    </span>
                                    <span>{formatPrice(item.line_total)}</span>
                                </li>
                            ))}
                        </ul>
                        <dl className="space-y-2 border-t border-neutral-100 pt-3 text-sm dark:border-neutral-800">
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Subtotal</dt>
                                <dd>{formatPrice(totals.subtotal)}</dd>
                            </div>
                            {Number(totals.discount) > 0 && (
                                <div className="flex justify-between text-green-700 dark:text-green-400">
                                    <dt>Descuento</dt>
                                    <dd>−{formatPrice(totals.discount)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Envío</dt>
                                <dd>{Number(totals.shipping) === 0 ? '—' : formatPrice(totals.shipping)}</dd>
                            </div>
                            <div className="flex justify-between border-t border-neutral-100 pt-2 text-base font-semibold dark:border-neutral-800">
                                <dt>Total</dt>
                                <dd>{formatPrice(totals.total)}</dd>
                            </div>
                        </dl>
                    </aside>
                </div>
            )}

            {/* Navigation buttons */}
            <div className="mt-8 flex items-center justify-between border-t border-neutral-100 pt-6 dark:border-neutral-800">
                <div>
                    {step > 0 && (
                        <Button type="button" variant="outline" onClick={() => setStep(step - 1)}>
                            Atrás
                        </Button>
                    )}
                </div>
                <div>
                    {step < STEPS.length - 1 ? (
                        <Button type="button" onClick={() => setStep(step + 1)} disabled={!canAdvance()}>
                            Continuar
                        </Button>
                    ) : (
                        <Button onClick={handleSubmit} disabled={processing}>
                            {processing ? 'Procesando...' : 'Realizar pedido'}
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}
