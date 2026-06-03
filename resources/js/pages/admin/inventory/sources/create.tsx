import { Form, Head, Link } from '@inertiajs/react';
import InventorySourceController from '@/actions/App/Http/Controllers/Admin/InventorySourceController';
import { Button } from '@/components/ui/button';
import inventorySources from '@/routes/admin/inventory-sources';
import { SourceFields } from './source-fields';

export default function SourcesCreate() {
    return (
        <>
            <Head title="Nuevo almacén" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo almacén</h1>
                <Button variant="outline" asChild>
                    <Link href={inventorySources.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...InventorySourceController.store.form()} className="max-w-2xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <SourceFields errors={errors} />
                        <Button disabled={processing}>Crear almacén</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
