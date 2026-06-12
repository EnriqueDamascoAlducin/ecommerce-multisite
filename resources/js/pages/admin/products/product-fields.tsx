import { router } from '@inertiajs/react';
import {
    BadgeDollarSign,
    CheckCircle2,
    ChevronDown,
    ChevronUp,
    FileText,
    ImageIcon,
    Layers3,
    Link2,
    Package,
    Pencil,
    Settings2,
    Store,
    Tags,
    Trash2,
    UploadCloud,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import DownloadableController from '@/actions/App/Http/Controllers/Admin/DownloadableController';
import ProductVariantController from '@/actions/App/Http/Controllers/Admin/ProductVariantController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
type RelatedProduct = {
    id: number;
    sku: string;
    name: string;
    status: string;
    type: string;
};

type LabelOption = {
    id: number;
    text: string;
    text_color: string;
    background_color: string;
    website: string | null;
};

type BundleItemDefault = {
    product_id: number;
    sku?: string;
    name?: string;
    quantity: number;
};

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
    upsell_products?: number[];
    cross_sell_products?: number[];
    attribute_values: Record<number, string | string[]>;
    configurable_attributes?: number[];
    price_type?: string | null;
    bundle_items?: BundleItemDefault[];
    downloadable_links?: DownloadableLinkDefault[];
    variants?: VariantEditable[];
    variant_candidates?: VariantCandidate[];
};

type VariantEditable = {
    id: number;
    sku: string;
    name: string;
    status: string;
    price: string | null;
    stock_qty: number;
    media_id: number | null;
    edit_url: string;
    options: Record<string, string>;
};

