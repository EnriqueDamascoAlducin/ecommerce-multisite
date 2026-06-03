import { Form, Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import orders from '@/routes/admin/orders';
import shipments from '@/routes/admin/shipments';
import { statusLabel } from './status-labels';

type ShipmentRow = {
    id: number;
    number: string;
    order_number: string;
    order_id: number;
    status: string;
    carrier_label: string | null;
    tracking_number: string | null;
    total_qty: number;
    store: string;
    shipped_at: string | null;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export default function ShipmentsIndex({
    shipments: page,
    filters,
    statuses,
}: {
    shipments: Paginated<ShipmentRow>;
    filters: { search: string; status: string };
    statuses: string[];
}) {
    return (
        <>
            <Head title="Envíos" />
            <h1 className="mb-6 text-2xl font-semibold">Envíos</h1>

            <Form action={shipments.index().url} className="mb-4 flex flex-wrap gap-2" options={{ preserveState: true }}>
                <Input name="search" defaultValue={filters.search} placeholder="Buscar por número de envío u orden" className="max-w-xs" />
                <select name="status" defaultValue={filters.status} className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">Todos los estados</option>
                    {statuses.map((s) => (
                        <option key={s} value={s}>{statusLabel(s)}</option>
                    ))}
                </select>
                <Button variant="outline">Filtrar</Button>
            </Form>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Envío</th>
                            <th className="px-4 py-3 font-medium">Orden</th>
                            <th className="px-4 py-3 font-medium">Tienda</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3 font-medium">Transportista</th>
                            <th className="px-4 py-3 font-medium">Rastreo</th>
                            <th className="px-4 py-3 text-right font-medium">Qty</th>
                            <th className="px-4 py-3 font-medium">Fecha</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {page.data.map((s) => (
                            <tr key={s.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td className="px-4 py-3 font-mono text-xs">
                                    <Link href={shipments.show(s.id).url} className="font-medium hover:underline">{s.number}</Link>
                                </td>
                                <td className="px-4 py-3 font-mono text-xs">
                                    <Link href={orders.show(s.order_id).url} className="hover:underline">{s.order_number}</Link>
                                </td>
                                <td className="px-4 py-3">{s.store}</td>
                                <td className="px-4 py-3"><Badge variant="outline">{statusLabel(s.status)}</Badge></td>
                                <td className="px-4 py-3 text-xs text-neutral-500">{s.carrier_label ?? '—'}</td>
                                <td className="px-4 py-3 font-mono text-xs">{s.tracking_number ?? '—'}</td>
                                <td className="px-4 py-3 text-right">{s.total_qty}</td>
                                <td className="px-4 py-3 text-xs text-neutral-500">{s.shipped_at}</td>
                            </tr>
                        ))}
                        {page.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-8 text-center text-neutral-500">No hay envíos.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-neutral-500">
                <span>{page.total} envíos</span>
                <div className="flex gap-2">
                    {page.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild><Link href={page.prev_page_url} preserveScroll>Anterior</Link></Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Anterior</Button>
                    )}
                    {page.next_page_url ? (
                        <Button variant="outline" size="sm" asChild><Link href={page.next_page_url} preserveScroll>Siguiente</Link></Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Siguiente</Button>
                    )}
                </div>
            </div>
        </>
    );
}
