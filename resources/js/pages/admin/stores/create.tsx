import { Form, Head, Link } from '@inertiajs/react';
import StoreController from '@/actions/App/Http/Controllers/Admin/StoreController';
import { Button } from '@/components/ui/button';
import stores from '@/routes/admin/stores';
import type { MediaImage } from '../websites/website-fields';
import { StoreFields } from './store-fields';

export default function StoresCreate({
    websites,
    availableImages,
}: {
    websites: { id: number; name: string }[];
    availableImages: MediaImage[];
}) {
    return (
        <>
            <Head title="Nueva tienda" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nueva tienda</h1>
                <Button variant="outline" asChild>
                    <Link href={stores.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...StoreController.store.form()} className="max-w-2xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <StoreFields
                            errors={errors}
                            websites={websites}
                            availableImages={availableImages}
                        />
                        <Button disabled={processing}>Crear tienda</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
