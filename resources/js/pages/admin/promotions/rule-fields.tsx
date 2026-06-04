import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export const ACTION_LABELS: Record<string, string> = {
    percent: 'Porcentaje (%)',
    fixed: 'Monto fijo',
    free_shipping: 'Envío gratis',
};

export type RuleData = {
    name: string;
    description: string;
    website_id: string;
    coupon_code: string;
    action: string;
    value: string;
    min_subtotal: string;
    starts_at: string;
    ends_at: string;
    is_active: boolean;
    usage_limit: string;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function RuleFields({
    data,
    setData,
    errors,
    websites,
    actions,
}: {
    data: RuleData;
    setData: (key: keyof RuleData, value: string | boolean) => void;
    errors: Partial<Record<keyof RuleData, string>>;
    websites: { id: number; name: string }[];
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
                    <Label htmlFor="coupon_code">Código de cupón</Label>
                    <Input id="coupon_code" value={data.coupon_code} onChange={(e) => setData('coupon_code', e.target.value)} placeholder="Vacío = automática" className={fieldClass} />
                    <InputError message={errors.coupon_code} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="action">Acción</Label>
                    <select id="action" value={data.action} onChange={(e) => setData('action', e.target.value)} className={fieldClass}>
                        {actions.map((a) => (
                            <option key={a} value={a}>{ACTION_LABELS[a] ?? a}</option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-1.5">
                    <Label htmlFor="value">Valor {data.action === 'percent' ? '(%)' : data.action === 'fixed' ? '($)' : ''}</Label>
                    <Input id="value" type="number" step="0.01" min="0" value={data.value} onChange={(e) => setData('value', e.target.value)} disabled={data.action === 'free_shipping'} className={fieldClass} />
                    <InputError message={errors.value} />
                </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-3">
                <div className="grid gap-1.5">
                    <Label htmlFor="min_subtotal">Subtotal mínimo</Label>
                    <Input id="min_subtotal" type="number" step="0.01" min="0" value={data.min_subtotal} onChange={(e) => setData('min_subtotal', e.target.value)} className={fieldClass} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="usage_limit">Límite de usos</Label>
                    <Input id="usage_limit" type="number" min="1" value={data.usage_limit} onChange={(e) => setData('usage_limit', e.target.value)} placeholder="Sin límite" className={fieldClass} />
                </div>
                <label className="flex items-center gap-2 self-end pb-2 text-sm">
                    <input type="checkbox" checked={data.is_active} onChange={(e) => setData('is_active', e.target.checked)} className="size-4 rounded" />
                    Activa
                </label>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-1.5">
                    <Label htmlFor="starts_at">Desde</Label>
                    <Input id="starts_at" type="date" value={data.starts_at} onChange={(e) => setData('starts_at', e.target.value)} className={fieldClass} />
                </div>
                <div className="grid gap-1.5">
                    <Label htmlFor="ends_at">Hasta</Label>
                    <Input id="ends_at" type="date" value={data.ends_at} onChange={(e) => setData('ends_at', e.target.value)} className={fieldClass} />
                    <InputError message={errors.ends_at} />
                </div>
            </div>
        </div>
    );
}