type VariantCandidate = {
    id: number;
    sku: string;
    name: string;
    options: Record<string, string>;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900';

const mutedBoxClass =
    'rounded-lg border border-neutral-200 bg-neutral-50/70 p-3 text-sm text-neutral-500 dark:border-neutral-800 dark:bg-neutral-950/40 dark:text-neutral-400';

export function ProductFields({
    errors,
    stores,
    availableImages,
    categories,
    attributes,
    configurableAttributes,
    componentProducts,
    relatedProducts,
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
    relatedProducts?: RelatedProduct[];
    labels?: LabelOption[];
    defaults?: ProductDefaults;
}) {
    const [selected, setSelected] = useState<number[]>(defaults?.media ?? []);
    const [selectedCategories, setSelectedCategories] = useState<number[]>(
        defaults?.categories ?? [],
    );
    const [selectedLabels, setSelectedLabels] = useState<number[]>(
        defaults?.labels ?? [],
    );
    const [upsellProducts, setUpsellProducts] = useState<number[]>(
        defaults?.upsell_products ?? [],
    );
    const [crossSellProducts, setCrossSellProducts] = useState<number[]>(
        defaults?.cross_sell_products ?? [],
    );
    const [productType, setProductType] = useState(defaults?.type ?? 'simple');
    const [configurableAttrIds, setConfigurableAttrIds] = useState<number[]>(
        defaults?.configurable_attributes ?? [],
    );
    const [priceType, setPriceType] = useState(
        defaults?.price_type ?? 'dynamic',
    );
    const [bundleItems, setBundleItems] = useState<BundleItemDefault[]>(
        defaults?.bundle_items ?? [],
    );
    const [downloadableLinks, setDownloadableLinks] = useState<
        DownloadableLinkDefault[]
    >(defaults?.downloadable_links ?? []);
    const [variants, setVariants] = useState<VariantEditable[]>(
        defaults?.variants ?? [],
    );
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);

    const patchVariant = (
        id: number,
        field: keyof VariantEditable,
        value: string | number | null,
    ) => {
        setVariants((current) =>
            current.map((variant) =>
                variant.id === id ? { ...variant, [field]: value } : variant,
            ),
        );
    };

    const addBundleItem = (productId: number) => {
        if (
            productId === 0 ||
            bundleItems.some((item) => item.product_id === productId)
        ) {
            return;
        }

        const product = componentProducts?.find(
            (item) => item.id === productId,
        );

        setBundleItems((current) => [
            ...current,
            {
                product_id: productId,
                sku: product?.sku,
                name: product?.name,
                quantity: 1,
            },
        ]);
    };

    const setBundleQty = (productId: number, quantity: number) => {
        setBundleItems((current) =>
            current.map((item) =>
                item.product_id === productId
                    ? { ...item, quantity: Math.max(1, quantity) }
                    : item,
            ),
        );
    };

    const removeBundleItem = (productId: number) => {
        setBundleItems((current) =>
            current.filter((item) => item.product_id !== productId),
        );
    };

    const addRelatedProduct = (
        setter: (value: (current: number[]) => number[]) => void,
        productId: number,
    ) => {
        if (productId === 0 || productId === defaults?.id) {
            return;
        }

        setter((current) =>
            current.includes(productId) ? current : [...current, productId],
        );
    };

    const removeRelatedProduct = (
        setter: (value: (current: number[]) => number[]) => void,
        productId: number,
    ) => {
        setter((current) => current.filter((id) => id !== productId));
    };

    const moveRelatedProduct = (
        setter: (value: (current: number[]) => number[]) => void,
        index: number,
        direction: -1 | 1,
    ) => {
        setter((current) => {
            const nextIndex = index + direction;

            if (nextIndex < 0 || nextIndex >= current.length) {
                return current;
            }

            const next = [...current];
            [next[index], next[nextIndex]] = [next[nextIndex], next[index]];

            return next;
        });
    };

    const uploadDownloadable = async (file: File) => {
        setUploading(true);
        setUploadError(null);

        try {
            const form = new FormData();
            form.append('file', file);

            const xsrf = decodeURIComponent(
                document.cookie
                    .split('; ')
                    .find((row) => row.startsWith('XSRF-TOKEN='))
                    ?.split('=')[1] ?? '',
            );

            const response = await fetch(DownloadableController.upload.url(), {
                method: 'POST',
                body: form,
                credentials: 'same-origin',
                headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
            });

            if (!response.ok) {
                throw new Error('Upload failed');
            }

            const data = (await response.json()) as {
                file_path: string;
                original_name: string;
            };

            setDownloadableLinks((current) => [
                ...current,
                {
                    title: data.original_name,
                    file_path: data.file_path,
                    original_name: data.original_name,
                    max_downloads: null,
                },
            ]);
        } catch {
            setUploadError('No se pudo subir el archivo. Intentalo de nuevo.');
        } finally {
            setUploading(false);
        }
    };

    const setLinkField = (
        index: number,
        field: 'title' | 'max_downloads',
        value: string,
    ) => {
        setDownloadableLinks((current) =>
            current.map((link, currentIndex) =>
                currentIndex === index
                    ? {
                          ...link,
                          [field]:
                              field === 'max_downloads'
                                  ? value === ''
                                      ? null
                                      : Number(value)
                                  : value,
                      }
                    : link,
            ),
        );
    };

    const removeDownloadable = (index: number) => {
        setDownloadableLinks((current) =>
            current.filter((_, currentIndex) => currentIndex !== index),
        );
    };

    const toggleImage = (id: number) => {
        setSelected((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    const toggleCategory = (id: number) => {
        setSelectedCategories((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    const toggleLabel = (id: number) => {
        setSelectedLabels((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    const toggleConfigurableAttr = (id: number) => {
        setConfigurableAttrIds((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id],
        );
    };

    const attributeDefault = (id: number): string | string[] | undefined =>
        defaults?.attribute_values?.[id];

    const storeDefault = (storeId: number): StoreDefault | undefined =>
        defaults?.stores.find((store) => store.store_id === storeId);

    const sections = [
        { id: 'general', label: 'General', icon: Package },
        { id: 'descriptions', label: 'Descripciones', icon: FileText },
        { id: 'pricing', label: 'Precios', icon: BadgeDollarSign },
        { id: 'stores', label: 'Tiendas', icon: Store },
        { id: 'taxonomy', label: 'Categorias & labels', icon: Tags },
        { id: 'related', label: 'Venta relacionada', icon: Link2 },
        ...(attributes.length > 0
            ? [{ id: 'attributes', label: 'Atributos', icon: Settings2 }]
            : []),
        { id: 'media', label: 'Media', icon: ImageIcon },
        ...(productType !== 'simple' || (defaults?.variants?.length ?? 0) > 0
            ? [
                  {
                      id: 'special',
                      label: specialSectionLabel(productType),
                      icon: Layers3,
                  },
              ]
            : []),
    ];

    const scrollToSection = (id: string) => {
        document
            .getElementById(id)
            ?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    return (
        <div className="space-y-6">
            <div className="sticky top-14 z-20 -mx-1 overflow-x-auto border-y border-neutral-200 bg-neutral-50/95 px-1 py-3 backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/90">
                <div className="flex min-w-max gap-2">
                    {sections.map((section) => (
                        <button
                            key={section.id}
                            type="button"
                            onClick={() => scrollToSection(section.id)}
                            className="inline-flex h-9 items-center gap-2 rounded-md border border-neutral-200 bg-white px-3 text-sm font-medium text-neutral-700 shadow-sm transition hover:border-red-200 hover:text-red-800 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-200 dark:hover:border-red-900"
                        >
                            <section.icon className="size-4" />
                            {section.label}
                        </button>
                    ))}
                </div>
            </div>

            <ProductSection
                id="general"
                icon={Package}
                title="General"
                description="Identidad comercial, tipo de producto y datos base del catalogo."
                aside={
                    <div className="space-y-3">
                        <Badge variant="outline" className="w-fit">
                            {typeLabel(productType)}
                        </Badge>
                        <p className="text-xs text-neutral-500">
                            El tipo controla secciones especiales como
                            variantes, paquete o archivos digitales.
                        </p>
                    </div>
                }
            >
                <div className="grid gap-4 lg:grid-cols-4">
                    <Field label="Tipo de producto" htmlFor="type">
                        {defaults?.id ? (
                            <div className="flex h-10 items-center rounded-md border border-neutral-200 bg-neutral-100 px-3 text-sm text-neutral-600 dark:border-neutral-700 dark:bg-neutral-800 dark:text-neutral-400">
                                <input type="hidden" name="type" value={productType} />
                                {typeLabel(productType)}
                            </div>
                        ) : (
                            <select
                                id="type"
                                name="type"
                                value={productType}
                                onChange={(event) =>
                                    setProductType(event.target.value)
                                }
                                className={fieldClass}
                            >
                                <option value="simple">Producto simple</option>
                                <option value="configurable">
                                    Producto configurable
                                </option>
                                <option value="bundle">Paquete (bundle)</option>
                                <option value="downloadable">
                                    Descargable (digital)
                                </option>
                            </select>
                        )}
                    </Field>
                    {productType === 'bundle' && (
                        <Field label="Precio del paquete" htmlFor="price_type">
                            <select
                                id="price_type"
                                name="price_type"
                                value={priceType}
                                onChange={(event) =>
                                    setPriceType(event.target.value)
                                }
                                className={fieldClass}
                            >
                                <option value="dynamic">Dinamico</option>
                                <option value="fixed">Fijo</option>
                            </select>
                        </Field>
                    )}
                    <Field label="SKU" htmlFor="sku" error={errors.sku}>
                        <Input
                            id="sku"
                            name="sku"
                            defaultValue={defaults?.sku}
                            required
                        />
                    </Field>
                    <Field label="Nombre" htmlFor="name" error={errors.name}>
                        <Input
                            id="name"
                            name="name"
                            defaultValue={defaults?.name}
                            required
                        />
                    </Field>
                    <Field label="Slug" htmlFor="slug" error={errors.slug}>
                        <Input
                            id="slug"
                            name="slug"
                            defaultValue={defaults?.slug ?? ''}
                            placeholder="se genera del nombre"
                        />
                    </Field>
                    <Field label="Peso (kg)" htmlFor="weight">
                        <Input
                            id="weight"
                            name="weight"
                            type="number"
                            step="0.001"
                            min={0}
                            defaultValue={defaults?.weight ?? ''}
                        />
                    </Field>
                </div>
            </ProductSection>

            <ProductSection
                id="descriptions"
                icon={FileText}
                title="Descripciones"
                description="Texto para resultados, ficha del producto y contenido tecnico."
            >
                <div className="grid gap-4">
                    <Field
                        label="Descripcion corta"
                        htmlFor="short_description"
                    >
                        <Input
                            id="short_description"
                            name="short_description"
                            defaultValue={defaults?.short_description ?? ''}
                            placeholder="Resumen breve para listados y previews"
                        />
                    </Field>
                    <Field label="Descripcion completa" htmlFor="description">
                        <textarea
                            id="description"
                            name="description"
                            rows={7}
                            defaultValue={defaults?.description ?? ''}
                            className={fieldClass}
                            placeholder="Descripcion tecnica, beneficios, uso y notas comerciales"
                        />
                    </Field>
                </div>
            </ProductSection>

            <ProductSection
                id="pricing"
                icon={BadgeDollarSign}
                title="Pricing & Visibility"
                description="Precio base, promociones temporales, estado y exposicion en storefront."
            >
                {(productType === 'configurable' ||
                    (productType === 'bundle' && priceType === 'dynamic')) && (
                    <div className={mutedBoxClass}>
                        {productType === 'configurable'
                            ? 'Captura precio base; el storefront puede mostrar precio desde variantes cuando aplique.'
                            : 'El precio dinamico se calcula sumando componentes del paquete.'}
                    </div>
                )}
                <div className="grid gap-4 lg:grid-cols-4">
                    <Field label="Precio" htmlFor="price" error={errors.price}>
                        <Input
                            id="price"
                            name="price"
                            type="number"
                            step="0.01"
                            min={0}
                            defaultValue={defaults?.price ?? ''}
                            required={
                                !(
                                    productType === 'bundle' &&
                                    priceType === 'dynamic'
                                )
                            }
                        />
                    </Field>
                    <Field label="Precio especial" htmlFor="special_price">
                        <Input
                            id="special_price"
                            name="special_price"
                            type="number"
                            step="0.01"
                            min={0}
                            defaultValue={defaults?.special_price ?? ''}
                        />
                    </Field>
                    <Field label="Especial desde" htmlFor="special_price_from">
                        <Input
                            id="special_price_from"
                            name="special_price_from"
                            type="date"
                            defaultValue={defaults?.special_price_from ?? ''}
                        />
                    </Field>
                    <Field label="Especial hasta" htmlFor="special_price_to">
                        <Input
                            id="special_price_to"
                            name="special_price_to"
                            type="date"
                            defaultValue={defaults?.special_price_to ?? ''}
                        />
                    </Field>
                    <Field label="Estado" htmlFor="status">
                        <select
                            id="status"
                            name="status"
                            defaultValue={defaults?.status ?? 'active'}
                            className={fieldClass}
                        >
                            <option value="active">Activo</option>
                            <option value="inactive">Inactivo</option>
                        </select>
                    </Field>
                    <Field label="Visibilidad" htmlFor="visibility">
                        <select
                            id="visibility"
                            name="visibility"
                            defaultValue={defaults?.visibility ?? 'both'}
                            className={fieldClass}
                        >
                            <option value="both">Catalogo y busqueda</option>
                            <option value="catalog">Solo catalogo</option>
                            <option value="search">Solo busqueda</option>
                            <option value="hidden">Oculto</option>
                        </select>
                    </Field>
                </div>
            </ProductSection>

            <ProductSection
                id="stores"
                icon={Store}
                title="Tiendas"
                description="Activacion y precios por tienda. Precio vacio usa precio base."
                badge={`${stores.length} tiendas`}
            >
                {stores.length === 0 ? (
                    <EmptyState text="No hay tiendas configuradas." />
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                        <table className="w-full min-w-[920px] text-left text-sm">
                            <thead className="bg-neutral-50 text-xs text-neutral-500 dark:bg-neutral-950">
                                <tr>
                                    <th className="px-3 py-3 font-medium">
                                        Tienda
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Activo
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Precio override
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Especial
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Desde
                                    </th>
                                    <th className="px-3 py-3 font-medium">
                                        Hasta
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                {stores.map((store, index) => {
                                    const storeData = storeDefault(store.id);

                                    return (
                                        <tr key={store.id}>
                                            <td className="px-3 py-3">
                                                <input
                                                    type="hidden"
                                                    name={`stores[${index}][store_id]`}
                                                    value={store.id}
                                                />
                                                <span className="font-medium">
                                                    {store.label}
                                                </span>
                                            </td>
                                            <td className="px-3 py-3">
                                                <label className="inline-flex items-center gap-2">
                                                    <input
                                                        type="checkbox"
                                                        name={`stores[${index}][is_active]`}
                                                        value="1"
                                                        defaultChecked={
                                                            storeData?.is_active ??
                                                            false
                                                        }
                                                        className="size-4 rounded"
                                                    />
                                                    <span className="text-xs text-neutral-500">
                                                        Publicado
                                                    </span>
                                                </label>
                                            </td>
                                            <td className="px-3 py-3">
                                                <Input
                                                    name={`stores[${index}][price]`}
                                                    type="number"
                                                    step="0.01"
                                                    min={0}
                                                    placeholder="Base"
                                                    defaultValue={
                                                        storeData?.price ?? ''
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-3">
                                                <Input
                                                    name={`stores[${index}][special_price]`}
                                                    type="number"
                                                    step="0.01"
                                                    min={0}
                                                    placeholder="-"
                                                    defaultValue={
                                                        storeData?.special_price ??
                                                        ''
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-3">
                                                <Input
                                                    name={`stores[${index}][special_price_from]`}
                                                    type="date"
                                                    defaultValue={
                                                        storeData?.special_price_from ??
                                                        ''
                                                    }
                                                />
                                            </td>
                                            <td className="px-3 py-3">
                                                <Input
                                                    name={`stores[${index}][special_price_to]`}
                                                    type="date"
                                                    defaultValue={
                                                        storeData?.special_price_to ??
                                                        ''
                                                    }
                                                />
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>
                )}
            </ProductSection>

            <ProductSection
                id="taxonomy"
                icon={Tags}
                title="Categorias & labels"
                description="Clasificacion por website y badges comerciales visibles en catalogo."
            >
                {selectedCategories.map((id) => (
                    <input
                        key={id}
                        type="hidden"
                        name="categories[]"
                        value={id}
                    />
                ))}
                {selectedLabels.map((id) => (
                    <input key={id} type="hidden" name="labels[]" value={id} />
                ))}
                <div className="grid gap-6 xl:grid-cols-2">
                    <div>
                        <h3 className="mb-3 text-sm font-semibold">
                            Categorias
                        </h3>
                        {categories.length === 0 ? (
                            <EmptyState text="No hay categorias. Crealas en Catalogo > Categorias." />
                        ) : (
                            <div className="grid gap-2 sm:grid-cols-2">
                                {categories.map((category) => {
                                    const checked = selectedCategories.includes(
                                        category.id,
                                    );

                                    return (
                                        <button
                                            key={category.id}
                                            type="button"
                                            onClick={() =>
                                                toggleCategory(category.id)
                                            }
                                            className={`flex min-h-11 items-center justify-between rounded-lg border px-3 py-2 text-left text-sm transition ${
                                                checked
                                                    ? 'border-red-700 bg-red-50 text-red-900 dark:border-red-800 dark:bg-red-950/30 dark:text-red-100'
                                                    : 'border-neutral-200 bg-white text-neutral-700 hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-950 dark:text-neutral-200'
                                            }`}
                                        >
                                            <span className="truncate">
                                                {category.label}
                                            </span>
                                            {checked && (
                                                <CheckCircle2 className="size-4 shrink-0" />
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>

                    <div>
                        <h3 className="mb-3 text-sm font-semibold">
                            Etiquetas
                        </h3>
                        {(labels ?? []).length === 0 ? (
                            <EmptyState text="No hay etiquetas. Crealas en Catalogo > Etiquetas." />
                        ) : (
                            <div className="flex flex-wrap gap-3">
                                {(labels ?? []).map((label) => {
                                    const checked = selectedLabels.includes(
                                        label.id,
                                    );

                                    return (
                                        <button
                                            key={label.id}
                                            type="button"
                                            onClick={() =>
                                                toggleLabel(label.id)
                                            }
                                            className={`flex items-center gap-2 rounded-lg border p-2 text-sm transition ${
                                                checked
                                                    ? 'border-red-700 bg-red-50 dark:border-red-800 dark:bg-red-950/30'
                                                    : 'border-neutral-200 bg-white hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-950'
                                            }`}
                                        >
                                            <span
                                                className="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium"
                                                style={{
                                                    color: label.text_color,
                                                    backgroundColor:
                                                        label.background_color,
                                                }}
                                            >
                                                {label.text}
                                            </span>
                                            {label.website && (
                                                <span className="text-xs text-neutral-400">
                                                    {label.website}
                                                </span>
                                            )}
                                        </button>
                                    );
                                })}
                            </div>
                        )}
                    </div>
                </div>
            </ProductSection>

            <ProductSection
                id="related"
                icon={Link2}
                title="Venta relacionada"
                description="Selecciona productos para carruseles del PDP. El orden se respeta en storefront."
                badge={`${upsellProducts.length + crossSellProducts.length} productos`}
            >
                <div className="grid gap-5 xl:grid-cols-2">
                    <ProductRelationSelector
                        name="upsell_products"
                        title="Upsell"
                        description="Alternativas o productos superiores para aumentar ticket."
                        products={relatedProducts ?? []}
                        currentProductId={defaults?.id}
                        selectedIds={upsellProducts}
                        error={errors.upsell_products}
                        onAdd={(productId) =>
                            addRelatedProduct(setUpsellProducts, productId)
                        }
                        onMove={(index, direction) =>
                            moveRelatedProduct(
                                setUpsellProducts,
                                index,
                                direction,
                            )
                        }
                        onRemove={(productId) =>
                            removeRelatedProduct(
                                setUpsellProducts,
                                productId,
                            )
                        }
                    />
                    <ProductRelationSelector
                        name="cross_sell_products"
                        title="Cross-sell"
                        description="Complementos que ayudan a completar la compra."
                        products={relatedProducts ?? []}
                        currentProductId={defaults?.id}
                        selectedIds={crossSellProducts}
                        error={errors.cross_sell_products}
                        onAdd={(productId) =>
                            addRelatedProduct(setCrossSellProducts, productId)
                        }
                        onMove={(index, direction) =>
                            moveRelatedProduct(
                                setCrossSellProducts,
                                index,
                                direction,
                            )
                        }
                        onRemove={(productId) =>
                            removeRelatedProduct(
                                setCrossSellProducts,
                                productId,
                            )
                        }
                    />
                </div>
            </ProductSection>

            {attributes.length > 0 && (
                <ProductSection
                    id="attributes"
                    icon={Settings2}
                    title="Atributos"
                    description="Valores reutilizables del catalogo. Sirven para filtros, variantes y contenido tecnico."
                    badge={`${attributes.length} atributos`}
                >
                    <div className="grid gap-4 lg:grid-cols-2">
                        {attributes.map((attribute) => (
                            <Field
                                key={attribute.id}
                                label={attribute.name}
                                htmlFor={`attr-${attribute.id}`}
                                required={attribute.is_required}
                                error={
                                    errors[`attribute_values.${attribute.id}`]
                                }
                            >
                                <AttributeInput
                                    attribute={attribute}
                                    value={attributeDefault(attribute.id)}
                                />
                            </Field>
                        ))}
                    </div>
                </ProductSection>
            )}

            <ProductSection
                id="media"
                icon={ImageIcon}
                title="Media"
                description="Imagenes desde biblioteca. La primera seleccionada sera principal."
                badge={`${selected.length} seleccionadas`}
            >
                {selected.map((id) => (
                    <input key={id} type="hidden" name="media[]" value={id} />
                ))}
                {availableImages.length === 0 ? (
                    <EmptyState text="No hay imagenes. Subelas en Biblioteca de medios." />
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
                        {availableImages.map((image) => {
                            const isSelected = selected.includes(image.id);
                            const order = selected.indexOf(image.id);

                            return (
                                <button
                                    type="button"
                                    key={image.id}
                                    onClick={() => toggleImage(image.id)}
                                    className={`group relative aspect-square overflow-hidden rounded-lg border-2 bg-neutral-100 transition dark:bg-neutral-900 ${
                                        isSelected
                                            ? 'border-red-700 shadow-sm'
                                            : 'border-neutral-200 hover:border-neutral-400 dark:border-neutral-800'
                                    }`}
                                    title={image.name}
                                >
                                    <img
                                        src={image.url}
                                        alt={image.name}
                                        className="h-full w-full object-cover"
                                    />
                                    <span className="absolute inset-x-0 bottom-0 truncate bg-black/60 px-2 py-1 text-xs text-white opacity-0 transition group-hover:opacity-100">
                                        {image.name}
                                    </span>
                                    {isSelected && (
                                        <span className="absolute top-2 right-2 rounded-full bg-red-700 px-2 py-0.5 text-xs font-semibold text-white">
                                            {order === 0
                                                ? 'Principal'
                                                : order + 1}
                                        </span>
                                    )}
                                </button>
                            );
                        })}
                    </div>
                )}
            </ProductSection>

            {(productType !== 'simple' ||
                (defaults?.variants?.length ?? 0) > 0) && (
                <ProductSection
                    id="special"
                    icon={Layers3}
                    title={specialSectionLabel(productType)}
                    description="Configuracion especifica segun tipo de producto."
                >
                    {productType === 'configurable' && (
                        <ConfigurableAttributes
                            attributes={configurableAttributes ?? []}
                            selectedIds={configurableAttrIds}
                            onToggle={toggleConfigurableAttr}
                        />
                    )}

                    {productType === 'bundle' && (
                        <BundleItems
                            items={bundleItems}
                            products={componentProducts ?? []}
                            errors={errors}
                            onAdd={addBundleItem}
                            onSetQuantity={setBundleQty}
                            onRemove={removeBundleItem}
                        />
                    )}

                    {productType === 'downloadable' && (
                        <DownloadableLinks
                            links={downloadableLinks}
                            uploading={uploading}
                            uploadError={uploadError}
                            errors={errors}
                            onUpload={uploadDownloadable}
                            onSetField={setLinkField}
                            onRemove={removeDownloadable}
                        />
                    )}

                    {productType === 'configurable' && defaults?.id && (
                        <VariantsManager
                            productId={defaults.id}
                            variants={variants}
                            candidates={defaults.variant_candidates ?? []}
                            images={availableImages}
                            errors={errors}
                            onPatch={patchVariant}
                        />
                    )}
                </ProductSection>
            )}
        </div>
    );
}

function ConfigurableAttributes({
    attributes,
    selectedIds,
    onToggle,
}: {
    attributes: ConfigurableAttrDef[];
    selectedIds: number[];
    onToggle: (id: number) => void;
}) {
    if (attributes.length === 0) {
        return (
            <EmptyState text="No hay atributos configurables disponibles." />
        );
    }

    return (
        <div className="space-y-3">
            {selectedIds.map((id) => (
                <input
                    key={id}
                    type="hidden"
                    name="configurable_attributes[]"
                    value={id}
                />
            ))}
            <div className="grid gap-3 lg:grid-cols-2">
                {attributes.map((attribute) => {
                    const checked = selectedIds.includes(attribute.id);

                    return (
                        <button
                            key={attribute.id}
                            type="button"
                            onClick={() => onToggle(attribute.id)}
                            className={`rounded-lg border p-4 text-left transition ${
                                checked
                                    ? 'border-red-700 bg-red-50 dark:border-red-800 dark:bg-red-950/30'
                                    : 'border-neutral-200 bg-white hover:border-neutral-300 dark:border-neutral-800 dark:bg-neutral-950'
                            }`}
                        >
                            <div className="flex items-center justify-between gap-3">
                                <span className="font-medium">
                                    {attribute.name}
                                </span>
                                {checked && (
                                    <CheckCircle2 className="size-4 text-red-700" />
                                )}
                            </div>
                            <p className="mt-2 line-clamp-2 text-xs text-neutral-500">
                                {attribute.options
                                    .map((option) => option.label)
                                    .join(', ')}
                            </p>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

function ProductRelationSelector({
    name,
    title,
    description,
    products,
    currentProductId,
    selectedIds,
    error,
    onAdd,
    onMove,
    onRemove,
}: {
    name: string;
    title: string;
    description: string;
    products: RelatedProduct[];
    currentProductId?: number;
    selectedIds: number[];
    error?: string;
    onAdd: (productId: number) => void;
    onMove: (index: number, direction: -1 | 1) => void;
    onRemove: (productId: number) => void;
}) {
    const selectedProducts = selectedIds
        .map((id) => products.find((product) => product.id === id))
        .filter((product): product is RelatedProduct => Boolean(product));
    const availableProducts = products.filter(
        (product) =>
            product.id !== currentProductId && !selectedIds.includes(product.id),
    );

    return (
        <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950">
            {selectedIds.map((id) => (
                <input key={id} type="hidden" name={`${name}[]`} value={id} />
            ))}

            <div className="flex items-start justify-between gap-3">
                <div>
                    <h3 className="text-sm font-semibold">{title}</h3>
                    <p className="mt-1 text-xs leading-5 text-neutral-500">
                        {description}
                    </p>
                </div>
                <Badge variant="outline">{selectedProducts.length}</Badge>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-2">
                <select
                    value=""
                    onChange={(event) => {
                        onAdd(Number(event.target.value));
                        event.target.value = '';
                    }}
                    className={fieldClass}
                >
                    <option value="">Agregar producto...</option>
                    {availableProducts.map((product) => (
                        <option key={product.id} value={product.id}>
                            {product.name} ({product.sku})
                        </option>
                    ))}
                </select>
            </div>
            <InputError message={error} className="mt-2" />

            {selectedProducts.length === 0 ? (
                <div className="mt-4 rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-4 text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900/50">
                    No hay productos seleccionados.
                </div>
            ) : (
                <div className="mt-4 space-y-2">
                    {selectedProducts.map((product, index) => (
                        <div
                            key={product.id}
                            className="flex items-center justify-between gap-3 rounded-lg border border-neutral-200 bg-neutral-50/70 p-3 dark:border-neutral-800 dark:bg-neutral-900/60"
                        >
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-2">
                                    <span className="truncate text-sm font-medium">
                                        {product.name}
                                    </span>
                                    <Badge variant="outline">
                                        {typeLabel(product.type)}
                                    </Badge>
                                    {product.status !== 'active' && (
                                        <Badge variant="secondary">
                                            Inactivo
                                        </Badge>
                                    )}
                                </div>
                                <p className="mt-1 font-mono text-xs text-neutral-500">
                                    {product.sku}
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-1">
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={index === 0}
                                    onClick={() => onMove(index, -1)}
                                >
                                    <ChevronUp className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    disabled={index === selectedProducts.length - 1}
                                    onClick={() => onMove(index, 1)}
                                >
                                    <ChevronDown className="size-4" />
                                </Button>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => onRemove(product.id)}
                                >
                                    <Trash2 className="size-4" />
                                </Button>
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function BundleItems({
    items,
    products,
    errors,
    onAdd,
    onSetQuantity,
    onRemove,
}: {
    items: BundleItemDefault[];
    products: ComponentProduct[];
    errors: Record<string, string>;
    onAdd: (productId: number) => void;
    onSetQuantity: (productId: number, quantity: number) => void;
    onRemove: (productId: number) => void;
}) {
    return (
        <div className="space-y-4">
            {items.map((item, index) => (
                <div key={item.product_id}>
                    <input
                        type="hidden"
                        name={`bundle_items[${index}][product_id]`}
                        value={item.product_id}
                    />
                    <input
                        type="hidden"
                        name={`bundle_items[${index}][quantity]`}
                        value={item.quantity}
                    />
                </div>
            ))}

            <div className="flex flex-wrap items-center gap-2">
                <select
                    value=""
                    onChange={(event) => {
                        onAdd(Number(event.target.value));
                        event.target.value = '';
                    }}
                    className={fieldClass}
                >
                    <option value="">Agregar producto...</option>
                    {products
                        .filter(
                            (product) =>
                                !items.some(
                                    (item) => item.product_id === product.id,
                                ),
                        )
                        .map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.name} ({product.sku})
                            </option>
                        ))}
                </select>
                <Badge variant="outline">{items.length} componentes</Badge>
            </div>
            <InputError message={errors.bundle_items} />

            {items.length === 0 ? (
                <EmptyState text="Aun no hay componentes. Agrega al menos uno." />
            ) : (
                <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <table className="w-full min-w-[560px] text-left text-sm">
                        <thead className="bg-neutral-50 text-xs text-neutral-500 dark:bg-neutral-950">
                            <tr>
                                <th className="px-3 py-3 font-medium">
                                    Producto
                                </th>
                                <th className="px-3 py-3 font-medium">
                                    Cantidad
                                </th>
                                <th className="px-3 py-3 text-right font-medium">
                                    Accion
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {items.map((item) => (
                                <tr key={item.product_id}>
                                    <td className="px-3 py-3">
                                        <span className="font-medium">
                                            {item.name ?? `#${item.product_id}`}
                                        </span>
                                        {item.sku && (
                                            <span className="ml-2 font-mono text-xs text-neutral-500">
                                                {item.sku}
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        <Input
                                            type="number"
                                            min={1}
                                            value={item.quantity}
                                            onChange={(event) =>
                                                onSetQuantity(
                                                    item.product_id,
                                                    Number(event.target.value),
                                                )
                                            }
                                            className="w-24"
                                        />
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() =>
                                                onRemove(item.product_id)
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                            Quitar
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function DownloadableLinks({
    links,
    uploading,
    uploadError,
    errors,
    onUpload,
    onSetField,
    onRemove,
}: {
    links: DownloadableLinkDefault[];
    uploading: boolean;
    uploadError: string | null;
    errors: Record<string, string>;
    onUpload: (file: File) => void;
    onSetField: (
        index: number,
        field: 'title' | 'max_downloads',
        value: string,
    ) => void;
    onRemove: (index: number) => void;
}) {
    return (
        <div className="space-y-4">
            {links.map((link, index) => (
                <div key={`${link.file_path}-${index}`}>
                    {link.id !== undefined && (
                        <input
                            type="hidden"
                            name={`downloadable_links[${index}][id]`}
                            value={link.id}
                        />
                    )}
                    <input
                        type="hidden"
                        name={`downloadable_links[${index}][file_path]`}
                        value={link.file_path}
                    />
                    <input
                        type="hidden"
                        name={`downloadable_links[${index}][original_name]`}
                        value={link.original_name ?? ''}
                    />
                    <input
                        type="hidden"
                        name={`downloadable_links[${index}][title]`}
                        value={link.title}
                    />
                    {link.max_downloads != null && (
                        <input
                            type="hidden"
                            name={`downloadable_links[${index}][max_downloads]`}
                            value={link.max_downloads}
                        />
                    )}
                </div>
            ))}

            <label className="flex min-h-28 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-neutral-300 bg-neutral-50 text-sm text-neutral-500 transition hover:border-red-300 hover:bg-red-50/40 dark:border-neutral-700 dark:bg-neutral-950 dark:hover:border-red-900">
                <UploadCloud className="mb-2 size-6" />
                <span>
                    {uploading ? 'Subiendo...' : 'Subir archivo descargable'}
                </span>
                <input
                    type="file"
                    disabled={uploading}
                    className="sr-only"
                    onChange={(event) => {
                        const file = event.target.files?.[0];

                        if (file) {
                            void onUpload(file);
                        }

                        event.target.value = '';
                    }}
                />
            </label>
            {uploadError && (
                <p className="text-xs text-red-600">{uploadError}</p>
            )}
            <InputError message={errors.downloadable_links} />

            {links.length === 0 ? (
                <EmptyState text="Aun no hay archivos. Sube al menos uno." />
            ) : (
                <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <table className="w-full min-w-[720px] text-left text-sm">
                        <thead className="bg-neutral-50 text-xs text-neutral-500 dark:bg-neutral-950">
                            <tr>
                                <th className="px-3 py-3 font-medium">
                                    Titulo
                                </th>
                                <th className="px-3 py-3 font-medium">
                                    Archivo
                                </th>
                                <th className="px-3 py-3 font-medium">
                                    Limite
                                </th>
                                <th className="px-3 py-3 text-right font-medium">
                                    Accion
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {links.map((link, index) => (
                                <tr key={`${link.file_path}-${index}`}>
                                    <td className="px-3 py-3">
                                        <Input
                                            value={link.title}
                                            onChange={(event) =>
                                                onSetField(
                                                    index,
                                                    'title',
                                                    event.target.value,
                                                )
                                            }
                                        />
                                    </td>
                                    <td className="px-3 py-3 font-mono text-xs text-neutral-500">
                                        {link.original_name ?? link.file_path}
                                    </td>
                                    <td className="px-3 py-3">
                                        <Input
                                            type="number"
                                            min={1}
                                            placeholder="sin limite"
                                            value={link.max_downloads ?? ''}
                                            onChange={(event) =>
                                                onSetField(
                                                    index,
                                                    'max_downloads',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-32"
                                        />
                                    </td>
                                    <td className="px-3 py-3 text-right">
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            onClick={() => onRemove(index)}
                                        >
                                            <Trash2 className="size-4" />
                                            Quitar
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function VariantsManager({
    productId,
    variants,
    candidates,
    images,
    errors,
    onPatch,
}: {
    productId: number;
    variants: VariantEditable[];
    candidates: VariantCandidate[];
    images: ImageOption[];
    errors: Record<string, string>;
    onPatch: (
        id: number,
        field: keyof VariantEditable,
        value: string | number | null,
    ) => void;
}) {
    const [candidateId, setCandidateId] = useState(0);

    const attach = () => {
        if (candidateId === 0) {
            return;
        }

        router.post(
            ProductVariantController.attach.url({ product: productId }),
            { product_id: candidateId },
            { preserveScroll: true },
        );
    };

    const detach = (variantId: number) => {
        if (
            !window.confirm(
                '¿Desvincular esta variante? Volverá a ser un producto simple independiente.',
            )
        ) {
            return;
        }

        router.delete(
            ProductVariantController.detach.url({
                product: productId,
                variant: variantId,
            }),
            { preserveScroll: true },
        );
    };

    const optionsLabel = (options: Record<string, string>) =>
        Object.entries(options)
            .map(([code, value]) => `${code}: ${value}`)
            .join(', ');

    return (
        <div className="mt-6 space-y-4">
            <div className="flex items-center justify-between gap-3">
                <h3 className="text-sm font-semibold">Variantes</h3>
                <Badge variant="outline">{variants.length} variantes</Badge>
            </div>

            <div className="rounded-lg border border-dashed border-neutral-300 p-4 dark:border-neutral-700">
                <Label className="text-xs font-medium text-neutral-500">
                    Vincular producto existente
                </Label>
                {candidates.length === 0 ? (
                    <p className="mt-2 text-xs text-neutral-500">
                        No hay productos simple elegibles (mismo sitio, con todos
                        los atributos configurables y una combinación todavía
                        libre).
                    </p>
                ) : (
                    <div className="mt-2 flex flex-wrap items-center gap-2">
                        <select
                            className={fieldClass}
                            value={candidateId}
                            onChange={(event) =>
                                setCandidateId(Number(event.target.value))
                            }
                        >
                            <option value={0}>Selecciona un producto…</option>
                            {candidates.map((candidate) => (
                                <option key={candidate.id} value={candidate.id}>
                                    {candidate.sku} — {candidate.name} (
                                    {optionsLabel(candidate.options)})
                                </option>
                            ))}
                        </select>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={attach}
                            disabled={candidateId === 0}
                        >
                            <Link2 className="size-4" />
                            Vincular
                        </Button>
                    </div>
                )}
                <InputError message={errors.product_id} className="mt-2" />
            </div>

            {variants.length === 0 ? (
                <EmptyState text="Aún no hay variantes. Selecciona atributos configurables y guarda para generarlas, o vincula un producto existente." />
            ) : (
                <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                    <table className="w-full min-w-[880px] text-left text-sm">
                        <thead className="bg-neutral-50 text-xs text-neutral-500 dark:bg-neutral-950">
                            <tr>
                                <th className="px-3 py-3 font-medium">
                                    Opciones
                                </th>
                                <th className="px-3 py-3 font-medium">SKU</th>
                                <th className="px-3 py-3 font-medium">Precio</th>
                                <th className="px-3 py-3 font-medium">Stock</th>
                                <th className="px-3 py-3 font-medium">Imagen</th>
                                <th className="px-3 py-3 font-medium">Estado</th>
                                <th className="px-3 py-3 text-right font-medium">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {variants.map((variant, index) => (
                                <tr key={variant.id}>
                                    <td className="px-3 py-3">
                                        <input
                                            type="hidden"
                                            name={`variants[${index}][id]`}
                                            value={variant.id}
                                        />
                                        {Object.entries(variant.options).map(
                                            ([code, value]) => (
                                                <Badge
                                                    key={code}
                                                    variant="outline"
                                                    className="mr-1"
                                                >
                                                    {code}: {value}
                                                </Badge>
                                            ),
                                        )}
                                    </td>
                                    <td className="px-3 py-3">
                                        <Input
                                            name={`variants[${index}][sku]`}
                                            value={variant.sku}
                                            onChange={(event) =>
                                                onPatch(
                                                    variant.id,
                                                    'sku',
                                                    event.target.value,
                                                )
                                            }
                                            className="min-w-[160px] font-mono text-xs"
                                        />
                                    </td>
                                    <td className="px-3 py-3">
                                        <Input
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            name={`variants[${index}][price]`}
                                            value={variant.price ?? ''}
                                            onChange={(event) =>
                                                onPatch(
                                                    variant.id,
                                                    'price',
                                                    event.target.value,
                                                )
                                            }
                                            className="w-28"
                                        />
                                    </td>
                                    <td className="px-3 py-3">
                                        <Input
                                            type="number"
                                            min="0"
                                            name={`variants[${index}][stock_qty]`}
                                            value={variant.stock_qty}
                                            onChange={(event) =>
                                                onPatch(
                                                    variant.id,
                                                    'stock_qty',
                                                    Number(event.target.value),
                                                )
                                            }
                                            className="w-24"
                                        />
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center gap-2">
                                            {variant.media_id != null && (
                                                <img
                                                    src={
                                                        images.find(
                                                            (image) =>
                                                                image.id ===
                                                                variant.media_id,
                                                        )?.url
                                                    }
                                                    alt=""
                                                    className="size-9 rounded border border-neutral-200 object-cover dark:border-neutral-800"
                                                />
                                            )}
                                            <select
                                                className={fieldClass}
                                                name={`variants[${index}][media_id]`}
                                                value={variant.media_id ?? ''}
                                                onChange={(event) =>
                                                    onPatch(
                                                        variant.id,
                                                        'media_id',
                                                        event.target.value === ''
                                                            ? null
                                                            : Number(
                                                                  event.target
                                                                      .value,
                                                              ),
                                                    )
                                                }
                                            >
                                                <option value="">
                                                    Sin imagen
                                                </option>
                                                {images.map((image) => (
                                                    <option
                                                        key={image.id}
                                                        value={image.id}
                                                    >
                                                        {image.name}
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                    </td>
                                    <td className="px-3 py-3">
                                        <select
                                            className={fieldClass}
                                            name={`variants[${index}][status]`}
                                            value={variant.status}
                                            onChange={(event) =>
                                                onPatch(
                                                    variant.id,
                                                    'status',
                                                    event.target.value,
                                                )
                                            }
                                        >
                                            <option value="active">Activo</option>
                                            <option value="inactive">
                                                Inactivo
                                            </option>
                                        </select>
                                    </td>
                                    <td className="px-3 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <a
                                                href={variant.edit_url}
                                                className="inline-flex items-center gap-1 rounded-md border border-neutral-200 px-2 py-1 text-xs hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-900"
                                            >
                                                <Pencil className="size-3.5" />
                                                Editar
                                            </a>
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="sm"
                                                onClick={() =>
                                                    detach(variant.id)
                                                }
                                            >
                                                <Trash2 className="size-4" />
                                                Desvincular
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}

function ProductSection({
    id,
    icon: Icon,
    title,
    description,
    badge,
    aside,
    children,
}: {
    id: string;
    icon: LucideIcon;
    title: string;
    description: string;
    badge?: string;
    aside?: ReactNode;
    children: ReactNode;
}) {
    return (
        <section
            id={id}
            className="scroll-mt-32 rounded-lg border border-neutral-200 bg-white shadow-sm dark:border-neutral-800 dark:bg-neutral-900"
        >
            <div className="flex flex-wrap items-start justify-between gap-4 border-b border-neutral-200 px-5 py-4 dark:border-neutral-800">
                <div className="flex gap-3">
                    <div className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200">
                        <Icon className="size-4" />
                    </div>
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="font-semibold">{title}</h2>
                            {badge && <Badge variant="outline">{badge}</Badge>}
                        </div>
                        <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                            {description}
                        </p>
                    </div>
                </div>
                {aside && <div className="max-w-sm">{aside}</div>}
            </div>
            <div className="space-y-5 p-5">{children}</div>
        </section>
    );
}

function Field({
    label,
    htmlFor,
    required,
    error,
    children,
}: {
    label: string;
    htmlFor: string;
    required?: boolean;
    error?: string;
    children: ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <Label
                htmlFor={htmlFor}
                className="text-xs font-medium text-neutral-600 dark:text-neutral-300"
            >
                {label}
                {required && <span className="text-red-600"> *</span>}
            </Label>
            {children}
            <InputError message={error} />
        </div>
    );
}

function EmptyState({ text }: { text: string }) {
    return (
        <div className={mutedBoxClass}>
            <p>{text}</p>
        </div>
    );
}

function AttributeInput({
    attribute,
    value,
}: {
    attribute: AttributeDef;
    value: string | string[] | undefined;
}) {
    const name = `attribute_values[${attribute.id}]`;
    const single = Array.isArray(value) ? '' : (value ?? '');

    switch (attribute.type) {
        case 'textarea':
            return (
                <textarea
                    id={`attr-${attribute.id}`}
                    name={name}
                    rows={4}
                    defaultValue={single}
                    className={fieldClass}
                />
            );
        case 'number':
            return (
                <Input
                    id={`attr-${attribute.id}`}
                    name={name}
                    type="number"
                    step="any"
                    defaultValue={single}
                />
            );
        case 'date':
            return (
                <Input
                    id={`attr-${attribute.id}`}
                    name={name}
                    type="date"
                    defaultValue={single}
                />
            );
        case 'boolean':
            return (
                <select
                    id={`attr-${attribute.id}`}
                    name={name}
                    defaultValue={single}
                    className={fieldClass}
                >
                    <option value="">-</option>
                    <option value="1">Si</option>
                    <option value="0">No</option>
                </select>
            );
        case 'select':
            return (
                <select
                    id={`attr-${attribute.id}`}
                    name={name}
                    defaultValue={single}
                    className={fieldClass}
                >
                    <option value="">-</option>
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
                <div className="flex min-h-10 flex-wrap gap-2 rounded-md border border-neutral-200 bg-white p-2 dark:border-neutral-800 dark:bg-neutral-950">
                    {attribute.options.map((option) => (
                        <label
                            key={option.value}
                            className="inline-flex items-center gap-1.5 rounded-md border border-neutral-200 px-2 py-1 text-sm dark:border-neutral-800"
                        >
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
            return (
                <Input
                    id={`attr-${attribute.id}`}
                    name={name}
                    defaultValue={single}
                />
            );
    }
}

function specialSectionLabel(productType: string): string {
    return (
        {
            configurable: 'Configurable',
            bundle: 'Bundle',
            downloadable: 'Descargable',
        }[productType] ?? 'Tipo especial'
    );
}

function typeLabel(productType: string): string {
    return (
        {
            simple: 'Simple',
            configurable: 'Configurable',
            bundle: 'Bundle',
            downloadable: 'Descargable',
        }[productType] ?? productType
    );
}
