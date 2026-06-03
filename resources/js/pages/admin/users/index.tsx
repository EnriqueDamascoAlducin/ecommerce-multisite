import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import users from '@/routes/admin/users';

type UserRow = {
    id: number;
    name: string;
    email: string;
    roles: string[];
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    from: number | null;
    to: number | null;
    total: number;
};

export default function UsersIndex({ users: page }: { users: Paginated<UserRow> }) {
    const { can } = usePermissions();

    const destroy = (user: UserRow) => {
        if (confirm(`¿Eliminar al usuario ${user.email}?`)) {
            router.delete(users.destroy(user.id).url);
        }
    };

    return (
        <>
            <Head title="Usuarios" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Usuarios</h1>
                {can('admin.users.create') && (
                    <Button asChild>
                        <Link href={users.create()}>Nuevo usuario</Link>
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Email</th>
                            <th className="px-4 py-3 font-medium">Roles</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {page.data.map((user) => (
                            <tr key={user.id}>
                                <td className="px-4 py-3">{user.name}</td>
                                <td className="px-4 py-3 text-neutral-500">{user.email}</td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-wrap gap-1">
                                        {user.roles.map((role) => (
                                            <Badge key={role} variant="secondary">
                                                {role}
                                            </Badge>
                                        ))}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {can('admin.users.edit') && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={users.edit(user.id)}>Editar</Link>
                                            </Button>
                                        )}
                                        {can('admin.users.delete') && (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => destroy(user)}
                                            >
                                                Eliminar
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {page.data.length === 0 && (
                            <tr>
                                <td colSpan={4} className="px-4 py-8 text-center text-neutral-500">
                                    No hay usuarios.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-neutral-500">
                <span>
                    {page.from ?? 0}–{page.to ?? 0} de {page.total}
                </span>
                <div className="flex gap-2">
                    {page.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.prev_page_url} preserveScroll>
                                Anterior
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Anterior
                        </Button>
                    )}
                    {page.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.next_page_url} preserveScroll>
                                Siguiente
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Siguiente
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}
