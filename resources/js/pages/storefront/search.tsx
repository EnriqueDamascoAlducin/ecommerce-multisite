import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { useStoreUrls } from '@/lib/storefront';
import { ProductCard, type ProductCardData } from './product-card';

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type FilterOption = {
    label: string;
    value: string;
};

type FilterAttribute = {
    id: number;
    code: string;
    name: string;
    type: string;
    options: FilterOption[];
};

function isArrayValue(value: unknown): value is string[] {
    return Array.isArray(value);
}

function isRecordValue(value: unknown): value is Record<string, string> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export default function StorefrontSearch({
    filters,
    filterOptions,
    products,
}: {
    filters: { q: string; attrs: Record<string, unknown> };
    filterOptions: { attributes: FilterAttribute[] };
    products: Paginated<ProductCardData>;
}) {
    const urls = useStoreUrls();
    const [showFilters, setShowFilters] = useState(false);
    const currentAttrs = filters.attrs ?? {};

    function navigate(f: Record<string, unknown>) {
        router.get(urls.search(), f as Parameters<typeof router.get>[1], {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function handleSearchSubmit(e: React.FormEvent<HTMLFormElement>) {
        e.preventDefault();
        const formData = new FormData(e.currentTarget);
        navigate({ q: (formData.get('q') as string) || '' });
    }

    function toggleFilter(attrId: number, value: string) {
        const attr = filterOptions.attributes.find((a) => a.id === attrId);
        if (!attr) return;

        const newAttrs = { ...currentAttrs };

        if (attr.type === 'multiselect') {
            const current = isArrayValue(newAttrs[attrId])
                ? [...newAttrs[attrId]]
                : [];
            const idx = current.indexOf(value);
            if (idx >= 0) {
                current.splice(idx, 1);
            } else {
                current.push(value);
            }
            if (current.length === 0) {
                delete newAttrs[attrId];
            } else {
                newAttrs[attrId] = current;
            }
        } else {
            if (newAttrs[attrId] === value) {
                delete newAttrs[attrId];
            } else {
                newAttrs[attrId] = value;
            }
        }

        navigate({ q: filters.q, attrs: newAttrs });
    }

    function handleBooleanChange(attrId: number, e: React.ChangeEvent<HTMLSelectElement>) {
        const newAttrs = { ...currentAttrs };
        const val = e.target.value;
        if (val === '') {
            delete newAttrs[attrId];
        } else {
            newAttrs[attrId] = val;
        }
        navigate({ q: filters.q, attrs: newAttrs });
    }

    function handleRangeSubmit(
        attrId: number,
        field: 'min' | 'max' | 'from' | 'to',
        e: React.FocusEvent<HTMLInputElement>,
    ) {
        const newAttrs = { ...currentAttrs };

        const current = isRecordValue(newAttrs[attrId]) ? { ...newAttrs[attrId] as Record<string, string> } : {};
        if (e.target.value) {
            current[field] = e.target.value;
        } else {
            delete current[field];
        }

        if (Object.keys(current).length === 0) {
            delete newAttrs[attrId];
        } else {
            newAttrs[attrId] = current;
        }

        navigate({ q: filters.q, attrs: newAttrs });
    }

    function clearFilters() {
        navigate({ q: filters.q });
    }

    function isSelected(attrId: number, value: string): boolean {
        const current = currentAttrs[attrId];
        if (Array.isArray(current)) {
            return current.includes(value);
        }
        return current === value;
    }

    function hasActiveFilters(): boolean {
        return Object.keys(currentAttrs).length > 0;
    }

    const renderAttributeFilter = (attr: FilterAttribute) => {
        switch (attr.type) {
            case 'select':
                return (
                    <div key={attr.id} className="space-y-1">
                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            {attr.name}
                        </p>
                        {attr.options.map((opt) => (
                            <label
                                key={opt.value}
                                className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800"
                            >
                                <input
                                    type="checkbox"
                                    checked={isSelected(attr.id, opt.value)}
                                    onChange={() => toggleFilter(attr.id, opt.value)}
                                    className="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600"
                                />
                                {opt.label}
                            </label>
                        ))}
                    </div>
                );

            case 'multiselect':
                return (
                    <div key={attr.id} className="space-y-1">
                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            {attr.name}
                        </p>
                        {attr.options.map((opt) => (
                            <label
                                key={opt.value}
                                className="flex cursor-pointer items-center gap-2 rounded px-2 py-1 text-sm hover:bg-neutral-100 dark:hover:bg-neutral-800"
                            >
                                <input
                                    type="checkbox"
                                    checked={isSelected(attr.id, opt.value)}
                                    onChange={() => toggleFilter(attr.id, opt.value)}
                                    className="size-4 rounded border-neutral-300 text-neutral-900 focus:ring-neutral-900 dark:border-neutral-600"
                                />
                                {opt.label}
                            </label>
                        ))}
                    </div>
                );

            case 'boolean':
                return (
                    <div key={attr.id} className="space-y-1">
                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            {attr.name}
                        </p>
                        <select
                            value={isRecordValue(currentAttrs[attr.id]) ? '' : (currentAttrs[attr.id] as string) ?? ''}
                            onChange={(e) => handleBooleanChange(attr.id, e)}
                            className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                        >
                            <option value="">Todos</option>
                            <option value="1">Sí</option>
                            <option value="0">No</option>
                        </select>
                    </div>
                );

            case 'number':
                return (
                    <div key={attr.id} className="space-y-1">
                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            {attr.name}
                        </p>
                        <div className="flex gap-2">
                            <div className="flex-1">
                                <Label className="text-xs text-neutral-500">Mín</Label>
                                <input
                                    type="number"
                                    defaultValue={(isRecordValue(currentAttrs[attr.id]) ? currentAttrs[attr.id] as Record<string, string> : {}).min ?? ''}
                                    onBlur={(e) => handleRangeSubmit(attr.id, 'min', e)}
                                    placeholder="0"
                                    className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>
                            <div className="flex-1">
                                <Label className="text-xs text-neutral-500">Máx</Label>
                                <input
                                    type="number"
                                    defaultValue={(isRecordValue(currentAttrs[attr.id]) ? currentAttrs[attr.id] as Record<string, string> : {}).max ?? ''}
                                    onBlur={(e) => handleRangeSubmit(attr.id, 'max', e)}
                                    placeholder="9999"
                                    className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>
                        </div>
                    </div>
                );

            case 'date':
                return (
                    <div key={attr.id} className="space-y-1">
                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            {attr.name}
                        </p>
                        <div className="flex gap-2">
                            <div className="flex-1">
                                <Label className="text-xs text-neutral-500">Desde</Label>
                                <input
                                    type="date"
                                    defaultValue={(isRecordValue(currentAttrs[attr.id]) ? currentAttrs[attr.id] as Record<string, string> : {}).from ?? ''}
                                    onBlur={(e) => handleRangeSubmit(attr.id, 'from', e)}
                                    className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>
                            <div className="flex-1">
                                <Label className="text-xs text-neutral-500">Hasta</Label>
                                <input
                                    type="date"
                                    defaultValue={(isRecordValue(currentAttrs[attr.id]) ? currentAttrs[attr.id] as Record<string, string> : {}).to ?? ''}
                                    onBlur={(e) => handleRangeSubmit(attr.id, 'to', e)}
                                    className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>
                        </div>
                    </div>
                );

            default:
                return (
                    <div key={attr.id} className="space-y-1">
                        <p className="text-sm font-medium text-neutral-700 dark:text-neutral-300">
                            {attr.name}
                        </p>
                        <input
                            type="text"
                            defaultValue={typeof currentAttrs[attr.id] === 'string' ? (currentAttrs[attr.id] as string) : ''}
                            onBlur={(e) => {
                                const newAttrs = { ...currentAttrs };
                                if (e.target.value) {
                                    newAttrs[attr.id] = e.target.value;
                                } else {
                                    delete newAttrs[attr.id];
                                }
                                navigate({ q: filters.q, attrs: newAttrs });
                            }}
                            placeholder={`Buscar por ${attr.name.toLowerCase()}`}
                            className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                        />
                    </div>
                );
        }
    };

    return (
        <>
            <Head title={filters.q ? `${filters.q} — Buscar` : 'Buscar productos'} />

            <div className="mb-6">
                <form onSubmit={handleSearchSubmit} className="flex gap-2">
                    <Input
                        name="q"
                        defaultValue={filters.q}
                        placeholder="Buscar por nombre o SKU"
                        className="flex-1"
                    />
                    <Button type="submit" variant="default">
                        Buscar
                    </Button>
                    <Button
                        type="button"
                        variant="outline"
                        className="md:hidden"
                        onClick={() => setShowFilters(!showFilters)}
                    >
                        Filtros
                    </Button>
                </form>
            </div>

            <div className="flex gap-8">
                <aside
                    className={`${showFilters ? 'block' : 'hidden'} w-full shrink-0 space-y-6 md:block md:w-60`}
                >
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-semibold text-neutral-700 dark:text-neutral-300">
                            Filtros
                        </h2>
                        {hasActiveFilters() && (
                            <button
                                type="button"
                                onClick={clearFilters}
                                className="text-xs text-red-700 hover:text-red-800 dark:text-red-400"
                            >
                                Limpiar
                            </button>
                        )}
                    </div>

                    <div className="space-y-5">
                        {filterOptions.attributes.map(renderAttributeFilter)}
                    </div>
                </aside>

                <div className="min-w-0 flex-1">
                    <div className="mb-4 flex items-center justify-between text-sm text-neutral-500">
                        <span>
                            {products.total === 0
                                ? 'Sin resultados'
                                : `${products.total} producto${products.total === 1 ? '' : 's'}`}
                            {filters.q && (
                                <>
                                    {' '}para <strong className="text-neutral-700 dark:text-neutral-300">&ldquo;{filters.q}&rdquo;</strong>
                                </>
                            )}
                        </span>
                    </div>

                    {products.data.length === 0 ? (
                        <p className="py-12 text-center text-neutral-500">
                            No se encontraron productos con los criterios de búsqueda.
                        </p>
                    ) : (
                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                            {products.data.map((product) => (
                                <ProductCard key={product.sku} product={product} />
                            ))}
                        </div>
                    )}

                    <div className="mt-8 flex items-center justify-between text-sm text-neutral-500">
                        <span>{products.total} productos</span>
                        <div className="flex gap-2">
                            {products.prev_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={products.prev_page_url} preserveScroll>
                                        Anterior
                                    </Link>
                                </Button>
                            ) : (
                                <Button variant="outline" size="sm" disabled>
                                    Anterior
                                </Button>
                            )}
                            {products.next_page_url ? (
                                <Button variant="outline" size="sm" asChild>
                                    <Link href={products.next_page_url} preserveScroll>
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
                </div>
            </div>
        </>
    );
}
