import { Head, Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    CreditCard,
    Download,
    LifeBuoy,
    Package,
    Truck,
} from 'lucide-react';
import { formatPrice, useStoreUrls } from '@/lib/storefront';

type AddressFields = {
    first_name: string;
    last_name: string;
    line1: string;
    line2: string | null;
    neighborhood: string | null;
    city: string;
    state: string;
    postal_code: string;
    country: string;
};

type OrderItem = {
    name: string;
    sku: string | null;
    quantity: number;
    unit_price: string;
    line_total: string;
    thumbnail: string | null;
};

type OrderSummary = {
    number: string;
    status: string;
    email: string;
    subtotal: string;
    discount: string;
    shipping_amount: string;
    tax: string;
    total: string;
    payment_method: string | null;
    shipping_method_label: string | null;
    placed_at: string | null;
    items: OrderItem[];
    shipping_address: AddressFields | null;
};

const STATUS_BADGE: Record<string, { label: string; className: string }> = {
    pending_payment: { label: 'Pendiente de pago', className: 'bg-amber-100 text-amber-700' },
    payment_review: { label: 'En revisión', className: 'bg-amber-100 text-amber-700' },
    processing: { label: 'Procesando', className: 'bg-blue-100 text-blue-700' },
    paid: { label: 'Pagado', className: 'bg-green-100 text-green-700' },
    invoiced: { label: 'Facturado', className: 'bg-green-100 text-green-700' },
    partially_shipped: { label: 'Envío parcial', className: 'bg-blue-100 text-blue-700' },
    shipped: { label: 'Enviado', className: 'bg-blue-100 text-blue-700' },
    complete: { label: 'Completado', className: 'bg-green-100 text-green-700' },
    cancelled: { label: 'Cancelado', className: 'bg-red-100 text-red-700' },
    failed: { label: 'Fallido', className: 'bg-red-100 text-red-700' },
    refunded: { label: 'Reembolsado', className: 'bg-neutral-100 text-neutral-700' },
};

const PAYMENT_LABELS: Record<string, string> = {
    offline: 'Pago offline (efectivo / transferencia)',
    mercadopago: 'Mercado Pago',
    openpay: 'Openpay',
    cash: 'Efectivo',
};

const COUNTRY_LABELS: Record<string, string> = {
    MX: 'México',
    US: 'Estados Unidos',
};

function estimatedDelivery(placedAt: string | null): string | null {
    if (!placedAt) {
        return null;
    }

    const base = new Date(placedAt);
    const from = new Date(base);
    from.setDate(base.getDate() + 5);
    const to = new Date(base);
    to.setDate(base.getDate() + 8);

    const sameMonth = from.getMonth() === to.getMonth() && from.getFullYear() === to.getFullYear();
    const day = (d: Date) => d.toLocaleDateString('es-MX', { day: 'numeric' });
    const full = (d: Date) => d.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' });

    return sameMonth ? `${day(from)} - ${full(to)}` : `${full(from)} - ${full(to)}`;
}

