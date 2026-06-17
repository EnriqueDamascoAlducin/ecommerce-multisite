import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type StoreOption = { id: number; name: string };
type ParentOption = { id: number; label: string };

export type CategoryDefaults = {
    store_id: number;
    parent_id: number | null;
    name: string;
    slug: string | null;
    description: string | null;
    is_active: boolean;
    sort_order: number;
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export function CategoryFields({
    errors,
    stores,
    parentOptions,
    defaults,
    lockStore = false,
}: {
    errors: Record<string, string>;
    stores: StoreOption[];
    parentOptions: ParentOption[];
    defaults?: CategoryDefaults;
    lockStore?: boolean;
}) {
    return (
        <div className="space-y-6">
            <section className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="store_id">Tienda</Label>
                    <select
                        id="store_id"
                        name="store_id"
                        defaultValue={defaults?.store_id}
                        disabled={lockStore}
                        className={fieldClass}
                    >
                        {stores.map((store) => (
                            <option key={store.id} value={store.id}>
                                {store.name}
                            </option>
                        ))}
                    </select>
                    {lockStore && <input type="hidden" name="store_id" value={defaults?.store_id} />}
                    <InputError message={errors.store_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="parent_id">Categoría padre</Label>
                    <select id="parent_id" name="parent_id" defaultValue={defaults?.parent_id ?? ''} className={fieldClass}>
                        <option value="">— Raíz —</option>
                        {parentOptions.map((option) => (
                            <option key={option.id} value={option.id}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.parent_id} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="name">Nombre</Label>
                    <Input id="name" name="name" defaultValue={defaults?.name} required />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="slug">Slug (opcional)</Label>
                    <Input id="slug" name="slug" defaultValue={defaults?.slug ?? ''} placeholder="se genera del nombre" />
                    <InputError message={errors.slug} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="sort_order">Orden</Label>
                    <Input id="sort_order" name="sort_order" type="number" min={0} defaultValue={defaults?.sort_order ?? 0} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="is_active">Estado</Label>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="hidden" name="is_active" value="0" />
                        <input type="checkbox" id="is_active" name="is_active" value="1" defaultChecked={defaults?.is_active ?? true} className="size-4 rounded" />
                        Activa
                    </label>
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="description">Descripción</Label>
                    <textarea id="description" name="description" rows={3} defaultValue={defaults?.description ?? ''} className={fieldClass} />
                </div>
            </section>

            <section>
                <h2 className="mb-3 text-sm font-semibold">SEO</h2>
                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="meta_title">Meta título</Label>
                        <Input id="meta_title" name="meta_title" defaultValue={defaults?.meta_title ?? ''} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="meta_keywords">Meta keywords</Label>
                        <Input id="meta_keywords" name="meta_keywords" defaultValue={defaults?.meta_keywords ?? ''} />
                    </div>
                    <div className="grid gap-2 sm:col-span-2">
                        <Label htmlFor="meta_description">Meta descripción</Label>
                        <textarea id="meta_description" name="meta_description" rows={2} defaultValue={defaults?.meta_description ?? ''} className={fieldClass} />
                    </div>
                </div>
            </section>
        </div>
    );
}
