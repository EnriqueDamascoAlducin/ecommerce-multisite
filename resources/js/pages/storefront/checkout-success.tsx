import { Head, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatPrice, useStoreUrls } from '@/lib/storefront';

type OrderSummary = {
    number: string;
    status: string;
    email: string;
    total: string;
    shipping_amount: string;
    subtotal: string;
    payment_method: string | null;
    items: { name: string; quantity: number; line_total: string }[];
};

export default function CheckoutSuccess({ order }: { order: OrderSummary }) {
    const urls = useStoreUrls();

    return (
        <>
            <Head title={`Pedido ${order.number}`} />

            <div className="mx-auto max-w-2xl text-center">
                <CheckCircle2 className="mx-auto size-14 text-green-600" />
                <h1 className="mt-4 text-2xl font-semibold">¡Gracias por tu compra!</h1>
                <p className="mt-2 text-neutral-500">
                    Tu pedido <span className="font-mono font-medium">{order.number}</span> fue creado y está
                    <span className="font-medium"> pendiente de pago</span>. Te enviamos la confirmación a {order.email}.
                </p>
            </div>

            <div className="mx-auto mt-8 max-w-2xl rounded-lg border border-neutral-200 p-4 text-left dark:border-neutral-800">
                <ul className="mb-4 space-y-2 text-sm">
                    {order.items.map((item, i) => (
                        <li key={i} className="flex justify-between gap-2">
                            <span className="text-neutral-500">{item.quantity} × {item.name}</span>
                            <span>{formatPrice(item.line_total)}</span>
                        </li>
                    ))}
                </ul>
                <dl className="space-y-2 border-t border-neutral-100 pt-3 text-sm dark:border-neutral-800">
                    <div className="flex justify-between"><dt className="text-neutral-500">Subtotal</dt><dd>{formatPrice(order.subtotal)}</dd></div>
                    <div className="flex justify-between"><dt className="text-neutral-500">Envío</dt><dd>{Number(order.shipping_amount) === 0 ? '—' : formatPrice(order.shipping_amount)}</dd></div>
                    <div className="flex justify-between border-t border-neutral-100 pt-2 text-base font-semibold dark:border-neutral-800"><dt>Total</dt><dd>{formatPrice(order.total)}</dd></div>
                </dl>
            </div>

            <div className="mt-6 text-center">
                <Button asChild>
                    <Link href={urls.home()}>Seguir comprando</Link>
                </Button>
            </div>
        </>
    );
}
