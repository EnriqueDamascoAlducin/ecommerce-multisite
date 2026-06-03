import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

/**
 * Campos compartidos por crear/editar rol. Los permisos se agrupan por módulo
 * (prefijo antes del primer punto) para legibilidad.
 */
export function RoleFields({
    errors,
    availablePermissions,
    defaults,
}: {
    errors: Record<string, string>;
    availablePermissions: string[];
    defaults?: { name: string; permissions: string[] };
}) {
    const groups = availablePermissions.reduce<Record<string, string[]>>((acc, permission) => {
        const group = permission.split('.')[0];
        (acc[group] ??= []).push(permission);

        return acc;
    }, {});

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre del rol</Label>
                <Input id="name" name="name" defaultValue={defaults?.name} required />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-3">
                <Label>Permisos</Label>
                {Object.entries(groups).map(([group, permissions]) => (
                    <fieldset
                        key={group}
                        className="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
                    >
                        <legend className="px-1 text-sm font-medium capitalize">{group}</legend>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {permissions.map((permission) => (
                                <label key={permission} className="flex items-center gap-2 text-sm">
                                    <input
                                        type="checkbox"
                                        name="permissions[]"
                                        value={permission}
                                        defaultChecked={defaults?.permissions.includes(permission)}
                                        className="size-4 rounded border-neutral-300 dark:border-neutral-700"
                                    />
                                    {permission}
                                </label>
                            ))}
                        </div>
                    </fieldset>
                ))}
                <InputError message={errors.permissions} />
            </div>
        </div>
    );
}
