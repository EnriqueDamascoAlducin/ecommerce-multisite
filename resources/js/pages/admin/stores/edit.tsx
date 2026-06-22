import { Form, Head, Link } from '@inertiajs/react';
import StoreController from '@/actions/App/Http/Controllers/Admin/StoreController';
import { Button } from '@/components/ui/button';
import stores from '@/routes/admin/stores';
import type { MediaImage } from '../websites/website-fields';
import { StoreFields } from './store-fields';

type EditableStore = {
    id: number;
    website_id: number;
    code: string;
    name: string;
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
    domains: string[];
    logo: { id: number; url: string } | null;
    pwa_icon: { id: number; url: string } | null;
};

export default function StoresEdit({
    store,
    websites,
    availableImages,
}: {
    store: EditableStore;
    websites: { id: number; name: string }[];
    availableImages: MediaImage[];
}) {
    return (
        <>
            <Head title={`Editar ${store.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar tienda</h1>
                <Button variant="outline" asChild>
                    <Link href={stores.index()}>Volver</Link>
                </Button>
            </div>

            <Form
                {...StoreController.update.form(store.id)}
                className="max-w-2xl"
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <StoreFields
                            errors={errors}
                            websites={websites}
                            availableImages={availableImages}
                            defaults={{
                                website_id: store.website_id,
                                code: store.code,
                                name: store.name,
                                is_default: store.is_default,
                                is_active: store.is_active,
                                sort_order: store.sort_order,
                                domains: store.domains,
                                logo: store.logo,
                                pwa_icon: store.pwa_icon,
                            }}
                        />
                        <Button disabled={processing}>Guardar cambios</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
