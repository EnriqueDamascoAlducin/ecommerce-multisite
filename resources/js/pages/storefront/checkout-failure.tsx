import { Head, Link } from '@inertiajs/react';
import { XCircle } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { formatPrice, useStoreUrls } from '@/lib/storefront';

type OrderSummary = {
    number: string;
    status: string;
    email: string;
    total: string;
    payment_method: string | null;
};

export default function CheckoutFailure({ order }: { order: OrderSummary }) {
    const urls = useStoreUrls();

    return (
        <>
            <Head title={`Pago no completado ${order.number}`} />

            <div className="mx-auto max-w-2xl text-center">
                <XCircle className="mx-auto size-14 text-red-600" />
                <h1 className="mt-4 text-2xl font-semibold">No pudimos completar tu pago</h1>
                <p className="mt-2 text-neutral-500">
                    El pago del pedido <span className="font-mono font-medium">{order.number}</span> por
                    <span className="font-medium"> {formatPrice(order.total)}</span> no se completó o fue rechazado.
                </p>
                <p className="mt-2 text-sm text-neutral-400">
                    Tu pedido sigue registrado. Puedes intentar de nuevo o contactarnos a {order.email}.
                </p>
                <div className="mt-6 flex justify-center gap-3">
                    <Button asChild>
                        <Link href={urls.home()}>Volver a la tienda</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}
