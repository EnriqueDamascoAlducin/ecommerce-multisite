import { Form, Head, Link } from '@inertiajs/react';
import AttributeController from '@/actions/App/Http/Controllers/Admin/AttributeController';
import { Button } from '@/components/ui/button';
import attributes from '@/routes/admin/attributes';
import { AttributeFields } from './attribute-fields';

export default function AttributesCreate({ types }: { types: string[] }) {
    return (
        <>
            <Head title="Nuevo atributo" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo atributo</h1>
                <Button variant="outline" asChild>
                    <Link href={attributes.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...AttributeController.store.form()} className="max-w-3xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <AttributeFields errors={errors} types={types} />
                        <Button disabled={processing}>Crear atributo</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
