import { Form, Head, Link, router } from '@inertiajs/react';
import {
    ChevronDown,
    ChevronRight,
    Columns3,
    Filter,
    ImageIcon,
    Pencil,
    Search,
    X,
} from 'lucide-react';
import type { ReactNode } from 'react';
import { Fragment, useMemo, useState } from 'react';
import { toast } from 'sonner';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { ProductLabels } from '@/components/product-labels';
import type { ProductLabelData } from '@/components/product-labels';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import products from '@/routes/admin/products';

const COLUMN_STORAGE_KEY = 'admin.products.columns';

type Option = {
    value: string;
    label: string;
};

type IdOption = {
    id: number;
    label: string;
};

type AttributeFilter = {
    id: number;
    code: string;
    name: string;
    type: string;
    options: Option[];
};

type FilterOptions = {
    statuses: Option[];
    types: Option[];
    visibilities: Option[];
    categories: IdOption[];
    stores: IdOption[];
    labels: IdOption[];
    attributes: AttributeFilter[];
};

type ColumnDefinition = {
    key: string;
    label: string;
    locked?: boolean;
    attribute_id?: number;
    type?: string;
};

type ProductRow = {
    id: number;
    type: string;
    sku: string;
    name: string;
    status: string;
    visibility: string;
    parent: { id: number; name: string } | null;
    price: string | number | null;
    thumbnail: string | null;
    labels: ProductLabelData[];
    stock: {
        available: number;
        status: 'in' | 'out';
    };
    categories: Array<{ id: number; name: string }>;
    stores: Array<{
        id: number;
        name: string | null;
        code: string | null;
        is_active: boolean;
    }>;
    attributes: Record<string, { raw: string | string[]; label: string }>;
    variants?: ProductRow[];
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type AttributeRangeFilter = {
    min?: string;
    max?: string;
    from?: string;
    to?: string;
};

type Filters = {
    search: string;
    status: string;
    type: string;
    visibility: string;
    category_id: string;
    store_id: string;
    label_id: string;
    stock: string;
    price_min: string;
    price_max: string;
    variants: string;
    attrs: Record<string, string | AttributeRangeFilter>;
};

export default function ProductsIndex({
    products: page,
    filters,
    filterOptions,
    columns,
}: {
    products: Paginated<ProductRow>;
    filters: Filters;
    filterOptions: FilterOptions;
    columns: ColumnDefinition[];
}) {
    const { can } = usePermissions();
    const lockedColumns = useMemo(
        () =>
            columns
                .filter((column) => column.locked)
                .map((column) => column.key),
        [columns],
    );
    const defaultColumns = useMemo(
        () => columns.map((column) => column.key),
        [columns],
    );
    const [selectedColumns, setSelectedColumns] = useState<string[]>(() =>
        readStoredColumns(columns, lockedColumns, defaultColumns),
    );

    const visibleColumns = useMemo(
        () => columns.filter((column) => selectedColumns.includes(column.key)),
        [columns, selectedColumns],
    );

    const activeFilters = useMemo(
        () => buildActiveFilters(filters, filterOptions),
        [filters, filterOptions],
    );

    const [deleteTarget, setDeleteTarget] = useState<ProductRow | null>(null);
    const [deleting, setDeleting] = useState(false);
    const [expanded, setExpanded] = useState<Set<number>>(new Set());

    const toggleExpand = (id: number) => {
        setExpanded((current) => {
            const next = new Set(current);
            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }
            return next;
        });
    };

    const canEditProducts = can('catalog.products.edit');

    const saveInline = (
        id: number,
        field: 'status' | 'visibility' | 'name',
        value: string,
    ) => {
        router.patch(
            products.inlineUpdate(id).url,
            { field, value },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => toast.success('Producto actualizado'),
                onError: (errors) =>
                    toast.error(
                        Object.values(errors)[0] ?? 'No se pudo actualizar',
                    ),
            },
        );
    };

    const destroy = () => {
        if (!deleteTarget) {
            return;
        }

        router.delete(products.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onStart: () => setDeleting(true),
            onFinish: () => {
                setDeleting(false);
                setDeleteTarget(null);
            },
        });
    };

    const toggleColumn = (key: string, checked: boolean) => {
        if (lockedColumns.includes(key)) {
            return;
        }

        const next = checked
            ? [...selectedColumns, key]
            : selectedColumns.filter((columnKey) => columnKey !== key);

        setSelectedColumns(next);
        window.localStorage.setItem(COLUMN_STORAGE_KEY, JSON.stringify(next));
    };

    const resetColumns = () => {
        setSelectedColumns(defaultColumns);
        window.localStorage.removeItem(COLUMN_STORAGE_KEY);
    };

    return (
        <>
            <Head title="Productos" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Productos</h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Grid de catalogo con filtros y atributos visibles.
                    </p>
                </div>
                {can('catalog.products.create') && (
                    <Button asChild>
                        <Link href={products.create()}>Nuevo producto</Link>
                    </Button>
                )}
            </div>

            <Form
                {...products.index.form()}
                options={{ preserveState: true, preserveScroll: true }}
            >
                {({ processing }) => (
                    <div className="mb-4 space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative min-w-64 flex-1 md:max-w-md">
                                <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-neutral-400" />
                                <Input
                                    name="search"
                                    defaultValue={filters.search}
                                    placeholder="Buscar nombre o SKU"
                                    className="pl-9"
                                />
                            </div>
                            <Button variant="outline" disabled={processing}>
                                <Filter className="size-4" />
                                Filtrar
                            </Button>
                            {activeFilters.length > 0 && (
                                <Button variant="ghost" asChild>
                                    <Link href={products.index()}>
                                        <X className="size-4" />
                                        Limpiar
                                    </Link>
                                </Button>
                            )}
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button type="button" variant="outline">
                                        <Columns3 className="size-4" />
                                        Columnas
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    className="max-h-96 w-72 overflow-y-auto"
                                >
                                    <DropdownMenuLabel>
                                        Columnas visibles
                                    </DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    {columns.map((column) => (
                                        <DropdownMenuCheckboxItem
                                            key={column.key}
                                            checked={selectedColumns.includes(
                                                column.key,
                                            )}
                                            disabled={column.locked}
                                            onCheckedChange={(checked) =>
                                                toggleColumn(
                                                    column.key,
                                                    Boolean(checked),
                                                )
                                            }
                                        >
                                            {column.label}
                                        </DropdownMenuCheckboxItem>
                                    ))}
                                    <DropdownMenuSeparator />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="sm"
                                        className="w-full justify-start"
                                        onClick={resetColumns}
                                    >
                                        Restablecer columnas
                                    </Button>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>

                        <Collapsible defaultOpen={activeFilters.length > 0}>
                            <CollapsibleTrigger asChild>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="sm"
                                    className="px-0 text-neutral-600 dark:text-neutral-300"
                                >
                                    <ChevronDown className="size-4" />
                                    Filtros avanzados
                                </Button>
                            </CollapsibleTrigger>
                            <CollapsibleContent className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                                <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                    <SelectFilter
                                        name="status"
                                        label="Estado"
                                        value={filters.status}
                                        options={filterOptions.statuses}
                                        empty="Todos"
                                    />
                                    <SelectFilter
                                        name="type"
                                        label="Tipo"
                                        value={filters.type}
                                        options={filterOptions.types}
                                        empty="Todos"
                                    />
                                    <SelectFilter
                                        name="visibility"
                                        label="Visibilidad"
                                        value={filters.visibility}
                                        options={filterOptions.visibilities}
                                        empty="Todas"
                                    />
                                    <SelectFilter
                                        name="variants"
                                        label="Variantes"
                                        value={filters.variants}
                                        options={[
                                            {
                                                value: 'include',
                                                label: 'Incluir variantes',
                                            },
                                        ]}
                                        empty="Ocultar variantes"
                                    />
                                    <SelectFilter
                                        name="stock"
                                        label="Stock"
                                        value={filters.stock}
                                        options={[
                                            { value: 'in', label: 'Con stock' },
                                            {
                                                value: 'out',
                                                label: 'Sin stock',
                                            },
                                        ]}
                                        empty="Todos"
                                    />
                                    <SelectFilter
                                        name="category_id"
                                        label="Categoria"
                                        value={filters.category_id}
                                        options={filterOptions.categories.map(
                                            (option) => ({
                                                value: String(option.id),
                                                label: option.label,
                                            }),
                                        )}
                                        empty="Todas"
                                    />
                                    <SelectFilter
                                        name="store_id"
                                        label="Tienda"
                                        value={filters.store_id}
                                        options={filterOptions.stores.map(
                                            (option) => ({
                                                value: String(option.id),
                                                label: option.label,
                                            }),
                                        )}
                                        empty="Todas"
                                    />
                                    <SelectFilter
                                        name="label_id"
                                        label="Etiqueta"
                                        value={filters.label_id}
                                        options={filterOptions.labels.map(
                                            (option) => ({
                                                value: String(option.id),
                                                label: option.label,
                                            }),
                                        )}
                                        empty="Todas"
                                    />
                                    <div className="grid grid-cols-2 gap-2">
                                        <FieldLabel label="Precio min">
                                            <Input
                                                name="price_min"
                                                type="number"
                                                step="0.01"
                                                defaultValue={filters.price_min}
                                            />
                                        </FieldLabel>
                                        <FieldLabel label="Precio max">
                                            <Input
                                                name="price_max"
                                                type="number"
                                                step="0.01"
                                                defaultValue={filters.price_max}
                                            />
                                        </FieldLabel>
                                    </div>
                                </div>

                                {filterOptions.attributes.length > 0 && (
                                    <div className="mt-4 border-t border-neutral-200 pt-4 dark:border-neutral-800">
                                        <p className="mb-3 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                                            Atributos filtrables
                                        </p>
                                        <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                                            {filterOptions.attributes.map(
                                                (attribute) => (
                                                    <AttributeFilterInput
                                                        key={attribute.id}
                                                        attribute={attribute}
                                                        value={
                                                            filters.attrs?.[
                                                                String(
                                                                    attribute.id,
                                                                )
                                                            ]
                                                        }
                                                    />
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            </CollapsibleContent>
                        </Collapsible>

                        {activeFilters.length > 0 && (
                            <div className="flex flex-wrap gap-2">
                                {activeFilters.map((filter) => (
                                    <Badge
                                        key={filter}
                                        variant="outline"
                                        className="max-w-72 truncate"
                                    >
                                        {filter}
                                    </Badge>
                                ))}
                            </div>
                        )}
                    </div>
                )}
            </Form>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <div className="overflow-x-auto">
                    <table className="w-full min-w-max text-left text-xs">
                        <thead className="border-b border-neutral-200 bg-neutral-50 text-neutral-500 dark:border-neutral-800 dark:bg-neutral-950/40">
                            <tr>
                                <th className="w-8 px-2 py-2" />
                                {visibleColumns.map((column) => (
                                    <th
                                        key={column.key}
                                        className="px-3 py-2 font-medium whitespace-nowrap"
                                    >
                                        {column.label}
                                    </th>
                                ))}
                                <th className="sticky right-0 bg-neutral-50 px-3 py-2 text-right font-medium dark:bg-neutral-950">
                                    Acciones
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {page.data.map((product) => {
                                const variants = product.variants ?? [];
                                const isExpandable = variants.length > 0;
                                const isExpanded = expanded.has(product.id);

                                return (
                                    <Fragment key={product.id}>
                                        <tr className="hover:bg-neutral-50/70 dark:hover:bg-neutral-800/40">
                                            <td className="px-2 py-2 align-top">
                                                {isExpandable && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            toggleExpand(
                                                                product.id,
                                                            )
                                                        }
                                                        aria-label={
                                                            isExpanded
                                                                ? 'Ocultar variantes'
                                                                : 'Ver variantes'
                                                        }
                                                        aria-expanded={isExpanded}
                                                        className="flex size-6 cursor-pointer items-center justify-center rounded text-neutral-500 transition hover:bg-neutral-200 hover:text-neutral-900 dark:hover:bg-neutral-700 dark:hover:text-neutral-100"
                                                    >
                                                        {isExpanded ? (
                                                            <ChevronDown className="size-4" />
                                                        ) : (
                                                            <ChevronRight className="size-4" />
                                                        )}
                                                    </button>
                                                )}
                                            </td>
                                            {visibleColumns.map((column) => (
                                                <td
                                                    key={column.key}
                                                    className="max-w-56 px-3 py-2 align-top"
                                                >
                                                    <ProductCell
                                                        column={column}
                                                        product={product}
                                                        onInlineSave={
                                                            canEditProducts
                                                                ? saveInline
                                                                : undefined
                                                        }
                                                    />
                                                </td>
                                            ))}
                                            <td className="sticky right-0 bg-white px-3 py-2 dark:bg-neutral-900">
                                                <div className="flex justify-end gap-2">
                                                    {can(
                                                        'catalog.products.edit',
                                                    ) && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            asChild
                                                        >
                                                            <Link
                                                                href={products.edit(
                                                                    product.id,
                                                                )}
                                                            >
                                                                Editar
                                                            </Link>
                                                        </Button>
                                                    )}
                                                    {can(
                                                        'catalog.products.delete',
                                                    ) && (
                                                        <Button
                                                            variant="destructive"
                                                            size="sm"
                                                            onClick={() =>
                                                                setDeleteTarget(
                                                                    product,
                                                                )
                                                            }
                                                        >
                                                            Eliminar
                                                        </Button>
                                                    )}
                                                </div>
                                            </td>
                                        </tr>
                                        {isExpanded &&
                                            variants.map((variant) => (
                                                <tr
                                                    key={variant.id}
                                                    className="bg-neutral-50/60 hover:bg-neutral-100/70 dark:bg-neutral-900/40 dark:hover:bg-neutral-800/50"
                                                >
                                                    <td className="border-l-2 border-red-700 px-2 py-2 align-top dark:border-red-400" />
                                                    {visibleColumns.map(
                                                        (column) => (
                                                            <td
                                                                key={column.key}
                                                                className="max-w-56 px-3 py-2 align-top"
                                                            >
                                                                <ProductCell
                                                                    column={
                                                                        column
                                                                    }
                                                                    product={
                                                                        variant
                                                                    }
                                                                    nested
                                                                    onInlineSave={
                                                                        canEditProducts
                                                                            ? saveInline
                                                                            : undefined
                                                                    }
                                                                />
                                                            </td>
                                                        ),
                                                    )}
                                                    <td className="sticky right-0 bg-neutral-50 px-3 py-2 dark:bg-neutral-900">
                                                        <div className="flex justify-end gap-2">
                                                            {can(
                                                                'catalog.products.edit',
                                                            ) && (
                                                                <Button
                                                                    variant="outline"
                                                                    size="sm"
                                                                    asChild
                                                                >
                                                                    <Link
                                                                        href={products.edit(
                                                                            variant.id,
                                                                        )}
                                                                    >
                                                                        Editar
                                                                    </Link>
                                                                </Button>
                                                            )}
                                                            {can(
                                                                'catalog.products.delete',
                                                            ) && (
                                                                <Button
                                                                    variant="destructive"
                                                                    size="sm"
                                                                    onClick={() =>
                                                                        setDeleteTarget(
                                                                            variant,
                                                                        )
                                                                    }
                                                                >
                                                                    Eliminar
                                                                </Button>
                                                            )}
                                                        </div>
                                                    </td>
                                                </tr>
                                            ))}
                                    </Fragment>
                                );
                            })}
                            {page.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={visibleColumns.length + 2}
                                        className="px-4 py-8 text-center text-neutral-500"
                                    >
                                        No hay productos con esos filtros.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-neutral-500">
                <span>{page.total} productos</span>
                <div className="flex gap-2">
                    {page.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.prev_page_url} preserveScroll>
                                Anterior
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Anterior
                        </Button>
                    )}
                    {page.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.next_page_url} preserveScroll>
                                Siguiente
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Siguiente
                        </Button>
                    )}
                </div>
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={destroy}
                loading={deleting}
                title="Eliminar producto"
                description={
                    deleteTarget ? (
                        <>
                            Vas a eliminar{' '}
                            <span className="font-semibold text-foreground">
                                {deleteTarget.name}
                            </span>{' '}
                            ({deleteTarget.sku}). Esta acción no se puede
                            deshacer.
                        </>
                    ) : null
                }
                confirmLabel="Eliminar"
            />
        </>
    );
}

