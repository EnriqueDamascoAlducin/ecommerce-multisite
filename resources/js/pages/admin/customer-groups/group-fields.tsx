import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type GroupData = {
    website_id: number | string;
    name: string;
    code: string;
    description: string;
    color: string;
    is_default: boolean;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function CustomerGroupFields({
    data,
    setData,
    errors,
    websites,
    lockWebsite = false,
}: {
    data: GroupData;
    setData: (key: keyof GroupData, value: string | number | boolean) => void;
    errors: Record<string, string>;
    websites: { id: number; name: string }[];
    lockWebsite?: boolean;
}) {
    return (
        <div className="space-y-5">
            <div className="grid gap-2">
                <Label htmlFor="website_id">Sitio (website)</Label>
                <select
                    id="website_id"
                    value={data.website_id}
                    onChange={(e) => setData('website_id', e.target.value)}
                    disabled={lockWebsite}
                    className={`${fieldClass} disabled:opacity-60`}
                >
                    <option value="">Selecciona un sitio…</option>
                    {websites.map((website) => (
                        <option key={website.id} value={website.id}>
                            {website.name}
                        </option>
                    ))}
                </select>
                <InputError message={errors.website_id} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="name">Nombre</Label>
                    <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} placeholder="Mayorista" />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="code">Código</Label>
                    <Input
                        id="code"
                        value={data.code}
                        onChange={(e) => setData('code', e.target.value)}
                        placeholder="mayorista"
                        className="font-mono"
                    />
                    <InputError message={errors.code} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Descripción</Label>
                <Input id="description" value={data.description} onChange={(e) => setData('description', e.target.value)} />
                <InputError message={errors.description} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="color">Color</Label>
                <div className="flex items-center gap-2">
                    <input
                        type="color"
                        value={data.color}
                        onChange={(e) => setData('color', e.target.value)}
                        className="h-9 w-12 rounded border border-neutral-300 dark:border-neutral-700"
                    />
                    <Input value={data.color} onChange={(e) => setData('color', e.target.value)} className="font-mono" />
                </div>
                <InputError message={errors.color} />
            </div>

            <div className="grid gap-2">
                <Label>Vista previa</Label>
                <Badge className="w-fit border-transparent" style={{ backgroundColor: data.color, color: '#ffffff' }}>
                    {data.name || 'Grupo'}
                </Badge>
            </div>

            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    checked={data.is_default}
                    onChange={(e) => setData('is_default', e.target.checked)}
                    className="size-4 rounded"
                />
                Grupo por defecto del sitio
            </label>
        </div>
    );
}
