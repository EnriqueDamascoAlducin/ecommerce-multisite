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

export default function OrderShow({ order, statuses }: { order: OrderDetail; statuses: string[] }) {
    const { can } = usePermissions();
    const [status, setStatus] = useState(order.status);
    const [statusComment, setStatusComment] = useState('');
    const [comment, setComment] = useState('');
    const [shipQty, setShipQty] = useState<Record<number, number>>(
        Object.fromEntries(order.shipment_items_available.map((i) => [i.id, i.max_qty])),
    );

    const updateStatus = () => {
        router.put(orders.status(order.id).url, { status, comment: statusComment }, { preserveScroll: true, onSuccess: () => setStatusComment('') });
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
        a ? `${a.first_name} ${a.last_name}, ${a.line1}${a.line2 ? `, ${a.line2}` : ''}, ${a.city}, ${a.state}, ${a.postal_code} (${a.country})` : '—';

    return (
        <>
            <Head title={`Orden ${order.number}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Orden {order.number}</h1>
                    <p className="text-sm text-neutral-500">{order.store} · {order.placed_at}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge>{statusLabel(order.status)}</Badge>
                    <Button variant="outline" asChild><Link href={orders.index()}>Volver</Link></Button>
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="space-y-6 lg:col-span-2">
                    <section className="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
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
                                    <tr key={item.sku}>
                                        <td className="px-4 py-2">{item.name}<span className="ml-2 font-mono text-xs text-neutral-400">{item.sku}</span></td>
                                        <td className="px-4 py-2 text-right">{item.quantity}</td>
                                        <td className="px-4 py-2 text-right">{item.unit_price}</td>
                                        <td className="px-4 py-2 text-right">{item.line_total}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                        <dl className="space-y-1 border-t border-neutral-200 p-4 text-sm dark:border-neutral-800">
                            <div className="flex justify-between"><dt className="text-neutral-500">Subtotal</dt><dd>{order.subtotal}</dd></div>
                            <div className="flex justify-between"><dt className="text-neutral-500">Envío ({order.shipping_method_label ?? '—'})</dt><dd>{order.shipping_amount}</dd></div>
                            <div className="flex justify-between text-base font-semibold"><dt>Total</dt><dd>{order.total} {order.currency}</dd></div>
                        </dl>
                    </section>

                    <section className="grid gap-4 sm:grid-cols-2">
                        <div className="rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800">
                            <h3 className="mb-1 font-medium">Envío</h3>
                            <p className="text-neutral-500">{fmtAddress(order.shipping_address)}</p>
                        </div>
                        <div className="rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800">
                            <h3 className="mb-1 font-medium">Facturación</h3>
                            <p className="text-neutral-500">{fmtAddress(order.billing_address)}</p>
                        </div>
                    </section>

                    {order.transactions.length > 0 && (
                        <section className="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <h3 className="border-b border-neutral-200 px-4 py-2 font-medium dark:border-neutral-800">Pagos</h3>
                            <table className="w-full text-left text-sm">
                                <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                    {order.transactions.map((t, i) => (
                                        <tr key={i}>
                                            <td className="px-4 py-2 capitalize">{t.gateway}</td>
                                            <td className="px-4 py-2"><Badge>{statusLabel(t.status)}</Badge></td>
                                            <td className="px-4 py-2 font-mono text-xs text-neutral-400">{t.gateway_transaction_id ?? '—'}</td>
                                            <td className="px-4 py-2 text-right">{t.amount} {t.currency}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </section>
                    )}

                    {order.shipments.length > 0 && (
                        <section className="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <h3 className="border-b border-neutral-200 px-4 py-2 font-medium dark:border-neutral-800">Envíos</h3>
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
                                            <td className="px-4 py-2 font-mono text-xs"><Link href={shipments.show(s.id).url} className="font-medium hover:underline">{s.number}</Link></td>
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
                        <h3 className="mb-3 font-medium">Historial</h3>
                        <ol className="space-y-2">
                            {order.history.map((h, i) => (
                                <li key={i} className="rounded-md border border-neutral-200 p-3 text-sm dark:border-neutral-800">
                                    <div className="flex items-center justify-between">
                                        <span>
                                            {h.from_status ? `${statusLabel(h.from_status)} → ` : ''}
                                            <strong>{statusLabel(h.to_status)}</strong>
                                        </span>
                                        <span className="text-xs text-neutral-400">{h.created_at}</span>
                                    </div>
                                    {h.comment && <p className="mt-1 text-neutral-500">{h.comment}</p>}
                                    {h.user && <p className="mt-1 text-xs text-neutral-400">por {h.user}</p>}
                                </li>
                            ))}
                        </ol>
                    </section>
                </div>

                <aside className="space-y-6">
                    <div className="rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800">
                        <h3 className="mb-1 font-medium">Cliente</h3>
                        <p className="text-neutral-500">{order.customer?.name ?? 'Invitado'}</p>
                        <p className="text-neutral-500">{order.email}</p>
                        <p className="mt-2 text-xs text-neutral-400">Pago: {order.payment_method ?? '—'}</p>
                    </div>

                    {can('sales.orders.edit') && (
                        <div className="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                            <h3 className="mb-2 text-sm font-medium">Cambiar estado</h3>
                            <div className="grid gap-2">
                                <select value={status} onChange={(e) => setStatus(e.target.value)} className={fieldClass}>
                                    {statuses.map((s) => <option key={s} value={s}>{statusLabel(s)}</option>)}
                                </select>
                                <textarea value={statusComment} onChange={(e) => setStatusComment(e.target.value)} rows={2} placeholder="Comentario (opcional)" className={fieldClass} />
                                <Button size="sm" onClick={updateStatus}>Actualizar estado</Button>
                            </div>

                            <div className="mt-4 grid gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                                <Label>Comentario interno</Label>
                                <textarea value={comment} onChange={(e) => setComment(e.target.value)} rows={2} className={fieldClass} />
                                <Button size="sm" variant="outline" onClick={addComment}>Agregar comentario</Button>
                            </div>
                        </div>
                    )}

                    {can('sales.invoices.create') && order.can_invoice && (
                        <Button className="w-full" onClick={() => {
                            router.post(invoices.store().url, { order_id: order.id }, { preserveScroll: true });
                        }}>Generar factura</Button>
                    )}

                    {can('sales.shipments.create') && order.can_ship && (
                        <div className="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                            <h3 className="mb-2 text-sm font-medium">Generar envío</h3>
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

                    {can('sales.orders.cancel') && order.is_cancellable && (
                        <Button variant="destructive" className="w-full" onClick={cancel}>Cancelar orden</Button>
                    )}
                </aside>
            </div>
        </>
    );
}
