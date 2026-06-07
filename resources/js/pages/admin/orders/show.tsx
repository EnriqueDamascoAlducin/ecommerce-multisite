import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import invoices from '@/routes/admin/invoices';
import orders from '@/routes/admin/orders';
import shipments from '@/routes/admin/shipments';
import { statusLabel } from './status-labels';

type Address = {
    first_name: string; last_name: string; company: string | null; phone: string | null;
    line1: string; line2: string | null; city: string; state: string; postal_code: string; country: string;
} | null;

type OrderDetail = {
    id: number;
    number: string;
    status: string;
    email: string;
    currency: string;
    subtotal: string; discount: string; shipping_amount: string; tax: string; total: string;
    shipping_method_label: string | null;
    payment_method: string | null;
    store: string;
    placed_at: string | null;
    is_cancellable: boolean;
    can_invoice: boolean;
    can_ship: boolean;
    shipment_items_available: { id: number; max_qty: number }[];
    customer: { name: string; email: string } | null;
    items: { id: number; sku: string; name: string; quantity: number; unit_price: string; line_total: string }[];
    shipping_address: Address;
    billing_address: Address;
    history: { from_status: string | null; to_status: string; comment: string | null; user: string | null; created_at: string | null }[];
    shipments: { id: number; number: string; status: string; carrier_label: string | null; tracking_number: string | null; total_qty: number; shipped_at: string | null }[];
    transactions: { gateway: string; status: string; amount: string; currency: string; gateway_transaction_id: string | null; created_at: string | null }[];
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

const STATUS_STYLES: Record<string, string> = {
    pending_payment: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-300',
    payment_review: 'bg-blue-100 text-blue-800 dark:bg-blue-900/30 dark:text-blue-400 border-blue-300',
    processing: 'bg-sky-100 text-sky-800 dark:bg-sky-900/30 dark:text-sky-400 border-sky-300',
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-300',
    invoiced: 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/30 dark:text-indigo-400 border-indigo-300',
    partially_shipped: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-400 border-cyan-300',
    shipped: 'bg-teal-100 text-teal-800 dark:bg-teal-900/30 dark:text-teal-400 border-teal-300',
    complete: 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400 border-green-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300',
    failed: 'bg-rose-100 text-rose-800 dark:bg-rose-900/30 dark:text-rose-400 border-rose-300',
    refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 border-purple-300',
};

const STATUS_ACTIONS: Record<string, { label: string; nextStatus: string; color: string }[]> = {
    pending_payment: [
        { label: 'Marcar como pagada', nextStatus: 'paid', color: 'bg-emerald-600 hover:bg-emerald-700' },
    ],
    payment_review: [
        { label: 'Aprobar pago', nextStatus: 'paid', color: 'bg-emerald-600 hover:bg-emerald-700' },
    ],
    paid: [],
    processing: [],
    invoiced: [],
    partially_shipped: [],
    shipped: [],
    complete: [],
    cancelled: [],
    failed: [
        { label: 'Reintentar', nextStatus: 'pending_payment', color: 'bg-amber-600 hover:bg-amber-700' },
    ],
    refunded: [],
};

export default function OrderShow({ order, statuses }: { order: OrderDetail; statuses: string[] }) {
    const { can } = usePermissions();
    const [newStatus, setNewStatus] = useState(order.status);
    const [statusComment, setStatusComment] = useState('');
    const [comment, setComment] = useState('');
    const [shipQty, setShipQty] = useState<Record<number, number>>(
        Object.fromEntries(order.shipment_items_available.map((i) => [i.id, i.max_qty])),
    );

    const updateStatus = (status?: string, comment?: string) => {
        router.put(orders.status(order.id).url, {
            status: status ?? newStatus,
            comment: comment ?? statusComment,
        }, { preserveScroll: true, onSuccess: () => setStatusComment('') });
    };

    const addComment = () => {
        if (!comment.trim()) return;
        router.post(orders.comment(order.id).url, { comment }, { preserveScroll: true, onSuccess: () => setComment('') });
    };

    const cancel = () => {
        if (confirm('¿Cancelar esta orden y liberar el stock reservado?')) {
            router.post(orders.cancel(order.id).url, {}, { preserveScroll: true });
        }
    };

    const generateShipment = () => {
        const items = order.items
            .filter((i) => (shipQty[i.id] ?? 0) > 0)
            .map((i) => ({ order_item_id: i.id, quantity: shipQty[i.id] }));

        if (items.length === 0) {
            alert('Selecciona al menos un producto con cantidad > 0.');
            return;
        }

        router.post(shipments.store().url, { order_id: order.id, items }, { preserveScroll: true });
    };

    const fmtAddress = (a: Address) =>
        a ? `${a.first_name} ${a.last_name}${a.company ? ` - ${a.company}` : ''}, ${a.line1}${a.line2 ? `, ${a.line2}` : ''}, ${a.city}, ${a.state}, ${a.postal_code} (${a.country})` : '—';

    const actions = STATUS_ACTIONS[order.status] ?? [];

    return (
        <>
            <Head title={`Orden ${order.number}`} />

            <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">Orden {order.number}</h1>
                            <Badge className={STATUS_STYLES[order.status] ?? ''}>
                                {statusLabel(order.status)}
                            </Badge>
                        </div>
                        <p className="mt-1.5 text-sm text-neutral-500">
                            {order.store}
                            {order.placed_at && <> &middot; {order.placed_at}</>}
                            {order.customer && <> &middot; {order.customer.name}</>}
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-2xl font-bold">{order.total} {order.currency}</p>
                        <p className="text-sm text-neutral-500">
                            {order.customer?.email ?? order.email}
                        </p>
                    </div>
                </div>

                {can('sales.orders.edit') && (actions.length > 0 || ['paid', 'invoiced'].includes(order.status)) && (
                    <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                        {actions.map((action) => (
                            <Button
                                key={action.nextStatus}
                                className={action.color}
                                onClick={() => updateStatus(action.nextStatus, '')}
                                size="sm"
                            >
                                {action.label}
                            </Button>
                        ))}
                        {order.can_invoice && (
                            <Button
                                variant="outline"
                                size="sm"
                                onClick={() => {
                                    router.post(invoices.store().url, { order_id: order.id }, { preserveScroll: true });
                                }}
                            >
                                Generar factura
                            </Button>
                        )}
                        {can('sales.orders.cancel') && order.is_cancellable && (
                            <Button variant="destructive" size="sm" onClick={cancel}>
                                Cancelar orden
                            </Button>
                        )}
                        <Button variant="outline" size="sm" asChild className="ml-auto">
                            <Link href={orders.index()}>Volver</Link>
                        </Button>
                    </div>
                )}
            </div>

            <div className="mt-6 grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="border-b border-neutral-200 px-4 py-2.5 text-sm font-semibold dark:border-neutral-800">
                            Productos
                        </h2>
                        <table className="w-full text-left text-sm">
                            <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                                <tr>
                                    <th className="px-4 py-2 font-medium">Producto</th>
                                    <th className="px-4 py-2 text-right font-medium">Cant.</th>
                                    <th className="px-4 py-2 text-right font-medium">Precio</th>
                                    <th className="px-4 py-2 text-right font-medium">Total</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {order.items.map((item) => (
                                    <tr key={item.sku} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                        <td className="px-4 py-2">
                                            <span>{item.name}</span>
                                            <span className="ml-2 font-mono text-xs text-neutral-400">{item.sku}</span>
                                        </td>
                                        <td className="px-4 py-2 text-right">{item.quantity}</td>
                                        <td className="px-4 py-2 text-right">{item.unit_price}</td>
                                        <td className="px-4 py-2 text-right font-medium">{item.line_total}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <dl className="space-y-1 border-t border-neutral-200 bg-neutral-50/50 px-4 py-3 text-sm dark:border-neutral-800 dark:bg-neutral-800/30">
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Subtotal</dt>
                                <dd>{order.subtotal}</dd>
                            </div>
                            {Number(order.discount) > 0 && (
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Descuento</dt>
                                    <dd className="text-red-600">-{order.discount}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">
                                    Envío ({order.shipping_method_label ?? '—'})
                                </dt>
                                <dd>{order.shipping_amount}</dd>
                            </div>
                            {Number(order.tax) > 0 && (
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Impuestos</dt>
                                    <dd>{order.tax}</dd>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-neutral-200 pt-2 text-base font-semibold dark:border-neutral-700">
                                <dt>Total</dt>
                                <dd>{order.total} {order.currency}</dd>
                            </div>
                        </dl>
                    </section>

                    {order.transactions.length > 0 && (
                        <section className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <h2 className="border-b border-neutral-200 px-4 py-2.5 text-sm font-semibold dark:border-neutral-800">
                                Pagos
                            </h2>
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Gateway</th>
                                        <th className="px-4 py-2 font-medium">Estado</th>
                                        <th className="px-4 py-2 font-medium">Transacción</th>
                                        <th className="px-4 py-2 text-right font-medium">Monto</th>
                                        <th className="px-4 py-2 font-medium">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {order.transactions.map((t, i) => (
                                        <tr key={i} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                            <td className="px-4 py-2 capitalize">{t.gateway}</td>
                                            <td className="px-4 py-2"><Badge variant="outline">{statusLabel(t.status)}</Badge></td>
                                            <td className="px-4 py-2 font-mono text-xs text-neutral-400">{t.gateway_transaction_id ?? '—'}</td>
                                            <td className="px-4 py-2 text-right font-medium">{t.amount} {t.currency}</td>
                                            <td className="px-4 py-2 text-xs text-neutral-500">{t.created_at}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    )}

                    {order.shipments.length > 0 && (
                        <section className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                            <h2 className="border-b border-neutral-200 px-4 py-2.5 text-sm font-semibold dark:border-neutral-800">
                                Envíos
                            </h2>
                            <table className="w-full text-left text-sm">
                                <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                                    <tr>
                                        <th className="px-4 py-2 font-medium">Número</th>
                                        <th className="px-4 py-2 font-medium">Estado</th>
                                        <th className="px-4 py-2 font-medium">Transportista</th>
                                        <th className="px-4 py-2 font-medium">Rastreo</th>
                                        <th className="px-4 py-2 text-right font-medium">Qty</th>
                                        <th className="px-4 py-2 font-medium">Fecha</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {order.shipments.map((s) => (
                                        <tr key={s.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                            <td className="px-4 py-2 font-mono text-xs">
                                                <Link href={shipments.show(s.id).url} className="font-medium text-blue-600 hover:underline dark:text-blue-400">
                                                    {s.number}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-2"><Badge variant="outline">{statusLabel(s.status)}</Badge></td>
                                            <td className="px-4 py-2 text-xs text-neutral-500">{s.carrier_label ?? '—'}</td>
                                            <td className="px-4 py-2 font-mono text-xs">{s.tracking_number ?? '—'}</td>
                                            <td className="px-4 py-2 text-right">{s.total_qty}</td>
                                            <td className="px-4 py-2 text-xs text-neutral-500">{s.shipped_at}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    )}

                    <section>
                        <h2 className="mb-3 text-sm font-semibold">Historial</h2>
                        <ol className="space-y-2">
                            {order.history.map((h, i) => (
                                <li key={i} className="rounded-lg border border-neutral-200 bg-white p-3 text-sm dark:border-neutral-800 dark:bg-neutral-900">
                                    <div className="flex items-center justify-between">
                                        <span className="flex items-center gap-1.5">
                                            {h.from_status && (
                                                <span className="text-neutral-500">{statusLabel(h.from_status)}</span>
                                            )}
                                            {h.from_status && (
                                                <svg className="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                                                    <path strokeLinecap="round" strokeLinejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                                </svg>
                                            )}
                                            <strong>{statusLabel(h.to_status)}</strong>
                                        </span>
                                        <span className="text-xs text-neutral-400">{h.created_at}</span>
                                    </div>
                                    {h.comment && (
                                        <p className="mt-1.5 text-neutral-600 dark:text-neutral-400">{h.comment}</p>
                                    )}
                                    {h.user && (
                                        <p className="mt-1 text-xs text-neutral-400">por {h.user}</p>
                                    )}
                                </li>
                            ))}
                        </ol>
                    </section>
                </div>

                <div className="space-y-6">
                    <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <h3 className="mb-3 text-sm font-semibold">Cliente</h3>
                        <dl className="space-y-2 text-sm">
                            <div>
                                <dt className="text-xs text-neutral-500">Nombre</dt>
                                <dd className="font-medium">{order.customer?.name ?? 'Invitado'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-neutral-500">Email</dt>
                                <dd className="font-medium">{order.email}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-neutral-500">Método de pago</dt>
                                <dd className="font-medium">{order.payment_method ?? '—'}</dd>
                            </div>
                            <div>
                                <dt className="text-xs text-neutral-500">Método de envío</dt>
                                <dd className="font-medium">{order.shipping_method_label ?? '—'}</dd>
                            </div>
                        </dl>
                    </div>

                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-1">
                        <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                            <h3 className="mb-2 text-sm font-semibold">Dirección de envío</h3>
                            {order.shipping_address ? (
                                <div className="text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                                    <p className="font-medium text-neutral-900 dark:text-white">
                                        {order.shipping_address.first_name} {order.shipping_address.last_name}
                                    </p>
                                    {order.shipping_address.company && <p>{order.shipping_address.company}</p>}
                                    <p>{order.shipping_address.line1}</p>
                                    {order.shipping_address.line2 && <p>{order.shipping_address.line2}</p>}
                                    <p>
                                        {order.shipping_address.city}, {order.shipping_address.state} {order.shipping_address.postal_code}
                                    </p>
                                    <p>{order.shipping_address.country}</p>
                                    {order.shipping_address.phone && <p className="mt-1">{order.shipping_address.phone}</p>}
                                </div>
                            ) : (
                                <p className="text-sm text-neutral-500">—</p>
                            )}
                        </div>

                        <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                            <h3 className="mb-2 text-sm font-semibold">Dirección de facturación</h3>
                            {order.billing_address ? (
                                <div className="text-sm leading-relaxed text-neutral-600 dark:text-neutral-400">
                                    <p className="font-medium text-neutral-900 dark:text-white">
                                        {order.billing_address.first_name} {order.billing_address.last_name}
                                    </p>
                                    {order.billing_address.company && <p>{order.billing_address.company}</p>}
                                    <p>{order.billing_address.line1}</p>
                                    {order.billing_address.line2 && <p>{order.billing_address.line2}</p>}
                                    <p>
                                        {order.billing_address.city}, {order.billing_address.state} {order.billing_address.postal_code}
                                    </p>
                                    <p>{order.billing_address.country}</p>
                                    {order.billing_address.phone && <p className="mt-1">{order.billing_address.phone}</p>}
                                </div>
                            ) : (
                                <p className="text-sm text-neutral-500">—</p>
                            )}
                        </div>
                    </div>

                    {can('sales.orders.edit') && (
                        <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                            <h3 className="mb-3 text-sm font-semibold">Cambiar estado</h3>
                            <div className="grid gap-2">
                                <select value={newStatus} onChange={(e) => setNewStatus(e.target.value)} className={fieldClass}>
                                    {statuses.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
                                </select>
                                <textarea
                                    value={statusComment}
                                    onChange={(e) => setStatusComment(e.target.value)}
                                    rows={2}
                                    placeholder="Comentario (opcional)"
                                    className={fieldClass}
                                />
                                <Button size="sm" onClick={() => updateStatus()}>Actualizar estado</Button>
                            </div>

                            <div className="mt-4 grid gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                                <Label>Comentario interno</Label>
                                <textarea
                                    value={comment}
                                    onChange={(e) => setComment(e.target.value)}
                                    rows={2}
                                    className={fieldClass}
                                />
                                <Button size="sm" variant="outline" onClick={addComment}>Agregar comentario</Button>
                            </div>
                        </div>
                    )}

                    {can('sales.shipments.create') && order.can_ship && (
                        <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                            <h3 className="mb-3 text-sm font-semibold">Generar envío</h3>
                            <div className="grid gap-2">
                                {order.shipment_items_available.map((item) => {
                                    const orderItem = order.items.find((i) => i.id === item.id);
                                    return (
                                        <div key={item.id} className="flex items-center gap-2 text-sm">
                                            <span className="flex-1 truncate">{orderItem?.name}</span>
                                            <Input
                                                type="number"
                                                min={0}
                                                max={item.max_qty}
                                                value={shipQty[item.id] ?? 0}
                                                onChange={(e) => setShipQty((prev) => ({ ...prev, [item.id]: Math.min(item.max_qty, Math.max(0, Number(e.target.value))) }))}
                                                className="w-20 text-center"
                                            />
                                            <span className="w-6 text-right text-neutral-500">/{item.max_qty}</span>
                                        </div>
                                    );
                                })}
                                <Button size="sm" className="mt-1" onClick={generateShipment}>Crear envío</Button>
                            </div>
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
