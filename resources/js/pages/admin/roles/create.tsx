import { Form, Head, Link } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { Button } from '@/components/ui/button';
import roles from '@/routes/admin/roles';
import { RoleFields } from './role-fields';

export default function RolesCreate({ availablePermissions }: { availablePermissions: string[] }) {
    return (
        <>
            <Head title="Nuevo rol" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo rol</h1>
                <Button variant="outline" asChild>
                    <Link href={roles.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...RoleController.store.form()} className="max-w-3xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <RoleFields errors={errors} availablePermissions={availablePermissions} />
                        <Button disabled={processing}>Crear rol</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
