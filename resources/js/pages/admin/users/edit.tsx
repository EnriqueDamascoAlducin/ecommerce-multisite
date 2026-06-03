import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { Button } from '@/components/ui/button';
import users from '@/routes/admin/users';
import { UserFields } from './user-fields';

type EditableUser = {
    id: number;
    name: string;
    email: string;
    roles: string[];
};

export default function UsersEdit({
    user,
    availableRoles,
}: {
    user: EditableUser;
    availableRoles: string[];
}) {
    return (
        <>
            <Head title={`Editar ${user.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar usuario</h1>
                <Button variant="outline" asChild>
                    <Link href={users.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...UserController.update.form(user.id)} className="max-w-2xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <UserFields
                            errors={errors}
                            availableRoles={availableRoles}
                            defaults={{ name: user.name, email: user.email, roles: user.roles }}
                            requirePassword={false}
                        />
                        <Button disabled={processing}>Guardar cambios</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
