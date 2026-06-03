import { Form, Head, Link } from '@inertiajs/react';
import WebsiteController from '@/actions/App/Http/Controllers/Admin/WebsiteController';
import { Button } from '@/components/ui/button';
import websites from '@/routes/admin/websites';
import { WebsiteFields } from './website-fields';

type EditableWebsite = {
    id: number;
    code: string;
    name: string;
    is_default: boolean;
    sort_order: number;
};

export default function WebsitesEdit({ website }: { website: EditableWebsite }) {
    return (
        <>
            <Head title={`Editar ${website.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar website</h1>
                <Button variant="outline" asChild>
                    <Link href={websites.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...WebsiteController.update.form(website.id)} className="max-w-xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <WebsiteFields
                            errors={errors}
                            defaults={{
                                code: website.code,
                                name: website.name,
                                is_default: website.is_default,
                                sort_order: website.sort_order,
                            }}
                        />
                        <Button disabled={processing}>Guardar cambios</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
