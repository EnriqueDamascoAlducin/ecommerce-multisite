import { Form, Head, Link } from '@inertiajs/react';
import ShippingMethodController from '@/actions/App/Http/Controllers/Admin/ShippingMethodController';
import { Button } from '@/components/ui/button';
import shipping from '@/routes/admin/shipping';
import { ShippingFields, type ShippingMethodDefaults } from './shipping-fields';

type EditableMethod = ShippingMethodDefaults & { id: number };

export default function ShippingEdit({ method, types }: { method: EditableMethod; types: string[] }) {
    return (
        <>
            <Head title={`Editar ${method.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar método de envío</h1>
                <Button variant="outline" asChild>
                    <Link href={shipping.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...ShippingMethodController.update.form(method.id)} className="max-w-2xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <ShippingFields errors={errors} types={types} defaults={method} lockCode />
                        <Button disabled={processing}>Guardar cambios</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
