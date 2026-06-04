import InputError from '@/components/input-error';
import { ProductLabels } from '@/components/product-labels';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ProductLabelData = {
    website_id: number | string;
    text: string;
    text_color: string;
    background_color: string;
    is_active: boolean;
    sort_order: string | number;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function ProductLabelFields({
    data,
    setData,
    errors,
    websites,
}: {
    data: ProductLabelData;
    setData: (key: keyof ProductLabelData, value: string | number | boolean) => void;
    errors: Record<string, string>;
    websites: { id: number; name: string }[];
}) {
    return (
        <div className="space-y-5">
            <div className="grid gap-2">
                <Label htmlFor="website_id">Sitio (website)</Label>
                <select
                    id="website_id"
                    value={data.website_id}
                    onChange={(e) => setData('website_id', e.target.value)}
                    className={fieldClass}
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

            <div className="grid gap-2">
                <Label htmlFor="text">Texto</Label>
                <Input id="text" value={data.text} maxLength={50} onChange={(e) => setData('text', e.target.value)} required />
                <InputError message={errors.text} />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="text_color">Color de letra</Label>
                    <div className="flex items-center gap-2">
                        <input
                            type="color"
                            value={data.text_color}
                            onChange={(e) => setData('text_color', e.target.value)}
                            className="h-9 w-12 rounded border border-neutral-300 dark:border-neutral-700"
                        />
                        <Input value={data.text_color} onChange={(e) => setData('text_color', e.target.value)} className="font-mono" />
                    </div>
                    <InputError message={errors.text_color} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="background_color">Color de fondo</Label>
                    <div className="flex items-center gap-2">
                        <input
                            type="color"
                            value={data.background_color}
                            onChange={(e) => setData('background_color', e.target.value)}
                            className="h-9 w-12 rounded border border-neutral-300 dark:border-neutral-700"
                        />
                        <Input value={data.background_color} onChange={(e) => setData('background_color', e.target.value)} className="font-mono" />
                    </div>
                    <InputError message={errors.background_color} />
                </div>
            </div>

            <div className="grid gap-2">
                <Label>Vista previa</Label>
                <ProductLabels
                    labels={[{ text: data.text || 'Etiqueta', text_color: data.text_color, background_color: data.background_color }]}
                />
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="sort_order">Orden</Label>
                    <Input
                        id="sort_order"
                        type="number"
                        min={0}
                        value={data.sort_order}
                        onChange={(e) => setData('sort_order', e.target.value)}
                    />
                    <InputError message={errors.sort_order} />
                </div>
                <label className="flex items-end gap-2 pb-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.is_active}
                        onChange={(e) => setData('is_active', e.target.checked)}
                        className="size-4 rounded"
                    />
                    Activa
                </label>
            </div>
        </div>
    );
}
