import { Form, Head, Link } from '@inertiajs/react';
import ShippingMethodController from '@/actions/App/Http/Controllers/Admin/ShippingMethodController';
import { Button } from '@/components/ui/button';
import shipping from '@/routes/admin/shipping';
import { ShippingFields } from './shipping-fields';

export default function ShippingCreate({ types }: { types: string[] }) {
    return (
        <>
            <Head title="Nuevo método de envío" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo método de envío</h1>
                <Button variant="outline" asChild>
                    <Link href={shipping.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...ShippingMethodController.store.form()} className="max-w-2xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <ShippingFields errors={errors} types={types} />
                        <Button disabled={processing}>Crear método</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
