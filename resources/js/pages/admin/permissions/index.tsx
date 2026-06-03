import { Head } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';

/**
 * Listado de solo lectura de los permisos del sistema, agrupados por módulo.
 * Los permisos se definen en el backend (RolesAndPermissionsSeeder).
 */
export default function PermissionsIndex({
    permissionGroups,
}: {
    permissionGroups: Record<string, string[]>;
}) {
    return (
        <>
            <Head title="Permisos" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Permisos</h1>
                <p className="mt-1 text-sm text-neutral-500">
                    Catálogo de permisos del sistema. Se asignan a los roles.
                </p>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                {Object.entries(permissionGroups).map(([group, permissions]) => (
                    <div
                        key={group}
                        className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <h2 className="mb-3 text-sm font-semibold capitalize">{group}</h2>
                        <div className="flex flex-wrap gap-1">
                            {permissions.map((permission) => (
                                <Badge key={permission} variant="secondary">
                                    {permission}
                                </Badge>
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}
