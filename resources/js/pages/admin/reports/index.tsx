import { Form, Head } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { formatPrice } from '@/lib/storefront';
import reports from '@/routes/admin/reports';
import { statusLabel } from '../orders/status-labels';

type Summary = {
    total_orders: number;
    paid_orders: number;
    revenue: string;
    avg_order_value: string;
    units_sold: number;
};
type DayPoint = { day: string; orders: number; revenue: string };
type StatusRow = { status: string; count: number; total: string };
type ProductRow = { sku: string; name: string; quantity: number; revenue: string };
type StoreRow = { store: string; orders: number; revenue: string };
type StoreOption = { id: number; label: string };

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function ReportsIndex({
    filters,
    summary,
    revenueByDay,
    ordersByStatus,
    topProducts,
    byStore,
    stores,
}: {
    filters: { from: string; to: string; store_id: number | null };
    summary: Summary;
    revenueByDay: DayPoint[];
    ordersByStatus: StatusRow[];
    topProducts: ProductRow[];
    byStore: StoreRow[];
    stores: StoreOption[];
    statuses: string[];
}) {
    const maxRevenue = Math.max(...revenueByDay.map((d) => Number(d.revenue)), 1);

    const cards = [
        { label: 'Ingresos', value: formatPrice(summary.revenue) },
        { label: 'Órdenes pagadas', value: String(summary.paid_orders) },
        { label: 'Órdenes totales', value: String(summary.total_orders) },
        { label: 'Ticket promedio', value: formatPrice(summary.avg_order_value) },
        { label: 'Unidades vendidas', value: String(summary.units_sold) },
    ];

    return (
        <>
            <Head title="Reportes" />
            <h1 className="mb-6 text-2xl font-semibold">Reportes de ventas</h1>

            <Form {...reports.index.form()} className="mb-6 flex flex-wrap items-end gap-3" options={{ preserveState: true }}>
                <div className="grid gap-1.5">
                    <label className="text-xs text-neutral-500" htmlFor="from">Desde</label>
                    <Input id="from" type="date" name="from" defaultValue={filters.from} className={fieldClass} />
                </div>
                <div className="grid gap-1.5">
                    <label className="text-xs text-neutral-500" htmlFor="to">Hasta</label>
                    <Input id="to" type="date" name="to" defaultValue={filters.to} className={fieldClass} />
                </div>
                <div className="grid gap-1.5">
                    <label className="text-xs text-neutral-500" htmlFor="store_id">Tienda</label>
                    <select id="store_id" name="store_id" defaultValue={filters.store_id ?? ''} className={fieldClass}>
                        <option value="">Todas las tiendas</option>
                        {stores.map((store) => (
                            <option key={store.id} value={store.id}>{store.label}</option>
                        ))}
                    </select>
                </div>
                <Button variant="outline">Aplicar</Button>
            </Form>

            {/* KPIs */}
            <div className="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                {cards.map((card) => (
                    <div key={card.label} className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <p className="text-xs font-medium tracking-wide text-neutral-500 uppercase">{card.label}</p>
                        <p className="mt-1 text-2xl font-semibold">{card.value}</p>
                    </div>
                ))}
            </div>

            {/* Ingresos por día */}
            <section className="mb-6 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                <h2 className="mb-4 text-sm font-medium text-neutral-500">Ingresos por día</h2>
                {revenueByDay.length === 0 ? (
                    <p className="py-8 text-center text-sm text-neutral-400">Sin ventas confirmadas en el periodo.</p>
                ) : (
                    <>
                        <div className="flex h-40 items-end gap-1">
                            {revenueByDay.map((point) => (
                                <div
                                    key={point.day}
                                    className="flex-1 rounded-t bg-neutral-800 transition-colors hover:bg-neutral-600 dark:bg-neutral-300 dark:hover:bg-white"
                                    style={{ height: `${Math.max((Number(point.revenue) / maxRevenue) * 100, 2)}%` }}
                                    title={`${point.day}: ${formatPrice(point.revenue)} (${point.orders} órdenes)`}
                                />
                            ))}
                        </div>
                        <div className="mt-2 flex justify-between text-xs text-neutral-400">
                            <span>{revenueByDay[0]?.day}</span>
                            <span>{revenueByDay[revenueByDay.length - 1]?.day}</span>
                        </div>
                    </>
                )}
            </section>

            <div className="grid gap-6 lg:grid-cols-2">
                {/* Top productos */}
                <section className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="border-b border-neutral-200 px-4 py-3 text-sm font-medium text-neutral-500 dark:border-neutral-800">Productos más vendidos</h2>
                    <table className="w-full text-left text-sm">
                        <thead className="text-neutral-400">
                            <tr>
                                <th className="px-4 py-2 font-medium">Producto</th>
                                <th className="px-4 py-2 text-right font-medium">Uds.</th>
                                <th className="px-4 py-2 text-right font-medium">Ingresos</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {topProducts.map((product) => (
                                <tr key={product.sku}>
                                    <td className="px-4 py-2">{product.name}<span className="ml-2 font-mono text-xs text-neutral-400">{product.sku}</span></td>
                                    <td className="px-4 py-2 text-right">{product.quantity}</td>
                                    <td className="px-4 py-2 text-right">{formatPrice(product.revenue)}</td>
                                </tr>
                            ))}
                            {topProducts.length === 0 && (
                                <tr><td colSpan={3} className="px-4 py-6 text-center text-neutral-400">Sin ventas.</td></tr>
                            )}
                        </tbody>
                    </table>
                </section>

                {/* Por tienda + por estado */}
                <div className="space-y-6">
                    <section className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="border-b border-neutral-200 px-4 py-3 text-sm font-medium text-neutral-500 dark:border-neutral-800">Ingresos por tienda</h2>
                        <table className="w-full text-left text-sm">
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {byStore.map((row) => (
                                    <tr key={row.store}>
                                        <td className="px-4 py-2">{row.store}</td>
                                        <td className="px-4 py-2 text-right text-neutral-500">{row.orders} órd.</td>
                                        <td className="px-4 py-2 text-right">{formatPrice(row.revenue)}</td>
                                    </tr>
                                ))}
                                {byStore.length === 0 && (
                                    <tr><td colSpan={3} className="px-4 py-6 text-center text-neutral-400">Sin ventas.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </section>

                    <section className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                        <h2 className="border-b border-neutral-200 px-4 py-3 text-sm font-medium text-neutral-500 dark:border-neutral-800">Órdenes por estado</h2>
                        <table className="w-full text-left text-sm">
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {ordersByStatus.map((row) => (
                                    <tr key={row.status}>
                                        <td className="px-4 py-2">{statusLabel(row.status)}</td>
                                        <td className="px-4 py-2 text-right text-neutral-500">{row.count}</td>
                                        <td className="px-4 py-2 text-right">{formatPrice(row.total)}</td>
                                    </tr>
                                ))}
                                {ordersByStatus.length === 0 && (
                                    <tr><td colSpan={3} className="px-4 py-6 text-center text-neutral-400">Sin órdenes.</td></tr>
                                )}
                            </tbody>
                        </table>
                    </section>
                </div>
            </div>
        </>
    );
}
