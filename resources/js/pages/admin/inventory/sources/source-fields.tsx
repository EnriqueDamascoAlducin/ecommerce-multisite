import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type SourceDefaults = {
    code: string;
    name: string;
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
};

export function SourceFields({
    errors,
    defaults,
    lockCode = false,
}: {
    errors: Record<string, string>;
    defaults?: SourceDefaults;
    lockCode?: boolean;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="code">Código</Label>
                <Input id="code" name="code" defaultValue={defaults?.code} readOnly={lockCode} required placeholder="ej. cdmx, bodega_norte" />
                <InputError message={errors.code} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input id="name" name="name" defaultValue={defaults?.name} required />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="sort_order">Orden</Label>
                <Input id="sort_order" name="sort_order" type="number" min={0} defaultValue={defaults?.sort_order ?? 0} />
            </div>
            <div className="flex items-end gap-4">
                <label className="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_default" value="0" />
                    <input type="checkbox" name="is_default" value="1" defaultChecked={defaults?.is_default ?? false} className="size-4 rounded" />
                    Por defecto
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_active" value="0" />
                    <input type="checkbox" name="is_active" value="1" defaultChecked={defaults?.is_active ?? true} className="size-4 rounded" />
                    Activo
                </label>
            </div>
        </div>
    );
}