function FieldLabel({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <label className="space-y-1">
            <span className="block text-xs font-medium text-neutral-600 dark:text-neutral-300">
                {label}
            </span>
            {children}
        </label>
    );
}

function SelectFilter({
    name,
    label,
    value,
    options,
    empty,
}: {
    name: string;
    label: string;
    value: string;
    options: Option[];
    empty: string;
}) {
    return (
        <FieldLabel label={label}>
            <select
                name={name}
                defaultValue={value}
                className="h-9 w-full rounded-md border border-neutral-300 bg-white px-3 text-sm dark:border-neutral-700 dark:bg-neutral-800"
            >
                <option value="">{empty}</option>
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
        </FieldLabel>
    );
}

function AttributeFilterInput({
    attribute,
    value,
}: {
    attribute: AttributeFilter;
    value: string | AttributeRangeFilter | undefined;
}) {
    const stringValue = typeof value === 'string' ? value : '';
    const rangeValue = typeof value === 'object' && value ? value : {};

    if (attribute.type === 'number') {
        return (
            <div className="grid grid-cols-2 gap-2">
                <FieldLabel label={`${attribute.name} min`}>
                    <Input
                        name={`attrs[${attribute.id}][min]`}
                        type="number"
                        step="0.01"
                        defaultValue={rangeValue.min ?? ''}
                    />
                </FieldLabel>
                <FieldLabel label={`${attribute.name} max`}>
                    <Input
                        name={`attrs[${attribute.id}][max]`}
                        type="number"
                        step="0.01"
                        defaultValue={rangeValue.max ?? ''}
                    />
                </FieldLabel>
            </div>
        );
    }

    if (attribute.type === 'date') {
        return (
            <div className="grid grid-cols-2 gap-2">
                <FieldLabel label={`${attribute.name} desde`}>
                    <Input
                        name={`attrs[${attribute.id}][from]`}
                        type="date"
                        defaultValue={rangeValue.from ?? ''}
                    />
                </FieldLabel>
                <FieldLabel label={`${attribute.name} hasta`}>
                    <Input
                        name={`attrs[${attribute.id}][to]`}
                        type="date"
                        defaultValue={rangeValue.to ?? ''}
                    />
                </FieldLabel>
            </div>
        );
    }

    if (['select', 'multiselect'].includes(attribute.type)) {
        return (
            <SelectFilter
                name={`attrs[${attribute.id}]`}
                label={attribute.name}
                value={stringValue}
                options={attribute.options}
                empty="Todos"
            />
        );
    }

    if (attribute.type === 'boolean') {
        return (
            <SelectFilter
                name={`attrs[${attribute.id}]`}
                label={attribute.name}
                value={stringValue}
                options={[
                    { value: '1', label: 'Si' },
                    { value: '0', label: 'No' },
                ]}
                empty="Todos"
            />
        );
    }

    return (
        <FieldLabel label={attribute.name}>
            <Input
                name={`attrs[${attribute.id}]`}
                defaultValue={stringValue}
                placeholder="Contiene..."
            />
        </FieldLabel>
    );
}

