import { Form, Head, Link } from '@inertiajs/react';
import RoleController from '@/actions/App/Http/Controllers/Admin/RoleController';
import { Button } from '@/components/ui/button';
import roles from '@/routes/admin/roles';
import { RoleFields } from './role-fields';

type EditableRole = {
    id: number;
    name: string;
    permissions: string[];
    protected: boolean;
};

export default function RolesEdit({
    role,
    availablePermissions,
}: {
    role: EditableRole;
    availablePermissions: string[];
}) {
    return (
        <>
            <Head title={`Editar ${role.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar rol</h1>
                <Button variant="outline" asChild>
                    <Link href={roles.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...RoleController.update.form(role.id)} className="max-w-3xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <RoleFields
                            errors={errors}
                            availablePermissions={availablePermissions}
                            defaults={{ name: role.name, permissions: role.permissions }}
                        />
                        <Button disabled={processing}>Guardar cambios</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
