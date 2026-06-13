import { Head, Link, router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, SlidersHorizontal } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { useStoreUrls } from '@/lib/storefront';
import { ProductCard, type ProductCardData } from './product-card';

type PaginationLink = {
    url: string | null;
    label: string;
    active: boolean;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
    total: number;
    links: PaginationLink[];
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

const SORT_OPTIONS = [
    { value: 'relevance', label: 'Relevancia' },
    { value: 'price_asc', label: 'Precio: menor a mayor' },
    { value: 'price_desc', label: 'Precio: mayor a menor' },
    { value: 'newest', label: 'Nuevos' },
];

function isArrayValue(value: unknown): value is string[] {
    return Array.isArray(value);
}

function isRecordValue(value: unknown): value is Record<string, string> {
    return typeof value === 'object' && value !== null && !Array.isArray(value);
}

export default function StorefrontCategory({
    category,
    filters,
    filterOptions,
    sort,
    products,
}: {
    category: { name: string; slug: string; description: string | null };
    filters: { attrs: Record<string, unknown> };
    filterOptions: { attributes: FilterAttribute[] };
    sort: string;
    products: Paginated<ProductCardData>;
}) {
    const urls = useStoreUrls();
    const [showFilters, setShowFilters] = useState(false);
    const currentAttrs = filters.attrs ?? {};

    function navigate(attrs?: Record<string, unknown>, sortValue?: string) {
        const payload: Record<string, unknown> = {};

        if (attrs && Object.keys(attrs).length > 0) {
            payload.attrs = attrs;
        }

        const nextSort = sortValue ?? sort;
        if (nextSort && nextSort !== 'relevance') {
            payload.sort = nextSort;
        }

        router.get(urls.category(category.slug), payload as Parameters<typeof router.get>[1], {
            preserveState: true,
            preserveScroll: true,
        });
    }

    function toggleFilter(attrId: number, value: string) {
        const attr = filterOptions.attributes.find((a) => a.id === attrId);
        if (!attr) {
            return;
        }

        const newAttrs = { ...currentAttrs };

        if (attr.type === 'multiselect') {
            const current = isArrayValue(newAttrs[attrId]) ? [...newAttrs[attrId]] : [];
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
        } else if (newAttrs[attrId] === value) {
            delete newAttrs[attrId];
        } else {
            newAttrs[attrId] = value;
        }

        navigate(newAttrs);
    }

    function handleBooleanChange(attrId: number, e: React.ChangeEvent<HTMLSelectElement>) {
        const newAttrs = { ...currentAttrs };
        const val = e.target.value;
        if (val === '') {
            delete newAttrs[attrId];
        } else {
            newAttrs[attrId] = val;
        }
        navigate(newAttrs);
    }

    function handleRangeSubmit(
        attrId: number,
        field: 'min' | 'max' | 'from' | 'to',
        e: React.FocusEvent<HTMLInputElement>,
    ) {
        const newAttrs = { ...currentAttrs };
        const current = isRecordValue(newAttrs[attrId]) ? { ...(newAttrs[attrId] as Record<string, string>) } : {};

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

        navigate(newAttrs);
    }

    function clearFilters() {
        navigate();
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
            case 'multiselect':
                return (
                    <div key={attr.id} className="space-y-1">
                        <p className="mb-3 text-xs font-bold tracking-wider text-neutral-500 uppercase dark:text-neutral-400">
                            {attr.name}
                        </p>
                        {attr.options.map((opt) => (
                            <label
                                key={opt.value}
                                className="flex cursor-pointer items-center gap-3 rounded px-1 py-1 text-sm hover:text-red-700 dark:hover:text-red-300"
                            >
                                <input
                                    type="checkbox"
                                    checked={isSelected(attr.id, opt.value)}
                                    onChange={() => toggleFilter(attr.id, opt.value)}
                                    className="size-4 rounded border-neutral-300 text-red-700 focus:ring-red-700 dark:border-neutral-600"
                                />
                                {opt.label}
                            </label>
                        ))}
                    </div>
                );

            case 'boolean':
                return (
                    <div key={attr.id} className="space-y-1">
                        <p className="mb-3 text-xs font-bold tracking-wider text-neutral-500 uppercase dark:text-neutral-400">
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
                        <p className="mb-3 text-xs font-bold tracking-wider text-neutral-500 uppercase dark:text-neutral-400">
                            {attr.name}
                        </p>
                        <div className="flex gap-2">
                            <div className="flex-1">
                                <Label className="text-xs text-neutral-500">Mín</Label>
                                <input
                                    type="number"
                                    defaultValue={(isRecordValue(currentAttrs[attr.id]) ? (currentAttrs[attr.id] as Record<string, string>) : {}).min ?? ''}
                                    onBlur={(e) => handleRangeSubmit(attr.id, 'min', e)}
                                    placeholder="0"
                                    className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>
                            <div className="flex-1">
                                <Label className="text-xs text-neutral-500">Máx</Label>
                                <input
                                    type="number"
                                    defaultValue={(isRecordValue(currentAttrs[attr.id]) ? (currentAttrs[attr.id] as Record<string, string>) : {}).max ?? ''}
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
                        <p className="mb-3 text-xs font-bold tracking-wider text-neutral-500 uppercase dark:text-neutral-400">
                            {attr.name}
                        </p>
                        <div className="flex gap-2">
                            <div className="flex-1">
                                <Label className="text-xs text-neutral-500">Desde</Label>
                                <input
                                    type="date"
                                    defaultValue={(isRecordValue(currentAttrs[attr.id]) ? (currentAttrs[attr.id] as Record<string, string>) : {}).from ?? ''}
                                    onBlur={(e) => handleRangeSubmit(attr.id, 'from', e)}
                                    className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                />
                            </div>
                            <div className="flex-1">
                                <Label className="text-xs text-neutral-500">Hasta</Label>
                                <input
                                    type="date"
                                    defaultValue={(isRecordValue(currentAttrs[attr.id]) ? (currentAttrs[attr.id] as Record<string, string>) : {}).to ?? ''}
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
                        <p className="mb-3 text-xs font-bold tracking-wider text-neutral-500 uppercase dark:text-neutral-400">
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
                                navigate(newAttrs);
                            }}
                            placeholder={`Buscar por ${attr.name.toLowerCase()}`}
                            className="w-full rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                        />
                    </div>
                );
        }
    };

    const hasFilters = filterOptions.attributes.length > 0;

    return (
        <>
            <Head title={category.name} />

            {/* Breadcrumbs */}
            <nav className="mb-2 flex items-center gap-2 text-xs text-neutral-500 dark:text-neutral-400">
                <Link href={urls.home()} className="hover:text-red-700 dark:hover:text-red-300">
                    Inicio
                </Link>
                <ChevronRight className="size-3.5" />
                <span className="font-semibold text-neutral-900 dark:text-neutral-100">{category.name}</span>
            </nav>

            {/* Header */}
            <div className="mb-8 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div>
                    <h1 className="text-3xl font-bold tracking-tight text-neutral-950 md:text-4xl dark:text-neutral-50">
                        {category.name}
                    </h1>
                    {category.description && (
                        <p className="mt-2 max-w-2xl text-neutral-500 dark:text-neutral-400">{category.description}</p>
                    )}
                </div>
                <div className="flex items-center gap-3 rounded-xl border border-neutral-200 bg-white p-2 shadow-sm dark:border-neutral-800 dark:bg-neutral-950">
                    <span className="pl-2 text-sm text-neutral-500">Ordenar por:</span>
                    <select
                        value={sort}
                        onChange={(e) => navigate(currentAttrs, e.target.value)}
                        className="cursor-pointer border-none bg-transparent text-sm font-bold text-red-700 focus:ring-0 focus:outline-none dark:text-red-300"
                    >
                        {SORT_OPTIONS.map((opt) => (
                            <option key={opt.value} value={opt.value}>
                                {opt.label}
                            </option>
                        ))}
                    </select>
                </div>
            </div>

            <div className="flex flex-col gap-6 lg:flex-row">
                {hasFilters && (
                    <>
                        <Button
                            type="button"
                            variant="outline"
                            className="w-fit lg:hidden"
                            onClick={() => setShowFilters(!showFilters)}
                        >
                            <SlidersHorizontal className="size-4" />
                            Filtros
                        </Button>

                        <aside className={`${showFilters ? 'block' : 'hidden'} w-full shrink-0 lg:block lg:w-72`}>
                            <div className="space-y-6 lg:sticky lg:top-28">
                                {filterOptions.attributes.map((attr) => (
                                    <div
                                        key={attr.id}
                                        className="rounded-xl border border-neutral-200 bg-white p-6 shadow-sm dark:border-neutral-800 dark:bg-neutral-950"
                                    >
                                        {renderAttributeFilter(attr)}
                                    </div>
                                ))}
                                {hasActiveFilters() && (
                                    <button
                                        type="button"
                                        onClick={clearFilters}
                                        className="w-full rounded-lg bg-neutral-100 py-3 text-sm font-semibold text-neutral-700 transition hover:bg-neutral-200 dark:bg-neutral-800 dark:text-neutral-200 dark:hover:bg-neutral-700"
                                    >
                                        Limpiar filtros
                                    </button>
                                )}
                            </div>
                        </aside>
                    </>
                )}

                <div className="min-w-0 flex-1">
                    <div className="mb-6 text-sm text-neutral-500">
                        Mostrando <span className="font-bold text-neutral-900 dark:text-neutral-100">{products.data.length}</span> de{' '}
                        {products.total} productos
                    </div>

                    {products.data.length === 0 ? (
                        <p className="py-12 text-center text-neutral-500">No hay productos en esta categoría.</p>
                    ) : (
                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                            {products.data.map((product) => (
                                <ProductCard key={product.sku} product={product} />
                            ))}
                        </div>
                    )}

                    {products.last_page > 1 && (
                        <div className="mt-12 flex flex-wrap items-center justify-center gap-2">
                            {products.links.map((link, index) => {
                                const isPrev = index === 0;
                                const isNext = index === products.links.length - 1;
                                const base =
                                    'flex h-10 min-w-10 items-center justify-center rounded-lg px-3 text-sm font-medium transition-colors';

                                if (!link.url) {
                                    return (
                                        <span key={index} className={`${base} text-neutral-400`}>
                                            {isPrev ? (
                                                <ChevronLeft className="size-5" />
                                            ) : isNext ? (
                                                <ChevronRight className="size-5" />
                                            ) : (
                                                '…'
                                            )}
                                        </span>
                                    );
                                }

                                return (
                                    <Link
                                        key={index}
                                        href={link.url}
                                        preserveScroll
                                        className={
                                            link.active
                                                ? `${base} bg-red-700 text-white`
                                                : `${base} text-neutral-600 hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800`
                                        }
                                    >
                                        {isPrev ? (
                                            <ChevronLeft className="size-5" />
                                        ) : isNext ? (
                                            <ChevronRight className="size-5" />
                                        ) : (
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        )}
                                    </Link>
                                );
                            })}
                        </div>
                    )}
                </div>
            </div>
        </>
    );
}
