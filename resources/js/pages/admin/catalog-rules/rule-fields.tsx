import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export const ACTION_LABELS: Record<string, string> = {
    percent: 'Porcentaje (%)',
    fixed_amount: 'Descontar monto',
    fixed_price: 'Precio fijo',
};

export type CatalogRuleData = {
    name: string;
    description: string;
    website_id: string;
    category_id: string;
    action: string;
    value: string;
    priority: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function CatalogRuleFields({
    data,
    setData,
    errors,
    websites,
    categories,
    actions,
}: {
    data: CatalogRuleData;
    setData: (key: keyof CatalogRuleData, value: string | boolean) => void;
    errors: Partial<Record<keyof CatalogRuleData, string>>;
    websites: { id: number; name: string }[];
    categories: { id: number; label: string }[];
    actions: string[];
}) {
    return (
        <div className="grid gap-4">
            <div className="grid gap-1.5">
                <Label htmlFor="name">Nombre</Label>
                <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} required className={fieldClass} />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-1.5">
                <Label htmlFor="description">Descripción</Label>
                <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} className={fieldClass} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="website_id">Sitio</Label>
                    <select id="website_id" value={data.website_id} onChange={(e) => setData('website_id', e.target.value)} className={fieldClass}>
                        <option value="">Todos los sitios</option>
                        {websites.map((w) => (
                            <option key={w.id} value={w.id}>{w.name}</option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="category_id">Categoría</Label>
                    <select id="category_id" value={data.category_id} onChange={(e) => setData('category_id', e.target.value)} className={fieldClass}>
                        <option value="">Todo el catálogo</option>
                        {categories.map((c) => (
                            <option key={c.id} value={c.id}>{c.label}</option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="action">Acción</Label>
                    <select id="action" value={data.action} onChange={(e) => setData('action', e.target.value)} className={fieldClass}>
                        {actions.map((a) => (
                            <option key={a} value={a}>{ACTION_LABELS[a] ?? a}</option>
                        ))}
                    </select>
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="value">Valor {data.action === 'percent' ? '(%)' : '($)'}</Label>
                    <Input id="value" type="number" step="0.01" min="0" value={data.value} onChange={(e) => setData('value', e.target.value)} className={fieldClass} />
                    <InputError message={errors.value} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="priority">Prioridad</Label>
                    <Input id="priority" type="number" min="0" value={data.priority} onChange={(e) => setData('priority', e.target.value)} className={fieldClass} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="starts_at">Desde</Label>
                    <Input id="starts_at" type="date" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className={fieldClass} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="ends_at">Hasta</Label>
                    <Input id="ends_at" type="date" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} className={fieldClass} />
                    <InputError message={errors.ends_at} />
                </div>
                <label className="flex items-center gap-2 self-end pb-2 text-sm">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="size-4 rounded" />
                    Activa
                </label>
            </div>
        </div>
    );
}
