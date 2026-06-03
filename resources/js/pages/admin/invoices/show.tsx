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

export default function InvoiceShow({ invoice }: { invoice: InvoiceDetail }) {
    const { can } = usePermissions();

    const cancel = () => {
        if (confirm('¿Cancelar esta factura? La orden volverá al estado pagada.')) {
            router.post(invoices.cancel(invoice.id).url, {}, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title={`Factura ${invoice.number}`} />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Factura {invoice.number}</h1>
                    <p className="text-sm text-neutral-500">{invoice.store} · {invoice.website} · {invoice.invoiced_at}</p>
                </div>
                <div className="flex items-center gap-2">
                    <Badge>{statusLabel(invoice.status)}</Badge>
                    <Button variant="outline" asChild><Link href={invoices.index().url}>Volver</Link></Button>
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
                                {invoice.items.map((item) => (
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
                            <div className="flex justify-between"><dt className="text-neutral-500">Subtotal</dt><dd>{invoice.subtotal}</dd></div>
                            <div className="flex justify-between"><dt className="text-neutral-500">Envío</dt><dd>{invoice.shipping_amount}</dd></div>
                            <div className="flex justify-between text-base font-semibold"><dt>Total</dt><dd>{invoice.total} {invoice.currency}</dd></div>
                        </dl>
                    </section>

                    <div className="rounded-lg border border-neutral-200 p-4 text-sm dark:border-neutral-800">
                        <h3 className="mb-1 font-medium">Orden relacionada</h3>
                        <p className="text-neutral-500">
                            <Link href={orders.show(invoice.order_id).url} className="font-medium hover:underline">{invoice.order_number}</Link>
                            <span className="ml-2"><Badge variant="outline">{statusLabel(invoice.order_status)}</Badge></span>
                        </p>
                    </div>
                </div>

                <aside className="space-y-6">
                    {can('sales.invoices.cancel') && invoice.status === 'pending' && (
                        <Button variant="destructive" className="w-full" onClick={cancel}>Cancelar factura</Button>
                    )}
                </aside>
            </div>
        </>
    );
}
