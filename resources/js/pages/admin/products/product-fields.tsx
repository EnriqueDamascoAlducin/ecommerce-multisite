import { useState } from 'react';
import DownloadableController from '@/actions/App/Http/Controllers/Admin/DownloadableController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
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
    is_configurable?: boolean;
    options: AttributeOption[];
};

type ConfigurableAttrDef = {
    id: number;
    code: string;
    name: string;
    type: string;
    options: AttributeOption[];
};

type ComponentProduct = { id: number; sku: string; name: string };

type LabelOption = { id: number; text: string; text_color: string; background_color: string; website: string | null };

type BundleItemDefault = { product_id: number; sku?: string; name?: string; quantity: number };

type DownloadableLinkDefault = {
    id?: number;
    title: string;
    file_path: string;
    original_name?: string | null;
    max_downloads?: number | null;
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
    id?: number;
    type: string;
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
    labels?: number[];
    attribute_values: Record<number, string | string[]>;
    configurable_attributes?: number[];
    price_type?: string | null;
    bundle_items?: BundleItemDefault[];
    downloadable_links?: DownloadableLinkDefault[];
    variants?: {
        id: number;
        sku: string;
        name: string;
        status: string;
        price: string | null;
        options: Record<string, string>;
    }[];
};

export function ProductFields({
    errors,
    stores,
    availableImages,
    categories,
    attributes,
    configurableAttributes,
    componentProducts,
    labels,
    defaults,
}: {
    errors: Record<string, string>;
    stores: StoreOption[];
    availableImages: ImageOption[];
    categories: CategoryOption[];
    attributes: AttributeDef[];
    configurableAttributes?: ConfigurableAttrDef[];
    componentProducts?: ComponentProduct[];
    labels?: LabelOption[];
    defaults?: ProductDefaults;
}) {
    const [selected, setSelected] = useState<number[]>(defaults?.media ?? []);
    const [selectedCategories, setSelectedCategories] = useState<number[]>(defaults?.categories ?? []);
    const [selectedLabels, setSelectedLabels] = useState<number[]>(defaults?.labels ?? []);
    const [productType, setProductType] = useState(defaults?.type ?? 'simple');
    const [configurableAttrIds, setConfigurableAttrIds] = useState<number[]>(defaults?.configurable_attributes ?? []);
    const [priceType, setPriceType] = useState(defaults?.price_type ?? 'dynamic');
    const [bundleItems, setBundleItems] = useState<BundleItemDefault[]>(defaults?.bundle_items ?? []);

    const addBundleItem = (productId: number) => {
        if (productId === 0 || bundleItems.some((i) => i.product_id === productId)) {
            return;
        }
        const product = componentProducts?.find((p) => p.id === productId);
        setBundleItems((current) => [
            ...current,
            { product_id: productId, sku: product?.sku, name: product?.name, quantity: 1 },
        ]);
    };

    const setBundleQty = (productId: number, quantity: number) => {
        setBundleItems((current) =>
            current.map((i) => (i.product_id === productId ? { ...i, quantity: Math.max(1, quantity) } : i)),
        );
    };

    const removeBundleItem = (productId: number) => {
        setBundleItems((current) => current.filter((i) => i.product_id !== productId));
    };

    const [downloadableLinks, setDownloadableLinks] = useState<DownloadableLinkDefault[]>(defaults?.downloadable_links ?? []);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const uploadDownloadable = async (file: File) => {
        setUploading(true);
        setUploadError(null);

        try {
            const form = new FormData();
            form.append('file', file);

            const xsrf = decodeURIComponent(
                document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
            );

            const response = await fetch(DownloadableController.upload.url(), {
                method: 'POST',
                body: form,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            });

            if (!response.ok) {
                throw new Error('No se pudo subir el archivo.');
            }

            const data = (await response.json()) as { file_path: string; original_name: string };

            setDownloadableLinks((current) => [
                ...current,
                { title: data.original_name, file_path: data.file_path, original_name: data.original_name, max_downloads: null },
            ]);
        } catch {
            setUploadError('No se pudo subir el archivo. Inténtalo de nuevo.');
        } finally {
            setUploading(false);
        }
    };

    const setLinkField = (index: number, field: 'title' | 'max_downloads', value: string) => {
        setDownloadableLinks((current) =>
            current.map((link, i) =>
                i === index
                    ? { ...link, [field]: field === 'max_downloads' ? (value === '' ? null : Number(value)) : value }
                    : link,
            ),
        );
    };

    const removeDownloadable = (index: number) => {
        setDownloadableLinks((current) => current.filter((_, i) => i !== index));
    };

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

    const toggleLabel = (id: number) => {
        setSelectedLabels((current) =>
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const toggleConfigurableAttr = (id: number) => {
        setConfigurableAttrIds((current) =>
            current.includes(id) ? current.filter((x) => x !== id) : [...current, id],
        );
    };

    const attributeDefault = (id: number): string | string[] | undefined => defaults?.attribute_values?.[id];

    const storeDefault = (storeId: number): StoreDefault | undefined =>
        defaults?.stores.find((s) => s.store_id === storeId);

    return (
        <div className="space-y-8">
            {/* Tipo de producto */}
            <section className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="type">Tipo de producto</Label>
                    <select
                        id="type"
                        name="type"
                        value={productType}
                        onChange={(e) => setProductType(e.target.value)}
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <option value="simple">Producto simple</option>
                        <option value="configurable">Producto configurable</option>
                        <option value="bundle">Paquete (bundle)</option>
                        <option value="downloadable">Descargable (digital)</option>
                    </select>
                </div>

                {productType === 'bundle' && (
                    <div className="grid gap-2">
                        <Label htmlFor="price_type">Precio del paquete</Label>
                        <select
                            id="price_type"
                            name="price_type"
                            value={priceType}
                            onChange={(e) => setPriceType(e.target.value)}
                            className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="dynamic">Dinámico (suma de componentes)</option>
                            <option value="fixed">Fijo (precio propio)</option>
                        </select>
                    </div>
                )}
            </section>

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

            {/* Precio base (simples y bundle fijo; configurables y bundle dinámico lo derivan) */}
            <section>
                <h2 className="mb-3 text-sm font-semibold">Precio base</h2>
                {productType === 'configurable' && (
                    <p className="mb-3 text-xs text-neutral-500">El precio se hereda de la variante más barata.</p>
                )}
                {productType === 'bundle' && priceType === 'dynamic' && (
                    <p className="mb-3 text-xs text-neutral-500">El precio se calcula sumando los componentes del paquete.</p>
                )}
                <div className="grid gap-4 sm:grid-cols-4">
                    <div className="grid gap-2">
                        <Label htmlFor="price">Precio</Label>
                        <Input
                            id="price"
                            name="price"
                            type="number"
                            step="0.01"
                            min={0}
                            defaultValue={defaults?.price ?? ''}
                            required={!(productType === 'bundle' && priceType === 'dynamic')}
                        />
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

            {/* Atributos configurables (solo para tipo configurable) */}
            {productType === 'configurable' && configurableAttributes && configurableAttributes.length > 0 && (
                <section>
                    <h2 className="mb-1 text-sm font-semibold">Atributos configurables</h2>
                    <p className="mb-3 text-xs text-neutral-500">
                        Selecciona los atributos que definen las variantes. Se generará una variante por cada combinación de opciones.
                    </p>
                    {configurableAttrIds.map((id) => (
                        <input key={id} type="hidden" name="configurable_attributes[]" value={id} />
                    ))}
                    <div className="flex flex-wrap gap-3">
                        {configurableAttributes.map((attr) => (
                            <label key={attr.id} className="flex items-center gap-2 rounded-lg border border-neutral-200 p-3 text-sm dark:border-neutral-800">
                                <input
                                    type="checkbox"
                                    checked={configurableAttrIds.includes(attr.id)}
                                    onChange={() => toggleConfigurableAttr(attr.id)}
                                    className="size-4 rounded"
                                />
                                <div>
                                    <span className="font-medium">{attr.name}</span>
                                    <span className="ml-2 text-neutral-500">({attr.options.map((o) => o.label).join(', ')})</span>
                                </div>
                            </label>
                        ))}
                    </div>
                </section>
            )}

            {/* Componentes del bundle */}
            {productType === 'bundle' && (
                <section>
                    <h2 className="mb-1 text-sm font-semibold">Componentes del paquete</h2>
                    <p className="mb-3 text-xs text-neutral-500">
                        Agrega los productos que forman el paquete y la cantidad de cada uno.
                    </p>
                    {bundleItems.map((item, index) => (
                        <div key={item.product_id}>
                            <input type="hidden" name={`bundle_items[${index}][product_id]`} value={item.product_id} />
                            <input type="hidden" name={`bundle_items[${index}][quantity]`} value={item.quantity} />
                        </div>
                    ))}
                    <div className="mb-3 flex items-center gap-2">
                        <select
                            value=""
                            onChange={(e) => {
                                addBundleItem(Number(e.target.value));
                                e.target.value = '';
                            }}
                            className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            <option value="">Agregar producto…</option>
                            {(componentProducts ?? [])
                                .filter((p) => !bundleItems.some((i) => i.product_id === p.id))
                                .map((p) => (
                                    <option key={p.id} value={p.id}>
                                        {p.name} ({p.sku})
                                    </option>
                                ))}
                        </select>
                    </div>
                    <InputError message={errors.bundle_items} />
                    {bundleItems.length > 0 ? (
                        <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <table className="w-full text-sm">
                                <thead className="bg-neutral-50 dark:bg-neutral-900">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium">Producto</th>
                                        <th className="px-3 py-2 text-left font-medium">Cantidad</th>
                                        <th className="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                    {bundleItems.map((item) => (
                                        <tr key={item.product_id}>
                                            <td className="px-3 py-2">
                                                {item.name ?? `#${item.product_id}`}
                                                {item.sku && <span className="ml-2 font-mono text-xs text-neutral-500">{item.sku}</span>}
                                            </td>
                                            <td className="px-3 py-2">
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    value={item.quantity}
                                                    onChange={(e) => setBundleQty(item.product_id, Number(e.target.value))}
                                                    className="w-20"
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => removeBundleItem(item.product_id)}
                                                    className="text-sm text-red-500 hover:underline"
                                                >
                                                    Quitar
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-sm text-neutral-500">Aún no hay componentes. Agrega al menos uno.</p>
                    )}
                </section>
            )}

            {/* Archivos descargables */}
            {productType === 'downloadable' && (
                <section>
                    <h2 className="mb-1 text-sm font-semibold">Archivos descargables</h2>
                    <p className="mb-3 text-xs text-neutral-500">
                        Sube los archivos que el cliente podrá descargar tras pagar. Deja el límite vacío para descargas ilimitadas.
                    </p>
                    {downloadableLinks.map((link, index) => (
                        <div key={index}>
                            {link.id !== undefined && (
                                <input type="hidden" name={`downloadable_links[${index}][id]`} value={link.id} />
                            )}
                            <input type="hidden" name={`downloadable_links[${index}][file_path]`} value={link.file_path} />
                            <input type="hidden" name={`downloadable_links[${index}][original_name]`} value={link.original_name ?? ''} />
                            <input type="hidden" name={`downloadable_links[${index}][title]`} value={link.title} />
                            {link.max_downloads != null && (
                                <input type="hidden" name={`downloadable_links[${index}][max_downloads]`} value={link.max_downloads} />
                            )}
                        </div>
                    ))}

                    <div className="mb-3">
                        <input
                            type="file"
                            disabled={uploading}
                            onChange={(e) => {
                                const file = e.target.files?.[0];
                                if (file) {
                                    void uploadDownloadable(file);
                                }
                                e.target.value = '';
                            }}
                            className="text-sm"
                        />
                        {uploading && <span className="ml-2 text-xs text-neutral-500">Subiendo…</span>}
                        {uploadError && <p className="mt-1 text-xs text-red-500">{uploadError}</p>}
                    </div>
                    <InputError message={errors.downloadable_links} />

                    {downloadableLinks.length > 0 ? (
                        <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                            <table className="w-full text-sm">
                                <thead className="bg-neutral-50 dark:bg-neutral-900">
                                    <tr>
                                        <th className="px-3 py-2 text-left font-medium">Título</th>
                                        <th className="px-3 py-2 text-left font-medium">Archivo</th>
                                        <th className="px-3 py-2 text-left font-medium">Límite</th>
                                        <th className="px-3 py-2"></th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                    {downloadableLinks.map((link, index) => (
                                        <tr key={index}>
                                            <td className="px-3 py-2">
                                                <Input
                                                    value={link.title}
                                                    onChange={(e) => setLinkField(index, 'title', e.target.value)}
                                                />
                                            </td>
                                            <td className="px-3 py-2 font-mono text-xs text-neutral-500">{link.original_name ?? link.file_path}</td>
                                            <td className="px-3 py-2">
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    placeholder="∞"
                                                    value={link.max_downloads ?? ''}
                                                    onChange={(e) => setLinkField(index, 'max_downloads', e.target.value)}
                                                    className="w-24"
                                                />
                                            </td>
                                            <td className="px-3 py-2 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => removeDownloadable(index)}
                                                    className="text-sm text-red-500 hover:underline"
                                                >
                                                    Quitar
                                                </button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    ) : (
                        <p className="text-sm text-neutral-500">Aún no hay archivos. Sube al menos uno.</p>
                    )}
                </section>
            )}

            {/* Variantes (solo edición de configurable) */}
            {defaults?.variants && defaults.variants.length > 0 && (
                <section>
                    <h2 className="mb-1 text-sm font-semibold">Variantes ({defaults.variants.length})</h2>
                    <p className="mb-3 text-xs text-neutral-500">
                        Variantes generadas automáticamente. Edítalas individualmente para ajustar precio o stock.
                    </p>
                    <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                        <table className="w-full text-sm">
                            <thead className="bg-neutral-50 dark:bg-neutral-900">
                                <tr>
                                    <th className="px-3 py-2 text-left font-medium">SKU</th>
                                    <th className="px-3 py-2 text-left font-medium">Opción</th>
                                    <th className="px-3 py-2 text-left font-medium">Precio</th>
                                    <th className="px-3 py-2 text-left font-medium">Estado</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-200 dark:divide-neutral-800">
                                {defaults.variants.map((variant) => (
                                    <tr key={variant.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-900">
                                        <td className="px-3 py-2 font-mono text-xs">{variant.sku}</td>
                                        <td className="px-3 py-2">
                                            {Object.entries(variant.options).map(([code, value]) => (
                                                <Badge key={code} variant="outline" className="mr-1">
                                                    {code}: {value}
                                                </Badge>
                                            ))}
                                        </td>
                                        <td className="px-3 py-2">${variant.price ?? '—'}</td>
                                        <td className="px-3 py-2">
                                            <Badge variant={variant.status === 'active' ? 'default' : 'secondary'}>
                                                {variant.status === 'active' ? 'Activo' : 'Inactivo'}
                                            </Badge>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>
            )}

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

            {/* Etiquetas (badges) */}
            <section>
                <h2 className="mb-1 text-sm font-semibold">Etiquetas</h2>
                <p className="mb-3 text-xs text-neutral-500">Resalta el producto con badges del catálogo de etiquetas (por website).</p>
                {selectedLabels.map((id) => (
                    <input key={id} type="hidden" name="labels[]" value={id} />
                ))}
                <div className="flex flex-wrap gap-3">
                    {(labels ?? []).map((label) => (
                        <label key={label.id} className="flex items-center gap-2 rounded-lg border border-neutral-200 p-2 text-sm dark:border-neutral-800">
                            <input
                                type="checkbox"
                                checked={selectedLabels.includes(label.id)}
                                onChange={() => toggleLabel(label.id)}
                                className="size-4 rounded"
                            />
                            <span
                                className="inline-flex items-center rounded-md border-transparent px-2 py-0.5 text-xs font-medium"
                                style={{ color: label.text_color, backgroundColor: label.background_color }}
                            >
                                {label.text}
                            </span>
                            {label.website && <span className="text-xs text-neutral-400">{label.website}</span>}
                        </label>
                    ))}
                    {(labels ?? []).length === 0 && (
                        <p className="text-sm text-neutral-500">No hay etiquetas. Créalas en la sección Etiquetas.</p>
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