const INLINE_SELECT_CLASS =
    'w-full max-w-40 cursor-pointer rounded-md border border-neutral-200 bg-white px-2 py-1 text-xs text-neutral-800 transition hover:border-neutral-400 focus:border-red-700 focus:ring-1 focus:ring-red-700 focus:outline-none dark:border-neutral-700 dark:bg-neutral-950 dark:text-neutral-100';

function ProductCell({
    column,
    product,
    nested = false,
    onInlineSave,
}: {
    column: ColumnDefinition;
    product: ProductRow;
    nested?: boolean;
    onInlineSave?: (
        id: number,
        field: 'status' | 'visibility' | 'name',
        value: string,
    ) => void;
}) {
    if (column.key.startsWith('attr:')) {
        const value = product.attributes[String(column.attribute_id)]?.label;

        return <TruncatedText value={value || '-'} />;
    }

    if (column.key === 'image') {
        return (
            <div className="flex size-10 items-center justify-center overflow-hidden rounded bg-neutral-100 dark:bg-neutral-800">
                {product.thumbnail ? (
                    <img
                        src={product.thumbnail}
                        alt={product.name}
                        className="h-full w-full object-cover"
                    />
                ) : (
                    <ImageIcon className="size-4 text-neutral-400" />
                )}
            </div>
        );
    }

    if (column.key === 'sku') {
        return <TruncatedText value={product.sku} className="font-mono" />;
    }

    if (column.key === 'name') {
        return (
            <div className="space-y-1">
                {onInlineSave ? (
                    <EditableName
                        value={product.name}
                        onSave={(value) =>
                            onInlineSave(product.id, 'name', value)
                        }
                    />
                ) : (
                    <TruncatedText
                        value={product.name}
                        className="font-medium text-neutral-900 dark:text-neutral-100"
                    />
                )}
                {product.parent && !nested && (
                    <Badge variant="outline" className="text-[10px]">
                        Variante de {product.parent.name}
                    </Badge>
                )}
                <ProductLabels labels={product.labels} />
            </div>
        );
    }

    if (column.key === 'type') {
        return <Badge variant="outline">{typeLabel(product.type)}</Badge>;
    }

    if (column.key === 'status') {
        if (!onInlineSave) {
            return (
                <Badge
                    variant={product.status === 'active' ? 'default' : 'outline'}
                >
                    {product.status === 'active' ? 'Activo' : 'Inactivo'}
                </Badge>
            );
        }

        return (
            <select
                value={product.status}
                onChange={(event) =>
                    onInlineSave(product.id, 'status', event.target.value)
                }
                className={INLINE_SELECT_CLASS}
            >
                <option value="active">Activo</option>
                <option value="inactive">Inactivo</option>
            </select>
        );
    }

    if (column.key === 'visibility') {
        if (!onInlineSave) {
            return <TruncatedText value={visibilityLabel(product.visibility)} />;
        }

        return (
            <select
                value={product.visibility}
                onChange={(event) =>
                    onInlineSave(product.id, 'visibility', event.target.value)
                }
                className={INLINE_SELECT_CLASS}
            >
                <option value="both">Catálogo y búsqueda</option>
                <option value="catalog">Solo catálogo</option>
                <option value="search">Solo búsqueda</option>
                <option value="hidden">Oculto</option>
            </select>
        );
    }

    if (column.key === 'price') {
        return <TruncatedText value={product.price ?? '-'} />;
    }

    if (column.key === 'stock') {
        return (
            <Badge
                variant={product.stock.status === 'in' ? 'default' : 'outline'}
            >
                {product.stock.status === 'in'
                    ? `${product.stock.available} disp.`
                    : 'Sin stock'}
            </Badge>
        );
    }

    if (column.key === 'categories') {
        return (
            <TruncatedText
                value={
                    product.categories
                        .map((category) => category.name)
                        .join(', ') || '-'
                }
            />
        );
    }

    if (column.key === 'stores') {
        return (
            <TruncatedText
                value={
                    product.stores
                        .filter((store) => store.is_active)
                        .map((store) => store.name)
                        .join(', ') || '-'
                }
            />
        );
    }

    if (column.key === 'labels') {
        return <ProductLabels labels={product.labels} />;
    }

    return <TruncatedText value="-" />;
}

