import { Form, Head, Link } from '@inertiajs/react';
import WebsiteController from '@/actions/App/Http/Controllers/Admin/WebsiteController';
import { Button } from '@/components/ui/button';
import websites from '@/routes/admin/websites';
import { WebsiteFields } from './website-fields';

export default function WebsitesCreate() {
    return (
        <>
            <Head title="Nuevo website" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo website</h1>
                <Button variant="outline" asChild>
                    <Link href={websites.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...WebsiteController.store.form()} className="max-w-xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <WebsiteFields errors={errors} />
                        <Button disabled={processing}>Crear website</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
