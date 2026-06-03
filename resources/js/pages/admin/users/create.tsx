import { Form, Head, Link } from '@inertiajs/react';
import UserController from '@/actions/App/Http/Controllers/Admin/UserController';
import { Button } from '@/components/ui/button';
import users from '@/routes/admin/users';
import { UserFields } from './user-fields';

export default function UsersCreate({ availableRoles }: { availableRoles: string[] }) {
    return (
        <>
            <Head title="Nuevo usuario" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo usuario</h1>
                <Button variant="outline" asChild>
                    <Link href={users.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...UserController.store.form()} className="max-w-2xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <UserFields errors={errors} availableRoles={availableRoles} requirePassword />
                        <Button disabled={processing}>Crear usuario</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