function EditableName({
    value,
    onSave,
}: {
    value: string;
    onSave: (value: string) => void;
}) {
    const [editing, setEditing] = useState(false);

    const commit = (next: string) => {
        const trimmed = next.trim();
        setEditing(false);
        if (trimmed && trimmed !== value) {
            onSave(trimmed);
        }
    };

    if (!editing) {
        return (
            <button
                type="button"
                onClick={() => setEditing(true)}
                title="Editar nombre"
                className="group/name flex max-w-56 cursor-text items-center gap-1 text-left"
            >
                <span className="truncate font-medium text-neutral-900 dark:text-neutral-100">
                    {value}
                </span>
                <Pencil className="size-3 shrink-0 text-neutral-400 opacity-0 transition group-hover/name:opacity-100" />
            </button>
        );
    }

    return (
        <input
            type="text"
            autoFocus
            defaultValue={value}
            onBlur={(event) => commit(event.target.value)}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    event.preventDefault();
                    event.currentTarget.blur();
                } else if (event.key === 'Escape') {
                    event.preventDefault();
                    event.currentTarget.value = value;
                    event.currentTarget.blur();
                }
            }}
            className="w-full max-w-56 rounded-md border border-red-700 bg-white px-2 py-1 text-xs font-medium text-neutral-900 focus:ring-1 focus:ring-red-700 focus:outline-none dark:bg-neutral-950 dark:text-neutral-100"
        />
    );
}

