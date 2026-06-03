import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export function WebsiteFields({
    errors,
    defaults,
}: {
    errors: Record<string, string>;
    defaults?: { code: string; name: string; is_default: boolean; sort_order: number };
}) {
    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="code">Código</Label>
                <Input id="code" name="code" defaultValue={defaults?.code} required placeholder="interferenciales" />
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
                <InputError message={errors.sort_order} />
            </div>

            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    name="is_default"
                    value="1"
                    defaultChecked={defaults?.is_default}
                    className="size-4 rounded border-neutral-300 dark:border-neutral-700"
                />
                Website por defecto
            </label>
        </div>
    );
}
