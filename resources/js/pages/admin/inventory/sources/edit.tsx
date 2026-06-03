import { Form, Head, Link } from '@inertiajs/react';
import InventorySourceController from '@/actions/App/Http/Controllers/Admin/InventorySourceController';
import { Button } from '@/components/ui/button';
import inventorySources from '@/routes/admin/inventory-sources';
import { SourceFields, type SourceDefaults } from './source-fields';

type EditableSource = SourceDefaults & { id: number };

export default function SourcesEdit({ source }: { source: EditableSource }) {
    return (
        <>
            <Head title={`Editar ${source.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar almacén</h1>
                <Button variant="outline" asChild>
                    <Link href={inventorySources.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...InventorySourceController.update.form(source.id)} className="max-w-2xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <SourceFields errors={errors} defaults={source} lockCode />
                        <Button disabled={processing}>Guardar cambios</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