function TruncatedText({
    value,
    className = '',
}: {
    value: string | number;
    className?: string;
}) {
    return (
        <span
            title={String(value)}
            className={`block max-w-56 truncate text-neutral-700 dark:text-neutral-200 ${className}`}
        >
            {value}
        </span>
    );
}

function buildActiveFilters(
    filters: Filters,
    options: FilterOptions,
): string[] {
    const chips = [
        filters.search ? `Busqueda: ${filters.search}` : null,
        optionChip('Estado', filters.status, options.statuses),
        optionChip('Tipo', filters.type, options.types),
        optionChip('Visibilidad', filters.visibility, options.visibilities),
        optionChip(
            'Categoria',
            filters.category_id,
            options.categories.map((option) => ({
                value: String(option.id),
                label: option.label,
            })),
        ),
        optionChip(
            'Tienda',
            filters.store_id,
            options.stores.map((option) => ({
                value: String(option.id),
                label: option.label,
            })),
        ),
        optionChip(
            'Etiqueta',
            filters.label_id,
            options.labels.map((option) => ({
                value: String(option.id),
                label: option.label,
            })),
        ),
        optionChip('Stock', filters.stock, [
            { value: 'in', label: 'Con stock' },
            { value: 'out', label: 'Sin stock' },
        ]),
        filters.price_min ? `Precio >= ${filters.price_min}` : null,
        filters.price_max ? `Precio <= ${filters.price_max}` : null,
    ].filter(Boolean) as string[];

    for (const attribute of options.attributes) {
        const raw = filters.attrs?.[String(attribute.id)];

        if (!raw) {
            continue;
        }

        if (typeof raw === 'string' && raw !== '') {
            chips.push(
                `${attribute.name}: ${optionLabel(attribute.options, raw) ?? raw}`,
            );
        }

        if (typeof raw === 'object') {
            if (raw.min) {
                chips.push(`${attribute.name} >= ${raw.min}`);
            }

            if (raw.max) {
                chips.push(`${attribute.name} <= ${raw.max}`);
            }

            if (raw.from) {
                chips.push(`${attribute.name} desde ${raw.from}`);
            }

            if (raw.to) {
                chips.push(`${attribute.name} hasta ${raw.to}`);
            }
        }
    }

    return chips;
}

