import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import invoices from '@/routes/admin/invoices';
import orders from '@/routes/admin/orders';
import { statusLabel } from './status-labels';

type InvoiceDetail = {
    id: number;
    number: string;
    status: string;
    currency: string;
    subtotal: string;
    discount: string;
    shipping_amount: string;
    tax: string;
    total: string;
    store: string;
    website: string;
    order_number: string;
    order_id: number;
    order_status: string;
    invoiced_at: string | null;
    items: { sku: string; name: string; quantity: number; unit_price: string; line_total: string }[];
};

const STATUS_STYLES: Record<string, string> = {
    pending: 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400 border-amber-300',
    paid: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-400 border-emerald-300',
    cancelled: 'bg-red-100 text-red-800 dark:bg-red-900/30 dark:text-red-400 border-red-300',
    refunded: 'bg-purple-100 text-purple-800 dark:bg-purple-900/30 dark:text-purple-400 border-purple-300',
};

export default function InvoiceShow({ invoice }: { invoice: InvoiceDetail }) {
    const { can } = usePermissions();

    const cancel = () => {
        if (confirm('¿Cancelar esta factura? La orden volverá al estado pagada.')) {
            router.post(invoices.cancel(invoice.id).url, {}, { preserveScroll: true });
        }
    };

    const markAsPaid = () => {
        router.post(invoices.markAsPaid(invoice.id).url, {}, { preserveScroll: true });
    };

    return (
        <>
            <Head title={`Factura ${invoice.number}`} />

            <div className="rounded-lg border border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-900">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-3">
                            <h1 className="text-2xl font-semibold">Factura {invoice.number}</h1>
                            <Badge className={STATUS_STYLES[invoice.status] ?? ''}>
                                {statusLabel(invoice.status)}
                            </Badge>
                        </div>
                        <p className="mt-1.5 text-sm text-neutral-500">
                            {invoice.store} &middot; {invoice.website}
                            {invoice.invoiced_at && <> &middot; {invoice.invoiced_at}</>}
                        </p>
                    </div>
                    <div className="text-right">
                        <p className="text-2xl font-bold">{invoice.total} {invoice.currency}</p>
                    </div>
                </div>

                <div className="mt-4 flex flex-wrap items-center gap-2 border-t border-neutral-100 pt-4 dark:border-neutral-800">
                    {can('sales.invoices.edit') && invoice.status === 'pending' && (
                        <Button className="bg-emerald-600 hover:bg-emerald-700" size="sm" onClick={markAsPaid}>
                            Marcar como pagada
                        </Button>
                    )}
                    {can('sales.invoices.cancel') && invoice.status === 'pending' && (
                        <Button variant="destructive" size="sm" onClick={cancel}>
                            Cancelar factura
                        </Button>
                    )}
                    <Button variant="outline" size="sm" asChild className="ml-auto">
                        <Link href={invoices.index().url}>Volver</Link>
                    </Button>
                </div>
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
                                {invoice.items.map((item) => (
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
                                <dd>{invoice.subtotal}</dd>
                            </div>
                            {Number(invoice.discount) > 0 && (
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Descuento</dt>
                                    <dd className="text-red-600">-{invoice.discount}</dd>
                                </div>
                            )}
                            <div className="flex justify-between">
                                <dt className="text-neutral-500">Envío</dt>
                                <dd>{invoice.shipping_amount}</dd>
                            </div>
                            {Number(invoice.tax) > 0 && (
                                <div className="flex justify-between">
                                    <dt className="text-neutral-500">Impuestos</dt>
                                    <dd>{invoice.tax}</dd>
                                </div>
                            )}
                            <div className="flex justify-between border-t border-neutral-200 pt-2 text-base font-semibold dark:border-neutral-700">
                                <dt>Total</dt>
                                <dd>{invoice.total} {invoice.currency}</dd>
                            </div>
                        </dl>
                    </section>
                </div>

                <div className="space-y-6">
                    <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                        <h3 className="mb-3 text-sm font-semibold">Orden relacionada</h3>
                        <Link
                            href={orders.show(invoice.order_id).url}
                            className="flex items-center gap-2 rounded-md border border-neutral-200 p-3 text-sm hover:bg-neutral-50 dark:border-neutral-700 dark:hover:bg-neutral-800"
                        >
                            <div className="flex-1">
                                <p className="font-medium">{invoice.order_number}</p>
                                <p className="text-xs text-neutral-500">
                                    <Badge variant="outline">{statusLabel(invoice.order_status)}</Badge>
                                </p>
                            </div>
                            <svg className="h-4 w-4 text-neutral-400" fill="none" viewBox="0 0 24 24" strokeWidth="1.5" stroke="currentColor">
                                <path strokeLinecap="round" strokeLinejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </Link>
                    </div>
                </div>
            </div>
        </>
    );
}
