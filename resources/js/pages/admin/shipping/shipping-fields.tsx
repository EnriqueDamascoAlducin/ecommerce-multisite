import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ShippingMethodDefaults = {
    code: string;
    name: string;
    type: string;
    is_active: boolean;
    sort_order: number;
};

const TYPE_LABELS: Record<string, string> = {
    flat_rate: 'Tarifa fija',
    free_shipping: 'Envío gratis',
    pickup: 'Recoger en tienda',
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export function ShippingFields({
    errors,
    types,
    defaults,
    lockCode = false,
}: {
    errors: Record<string, string>;
    types: string[];
    defaults?: ShippingMethodDefaults;
    lockCode?: boolean;
}) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            <div className="grid gap-2">
                <Label htmlFor="code">Código</Label>
                <Input id="code" name="code" defaultValue={defaults?.code} readOnly={lockCode} required placeholder="ej. flat_rate, pickup" />
                <InputError message={errors.code} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input id="name" name="name" defaultValue={defaults?.name} required />
                <InputError message={errors.name} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="type">Tipo</Label>
                <select id="type" name="type" defaultValue={defaults?.type ?? 'flat_rate'} className={fieldClass}>
                    {types.map((t) => (
                        <option key={t} value={t}>
                            {TYPE_LABELS[t] ?? t}
                        </option>
                    ))}
                </select>
                <InputError message={errors.type} />
            </div>
            <div className="grid gap-2">
                <Label htmlFor="sort_order">Orden</Label>
                <Input id="sort_order" name="sort_order" type="number" min={0} defaultValue={defaults?.sort_order ?? 0} />
            </div>
            <label className="flex items-center gap-2 text-sm">
                <input type="hidden" name="is_active" value="0" />
                <input type="checkbox" name="is_active" value="1" defaultChecked={defaults?.is_active ?? true} className="size-4 rounded" />
                Activo
            </label>
        </div>
    );
}
