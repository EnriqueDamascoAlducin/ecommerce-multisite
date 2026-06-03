import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import orders from '@/routes/admin/orders';
import shipments from '@/routes/admin/shipments';
import { statusLabel } from './status-labels';
import { useState } from 'react';

type ShipmentItem = {
    sku: string;
    name: string;
    quantity: number;
};

type ShipmentDetail = {
    id: number;
    number: string;
    status: string;
    carrier_code: string | null;
    carrier_label: string | null;
    tracking_number: string | null;
    total_qty: number;
    notes: string | null;
    store: string;
    order_number: string;
    order_id: number;
    order_status: string;
    shipped_at: string | null;
    delivered_at: string | null;
    items: ShipmentItem[];
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function ShipmentShow({ shipment }: { shipment: ShipmentDetail }) {
    const { can } = usePermissions();
    const [carrierCode, setCarrierCode] = useState(shipment.carrier_code ?? '');
    const [carrierLabel, setCarrierLabel] = useState(shipment.carrier_label ?? '');
    const [tracking, setTracking] = useState(shipment.tracking_number ?? '');

    const ship = () => {
        router.post(shipments.ship(shipment.id).url, {
            carrier_code: carrierCode,
            carrier_label: carrierLabel,
            tracking_number: tracking,
        }, { preserveScroll: true });
    };

    const deliver = () => {
        if (confirm('¿Marcar este envío como entregado?')) {
            router.post(shipments.deliver(shipment.id).url, {}, { preserveScroll: true });
        }
    };

    const cancel = () => {
        if (confirm('¿Cancelar este envío?')) {
            router.post(shipments.cancel(shipment.id).url, {}, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={`Envío ${shipment.number}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Envío {shipment.number}</h1>
                    <p className="text-sm text-neutral-500">{shipment.store}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge>{statusLabel(shipment.status)}</Badge>
                    <Button variant="outline" asChild><Link href={shipments.index().url}>Volver</Link></Button>
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
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {shipment.items.map((item) => (
                                    <tr key={item.sku}>
                                        <td className="px-4 py-2">{item.name}<span className="ml-2 font-mono text-xs text-neutral-400">{item.sku}</span></td>
                                        <td className="px-4 py-2 text-right">{item.quantity}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>

                    <div className="rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800">
                        <h3 className="mb-1 font-medium">Orden relacionada</h3>
                        <p className="text-neutral-500">
                            <Link href={orders.show(shipment.order_id).url} className="font-medium hover:underline">{shipment.order_number}</Link>
                            <span className="ml-2"><Badge variant="outline">{statusLabel(shipment.order_status)}</Badge></span>
                        </p>
                    </div>
                </div>

                <aside className="space-y-6">
                    {can('sales.shipments.edit') && shipment.status === 'pending' && (
                        <div className="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                            <h3 className="mb-3 text-sm font-medium">Despachar envío</h3>
                            <div className="grid gap-3">
                                <div>
                                    <Label>Transportista</Label>
                                    <Input value={carrierLabel} onChange={(e) => setCarrierLabel(e.target.value)} placeholder="Ej. Correos de México" className={fieldClass} />
                                </div>
                                <div>
                                    <Label>Código</Label>
                                    <Input value={carrierCode} onChange={(e) => setCarrierCode(e.target.value)} placeholder="Ej. correosmex" className={fieldClass} />
                                </div>
                                <div>
                                    <Label>Número de rastreo</Label>
                                    <Input value={tracking} onChange={(e) => setTracking(e.target.value)} placeholder="Número de guía" className={fieldClass} />
                                </div>
                                <Button size="sm" onClick={ship}>Marcar como enviado</Button>
                            </div>
                        </div>
                    )}

                    {can('sales.shipments.edit') && shipment.status === 'shipped' && (
                        <Button className="w-full" onClick={deliver}>Marcar como entregado</Button>
                    )}

                    {can('sales.shipments.cancel') && shipment.status === 'pending' && (
                        <Button variant="destructive" className="w-full" onClick={cancel}>Cancelar envío</Button>
                    )}
                </aside>
            </div>
        </>
    );
}
