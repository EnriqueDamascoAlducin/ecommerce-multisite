import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import roles from '@/routes/admin/roles';

type RoleRow = {
    id: number;
    name: string;
    permissions_count: number;
    users_count: number;
    protected: boolean;
};

export default function RolesIndex({ roles: items }: { roles: RoleRow[] }) {
    const { can } = usePermissions();

    const destroy = (role: RoleRow) => {
        if (confirm(`¿Eliminar el rol ${role.name}?`)) {
            router.delete(roles.destroy(role.id).url);
        }
    };

    return (
        <>
            <Head title="Roles" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Roles</h1>
                {can('admin.roles.create') && (
                    <Button asChild>
                        <Link href={roles.create()}>Nuevo rol</Link>
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Rol</th>
                            <th className="px-4 py-3 font-medium">Permisos</th>
                            <th className="px-4 py-3 font-medium">Usuarios</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {items.map((role) => (
                            <tr key={role.id}>
                                <td className="px-4 py-3 font-medium">{role.name}</td>
                                <td className="px-4 py-3 text-neutral-500">{role.permissions_count}</td>
                                <td className="px-4 py-3 text-neutral-500">{role.users_count}</td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {can('admin.roles.edit') && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={roles.edit(role.id)}>Editar</Link>
                                            </Button>
                                        )}
                                        {can('admin.roles.delete') && !role.protected && (
                                            <Button
                                                variant="destructive"
                                                size="sm"
                                                onClick={() => destroy(role)}
                                            >
                                                Eliminar
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
