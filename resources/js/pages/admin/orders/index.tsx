import { Form, Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import orders from '@/routes/admin/orders';
import { statusLabel } from './status-labels';

type OrderRow = {
    id: number;
    number: string;
    status: string;
    email: string;
    total: string;
    store: string;
    placed_at: string | null;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export default function OrdersIndex({
    orders: page,
    filters,
    statuses,
}: {
    orders: Paginated<OrderRow>;
    filters: { search: string; status: string };
    statuses: string[];
}) {
    return (
        <>
            <Head title="Órdenes" />
            <h1 className="mb-6 text-2xl font-semibold">Órdenes</h1>

            <Form {...orders.index.form()} className="mb-4 flex flex-wrap gap-2" options={{ preserveState: true }}>
                <Input name="search" defaultValue={filters.search} placeholder="Buscar por número o email" className="max-w-xs" />
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
                            <th className="px-4 py-3 font-medium">Número</th>
                            <th className="px-4 py-3 font-medium">Cliente</th>
                            <th className="px-4 py-3 font-medium">Tienda</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3 text-right font-medium">Total</th>
                            <th className="px-4 py-3 font-medium">Fecha</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {page.data.map((order) => (
                            <tr key={order.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td className="px-4 py-3 font-mono text-xs">
                                    <Link href={orders.show(order.id)} className="font-medium hover:underline">{order.number}</Link>
                                </td>
                                <td className="px-4 py-3">{order.email}</td>
                                <td className="px-4 py-3">{order.store}</td>
                                <td className="px-4 py-3"><Badge variant="outline">{statusLabel(order.status)}</Badge></td>
                                <td className="px-4 py-3 text-right">{order.total}</td>
                                <td className="px-4 py-3 text-xs text-neutral-500">{order.placed_at}</td>
                            </tr>
                        ))}
                        {page.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">No hay órdenes.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-neutral-500">
                <span>{page.total} órdenes</span>
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
