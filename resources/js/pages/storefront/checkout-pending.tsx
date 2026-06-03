import { Head, Link } from '@inertiajs/react';
import { Clock } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatPrice, useStoreUrls } from '@/lib/storefront';

type OrderSummary = {
    number: string;
    status: string;
    email: string;
    total: string;
    payment_method: string | null;
};

export default function CheckoutPending({ order }: { order: OrderSummary }) {
    const urls = useStoreUrls();

    return (
        <>
            <Head title={`Pago pendiente ${order.number}`} />

            <div className="mx-auto max-w-2xl text-center">
                <Clock className="mx-auto size-14 text-amber-500" />
                <h1 className="mt-4 text-2xl font-semibold">Pago pendiente</h1>
                <p className="mt-2 text-neutral-500">
                    Tu pedido <span className="font-mono font-medium">{order.number}</span> por
                    <span className="font-medium"> {formatPrice(order.total)}</span> está esperando la confirmación del pago.
                </p>
                <p className="mt-2 text-sm text-neutral-400">
                    En cuanto recibamos el pago, actualizaremos el estado y te avisaremos a {order.email}.
                </p>
                <Button className="mt-6" asChild>
                    <Link href={urls.home()}>Volver a la tienda</Link>
                </Button>
            </div>
        </>
    );
}
