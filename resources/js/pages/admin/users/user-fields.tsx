import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type UserDefaults = {
    name: string;
    email: string;
    roles: string[];
};

/**
 * Campos compartidos por los formularios de crear y editar usuario.
 * Los inputs son no controlados; el componente <Form> de Inertia los serializa.
 */
export function UserFields({
    errors,
    availableRoles,
    defaults,
    requirePassword,
}: {
    errors: Record<string, string>;
    availableRoles: string[];
    defaults?: UserDefaults;
    requirePassword: boolean;
}) {
    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input id="name" name="name" defaultValue={defaults?.name} required autoComplete="name" />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="email">Email</Label>
                <Input id="email" name="email" type="email" defaultValue={defaults?.email} required autoComplete="username" />
                <InputError message={errors.email} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password">
                    Contraseña {!requirePassword && <span className="text-neutral-400">(dejar en blanco para no cambiarla)</span>}
                </Label>
                <Input id="password" name="password" type="password" required={requirePassword} autoComplete="new-password" />
                <InputError message={errors.password} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="password_confirmation">Confirmar contraseña</Label>
                <Input id="password_confirmation" name="password_confirmation" type="password" autoComplete="new-password" />
            </div>

            <div className="grid gap-2">
                <Label>Roles</Label>
                <div className="grid gap-2 sm:grid-cols-2">
                    {availableRoles.map((role) => (
                        <label key={role} className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                name="roles[]"
                                value={role}
                                defaultChecked={defaults?.roles.includes(role)}
                                className="size-4 rounded border-neutral-300 dark:border-neutral-700"
                            />
                            {role}
                        </label>
                    ))}
                </div>
                <InputError message={errors.roles} />
            </div>
        </div>
    );
}