export default function CheckoutSuccess({ order }: { order: OrderSummary }) {
    const urls = useStoreUrls();

    const badge = STATUS_BADGE[order.status] ?? {
        label: order.status,
        className: 'bg-neutral-100 text-neutral-700',
    };
    const paymentLabel = order.payment_method
        ? (PAYMENT_LABELS[order.payment_method] ?? order.payment_method)
        : 'Por definir';
    const eta = estimatedDelivery(order.placed_at);
    const hasDiscount = Number(order.discount) > 0;
    const isFreeShipping = Number(order.shipping_amount) === 0;

    return (
        <>
            <Head title={`Pedido ${order.number}`} />

            {/* Encabezado de confirmación */}
            <div className="mx-auto max-w-2xl text-center">
                <div className="mx-auto flex size-16 items-center justify-center rounded-full bg-red-600 text-white shadow-lg shadow-red-600/30">
                    <CheckCircle2 className="size-9" />
                </div>
                <h1 className="mt-6 text-4xl font-bold tracking-tight">¡Gracias por tu compra!</h1>
                <p className="mt-2 text-neutral-500">
                    Hemos recibido tu pedido y estamos trabajando en él.
                </p>
                <div className="mt-4 inline-flex items-center gap-2 rounded-full bg-neutral-100 px-4 py-1.5 text-sm dark:bg-neutral-800">
                    <span className="text-neutral-500">Pedido:</span>
                    <span className="font-semibold text-red-700 dark:text-red-400">#{order.number}</span>
                </div>
            </div>

            {/* Cuerpo en dos columnas */}
            <div className="mx-auto mt-10 grid max-w-5xl gap-6 lg:grid-cols-3">
                {/* Columna principal: productos + acciones */}
                <div className="space-y-6 lg:col-span-2">
                    <div className="overflow-hidden rounded-2xl border border-neutral-200 dark:border-neutral-800">
                        <div className="flex items-center justify-between border-b border-neutral-200 bg-neutral-50 px-6 py-4 dark:border-neutral-800 dark:bg-neutral-900/50">
                            <h2 className="text-lg font-semibold">Resumen de Productos</h2>
                            <span className={`rounded-full px-3 py-1 text-xs font-medium ${badge.className}`}>
                                {badge.label}
                            </span>
                        </div>

                        <div className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {order.items.map((item, i) => (
                                <div key={i} className="flex items-center gap-4 px-6 py-5">
                                    <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-neutral-100 dark:bg-neutral-800">
                                        {item.thumbnail ? (
                                            <img
                                                src={item.thumbnail}
                                                alt={item.name}
                                                className="h-full w-full object-cover"
                                            />
                                        ) : (
                                            <Package className="size-6 text-neutral-300" />
                                        )}
                                    </div>
                                    <div className="min-w-0 flex-1">
                                        <p className="font-semibold">{item.name}</p>
                                        {item.sku && (
                                            <p className="text-sm text-neutral-500">SKU: {item.sku}</p>
                                        )}
                                        <p className="text-sm text-neutral-500">Cantidad: {item.quantity}</p>
                                    </div>
                                    <div className="shrink-0 text-lg font-semibold text-red-700 dark:text-red-400">
                                        {formatPrice(item.line_total)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2">
                        <Link
                            href="/cuenta"
                            className="flex items-center justify-center gap-2 rounded-xl bg-red-700 px-5 py-3.5 text-sm font-medium text-white transition-colors hover:bg-red-800"
                        >
                            <Package className="size-4" /> Ver mis pedidos
                        </Link>
                        <button
                            type="button"
                            onClick={() => window.print()}
                            className="flex items-center justify-center gap-2 rounded-xl border border-neutral-300 px-5 py-3.5 text-sm font-medium transition-colors hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                        >
                            <Download className="size-4" /> Descargar Comprobante
                        </button>
                    </div>
                </div>

                {/* Columna lateral */}
                <div className="space-y-6">
                    {order.shipping_address && (
                        <div className="rounded-2xl border border-neutral-200 p-6 dark:border-neutral-800">
                            <div className="mb-4 flex items-center gap-2">
                                <Truck className="size-5 text-red-700 dark:text-red-400" />
                                <h2 className="text-lg font-semibold">Envío y Entrega</h2>
                            </div>
                            <div className="space-y-1 text-sm">
                                <p className="font-semibold">
                                    {order.shipping_address.first_name} {order.shipping_address.last_name}
                                </p>
                                <p className="text-neutral-500">{order.shipping_address.line1}</p>
                                {order.shipping_address.line2 && (
                                    <p className="text-neutral-500">{order.shipping_address.line2}</p>
                                )}
                                {order.shipping_address.neighborhood && (
                                    <p className="text-neutral-500">{order.shipping_address.neighborhood}</p>
                                )}
                                <p className="text-neutral-500">
                                    {order.shipping_address.city}, {order.shipping_address.state}{' '}
                                    {order.shipping_address.postal_code}
                                </p>
                                <p className="text-neutral-500">
                                    {COUNTRY_LABELS[order.shipping_address.country] ?? order.shipping_address.country}
                                </p>
                            </div>
                            {eta && (
                                <div className="mt-4 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                                    <p className="text-xs font-medium tracking-wide text-neutral-400 uppercase">
                                        Fecha estimada
                                    </p>
                                    <p className="mt-1 text-sm font-semibold capitalize">{eta}</p>
                                </div>
                            )}
                        </div>
                    )}

                    {/* Resumen de pago (acento) */}
                    <div className="rounded-2xl bg-red-700 p-6 text-white shadow-lg shadow-red-700/20">
                        <h2 className="mb-4 border-b border-white/20 pb-3 text-lg font-semibold">
                            Resumen de Pago
                        </h2>
                        <dl className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-white/80">Subtotal</dt>
                                <dd className="font-medium">{formatPrice(order.subtotal)}</dd>
                            </div>
                            {hasDiscount && (
                                <div className="flex justify-between">
                                    <dt className="text-white/80">Descuento</dt>
                                    <dd className="font-medium">- {formatPrice(order.discount)}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-white/80">Envío</dt>
                                <dd className="font-bold">
                                    {isFreeShipping ? 'GRATIS' : formatPrice(order.shipping_amount)}
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-white/80">IVA</dt>
                                <dd className="font-medium">{formatPrice(order.tax)}</dd>
                            </div>
                            <div className="flex justify-between border-t border-white/20 pt-3 text-base font-bold">
                                <dt>Total</dt>
                                <dd>{formatPrice(order.total)}</dd>
                            </div>
                        </dl>

                        <div className="mt-5 flex items-center gap-3 rounded-xl bg-red-800/60 px-4 py-3">
                            <CreditCard className="size-5 shrink-0 text-white/80" />
                            <div className="min-w-0">
                                <p className="text-[11px] font-medium tracking-wide text-white/60 uppercase">
                                    Método de pago
                                </p>
                                <p className="truncate text-sm font-medium">{paymentLabel}</p>
                            </div>
                        </div>
                    </div>

                    {/* Ayuda */}
                    <div className="rounded-2xl bg-neutral-50 p-6 dark:bg-neutral-900/50">
                        <div className="mb-2 flex items-center gap-2">
                            <LifeBuoy className="size-5 text-neutral-500" />
                            <h2 className="font-semibold">¿Necesitas ayuda?</h2>
                        </div>
                        <p className="text-sm text-neutral-500">
                            Si tienes alguna duda sobre tu pedido, nuestro equipo de soporte está disponible.
                        </p>
                        <Link
                            href={urls.home()}
                            className="mt-3 inline-flex items-center gap-1 text-sm font-medium text-red-700 hover:underline dark:text-red-400"
                        >
                            Contactar soporte <ArrowRight className="size-4" />
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
