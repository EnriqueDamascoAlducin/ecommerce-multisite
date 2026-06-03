import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type StoreOption = { id: number; label: string };
type ImageOption = { id: number; url: string; name: string };
type CategoryOption = { id: number; label: string };
type AttributeOption = { label: string; value: string };
type AttributeDef = {
    id: number;
    code: string;
    name: string;
    type: string;
    is_required: boolean;
    options: AttributeOption[];
};

type StoreDefault = {
    store_id: number;
    is_active: boolean;
    price: string | null;
    special_price: string | null;
    special_price_from: string | null;
    special_price_to: string | null;
};

export type ProductDefaults = {
    sku: string;
    name: string;
    slug: string | null;
    short_description: string | null;
    description: string | null;
    status: string;
    visibility: string;
    weight: string | null;
    price: string | null;
    special_price: string | null;
    special_price_from: string | null;
    special_price_to: string | null;
    stores: StoreDefault[];
    media: number[];
    categories: number[];
    attribute_values: Record<number, string | string[]>;
};

export function ProductFields({
    errors,
    stores,
    availableImages,
    categories,
    attributes,
    defaults,
}: {
    errors: Record<string, string>;
    stores: StoreOption[];
    availableImages: ImageOption[];
    categories: CategoryOption[];
    attributes: AttributeDef[];
    defaults?: ProductDefaults;
}) {
    const [selected, setSelected] = useState<number[]>(defaults?.media ?? []);
    const [selectedCategories, setSelectedCategories] = useState<number[]>(defaults?.categories ?? []);

    const toggleImage = (id: number) => {
        setSelected((current) =>
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const toggleCategory = (id: number) => {
        setSelectedCategories((current) =>
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const attributeDefault = (id: number): string | string[] | undefined => defaults?.attribute_values?.[id];

    const storeDefault = (storeId: number): StoreDefault | undefined =>
        defaults?.stores.find((s) => s.store_id === storeId);

    return (
        <div className="space-y-8">
            {/* Datos básicos */}
            <section className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="sku">SKU</Label>
                    <Input id="sku" name="sku" defaultValue={defaults?.sku} required />
                    <InputError message={errors.sku} />
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
                    <Label htmlFor="weight">Peso (kg)</Label>
                    <Input id="weight" name="weight" type="number" step="0.001" min={0} defaultValue={defaults?.weight ?? ''} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="status">Estado</Label>
                    <select id="status" name="status" defaultValue={defaults?.status ?? 'active'} className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                        <option value="active">Activo</option>
                        <option value="inactive">Inactivo</option>
                    </select>
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="visibility">Visibilidad</Label>
                    <select id="visibility" name="visibility" defaultValue={defaults?.visibility ?? 'both'} className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                        <option value="both">Catálogo y búsqueda</option>
                        <option value="catalog">Solo catálogo</option>
                        <option value="search">Solo búsqueda</option>
                        <option value="hidden">Oculto</option>
                    </select>
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="short_description">Descripción corta</Label>
                    <Input id="short_description" name="short_description" defaultValue={defaults?.short_description ?? ''} />
                </div>
                <div className="grid gap-2 sm:col-span-2">
                    <Label htmlFor="description">Descripción</Label>
                    <textarea id="description" name="description" rows={4} defaultValue={defaults?.description ?? ''} className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800" />
                </div>
            </section>

            {/* Precio base */}
            <section>
                <h2 className="mb-3 text-sm font-semibold">Precio base</h2>
                <div className="grid gap-4 sm:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="price">Precio</Label>
                        <Input id="price" name="price" type="number" step="0.01" min={0} defaultValue={defaults?.price ?? ''} required />
                        <InputError message={errors.price} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="special_price">Precio especial</Label>
                        <Input id="special_price" name="special_price" type="number" step="0.01" min={0} defaultValue={defaults?.special_price ?? ''} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="special_price_from">Desde</Label>
                        <Input id="special_price_from" name="special_price_from" type="date" defaultValue={defaults?.special_price_from ?? ''} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="special_price_to">Hasta</Label>
                        <Input id="special_price_to" name="special_price_to" type="date" defaultValue={defaults?.special_price_to ?? ''} />
                    </div>
                </div>
            </section>

            {/* Disponibilidad y precio por tienda */}
            <section>
                <h2 className="mb-1 text-sm font-semibold">Disponibilidad por tienda</h2>
                <p className="mb-3 text-xs text-neutral-500">
                    Activa el producto por tienda. Deja el precio vacío para usar el precio base.
                </p>
                <div className="space-y-3">
                    {stores.map((store, index) => {
                        const sd = storeDefault(store.id);
                        return (
                            <div key={store.id} className="grid items-end gap-3 rounded-lg border border-neutral-200 p-3 sm:grid-cols-5 dark:border-neutral-800">
                                <input type="hidden" name={`stores[${index}][store_id]`} value={store.id} />
                                <label className="flex items-center gap-2 text-sm">
                                    <input type="checkbox" name={`stores[${index}][is_active]`} value="1" defaultChecked={sd?.is_active ?? false} className="size-4 rounded" />
                                    {store.label}
                                </label>
                                <Input name={`stores[${index}][price]`} type="number" step="0.01" min={0} placeholder="precio override" defaultValue={sd?.price ?? ''} />
                                <Input name={`stores[${index}][special_price]`} type="number" step="0.01" min={0} placeholder="especial" defaultValue={sd?.special_price ?? ''} />
                                <Input name={`stores[${index}][special_price_from]`} type="date" defaultValue={sd?.special_price_from ?? ''} />
                                <Input name={`stores[${index}][special_price_to]`} type="date" defaultValue={sd?.special_price_to ?? ''} />
                            </div>
                        );
                    })}
                </div>
            </section>

            {/* Categorías */}
            <section>
                <h2 className="mb-1 text-sm font-semibold">Categorías</h2>
                <p className="mb-3 text-xs text-neutral-500">Asigna el producto a una o más categorías (por website).</p>
                {selectedCategories.map((id) => (
                    <input key={id} type="hidden" name="categories[]" value={id} />
                ))}
                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    {categories.map((category) => (
                        <label key={category.id} className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={selectedCategories.includes(category.id)}
                                onChange={() => toggleCategory(category.id)}
                                className="size-4 rounded"
                            />
                            {category.label}
                        </label>
                    ))}
                    {categories.length === 0 && (
                        <p className="text-sm text-neutral-500">No hay categorías. Créalas en la sección Categorías.</p>
                    )}
                </div>
            </section>

            {/* Atributos */}
            {attributes.length > 0 && (
                <section>
                    <h2 className="mb-1 text-sm font-semibold">Atributos</h2>
                    <p className="mb-3 text-xs text-neutral-500">Valores de los atributos reutilizables del catálogo.</p>
                    <div className="grid gap-4 sm:grid-cols-2">
                        {attributes.map((attribute) => (
                            <div key={attribute.id} className="grid gap-2">
                                <Label htmlFor={`attr-${attribute.id}`}>
                                    {attribute.name}
                                    {attribute.is_required && <span className="text-red-500"> *</span>}
                                </Label>
                                <AttributeInput attribute={attribute} value={attributeDefault(attribute.id)} />
                                <InputError message={errors[`attribute_values.${attribute.id}`]} />
                            </div>
                        ))}
                    </div>
                </section>
            )}

            {/* Galería */}
            <section>
                <h2 className="mb-1 text-sm font-semibold">Imágenes</h2>
                <p className="mb-3 text-xs text-neutral-500">
                    Selecciona imágenes de la biblioteca. La primera seleccionada es la principal.
                </p>
                {selected.map((id) => (
                    <input key={id} type="hidden" name="media[]" value={id} />
                ))}
                <div className="grid grid-cols-3 gap-3 sm:grid-cols-6">
                    {availableImages.map((image) => {
                        const isSelected = selected.includes(image.id);
                        const order = selected.indexOf(image.id);
                        return (
                            <button
                                type="button"
                                key={image.id}
                                onClick={() => toggleImage(image.id)}
                                className={`relative aspect-square overflow-hidden rounded-md border-2 ${isSelected ? 'border-blue-500' : 'border-transparent'}`}
                            >
                                <img src={image.url} alt={image.name} className="h-full w-full object-cover" />
                                {isSelected && (
                                    <span className="absolute right-1 top-1 rounded-full bg-blue-500 px-1.5 text-xs text-white">
                                        {order === 0 ? '★' : order + 1}
                                    </span>
                                )}
                            </button>
                        );
                    })}
                    {availableImages.length === 0 && (
                        <p className="col-span-full text-sm text-neutral-500">
                            No hay imágenes. Súbelas en la Biblioteca de medios.
                        </p>
                    )}
                </div>
            </section>
        </div>
    );
}

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

function AttributeInput({ attribute, value }: { attribute: AttributeDef; value: string | string[] | undefined }) {
    const name = `attribute_values[${attribute.id}]`;
    const single = Array.isArray(value) ? '' : (value ?? '');

    switch (attribute.type) {
        case 'textarea':
            return <textarea id={`attr-${attribute.id}`} name={name} rows={3} defaultValue={single} className={fieldClass} />;
        case 'number':
            return <Input id={`attr-${attribute.id}`} name={name} type="number" step="any" defaultValue={single} />;
        case 'date':
            return <Input id={`attr-${attribute.id}`} name={name} type="date" defaultValue={single} />;
        case 'boolean':
            return (
                <select id={`attr-${attribute.id}`} name={name} defaultValue={single} className={fieldClass}>
                    <option value="">—</option>
                    <option value="1">Sí</option>
                    <option value="0">No</option>
                </select>
            );
        case 'select':
            return (
                <select id={`attr-${attribute.id}`} name={name} defaultValue={single} className={fieldClass}>
                    <option value="">—</option>
                    {attribute.options.map((option) => (
                        <option key={option.value} value={option.value}>
                            {option.label}
                        </option>
                    ))}
                </select>
            );
        case 'multiselect': {
            const selected = Array.isArray(value) ? value : [];
            return (
                <div className="flex flex-wrap gap-3">
                    {attribute.options.map((option) => (
                        <label key={option.value} className="flex items-center gap-1.5 text-sm">
                            <input
                                type="checkbox"
                                name={`${name}[]`}
                                value={option.value}
                                defaultChecked={selected.includes(option.value)}
                                className="size-4 rounded"
                            />
                            {option.label}
                        </label>
                    ))}
                </div>
            );
        }
        default:
            return <Input id={`attr-${attribute.id}`} name={name} defaultValue={single} />;
    }
}