function readStoredColumns(
    columns: ColumnDefinition[],
    lockedColumns: string[],
    defaultColumns: string[],
) {
    if (typeof window === 'undefined') {
        return defaultColumns;
    }

    const stored = window.localStorage.getItem(COLUMN_STORAGE_KEY);

    if (!stored) {
        return defaultColumns;
    }

    try {
        const parsed = JSON.parse(stored) as string[];
        const available = new Set(columns.map((column) => column.key));
        const next = [
            ...new Set([
                ...lockedColumns,
                ...parsed.filter((key) => available.has(key)),
            ]),
        ];

        return next.length > lockedColumns.length ? next : defaultColumns;
    } catch {
        return defaultColumns;
    }
}

function optionChip(label: string, value: string, options: Option[]) {
    if (!value) {
        return null;
    }

    return `${label}: ${optionLabel(options, value) ?? value}`;
}

function optionLabel(options: Option[], value: string) {
    return options.find((option) => option.value === value)?.label;
}

function typeLabel(value: string) {
    return (
        {
            simple: 'Simple',
            configurable: 'Configurable',
            bundle: 'Paquete',
            downloadable: 'Descargable',
        }[value] ?? value
    );
}

function visibilityLabel(value: string) {
    return (
        {
            both: 'Catalogo y busqueda',
            catalog: 'Solo catalogo',
            search: 'Solo busqueda',
            hidden: 'Oculto',
        }[value] ?? value
    );
}
