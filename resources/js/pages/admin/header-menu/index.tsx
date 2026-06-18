import { Head, router, useForm } from '@inertiajs/react';
import { GripVertical, Plus, X } from 'lucide-react';
import { useState } from 'react';
import type { ChangeEvent } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { usePermissions } from '@/hooks/use-permissions';
import headerMenu from '@/routes/admin/header-menu';

type LinkType =
    | 'all_categories'
    | 'category'
    | 'product'
    | 'page'
    | 'custom'
    | 'link';

type MenuItem = {
    id: number;
    store_id: number;
    parent_id: number | null;
    type: LinkType;
    label: string;
    url: string | null;
    category_id: number | null;
    product_id: number | null;
    page_id: number | null;
    is_active: boolean;
    expand_products: boolean;
    sort_order: number;
    children: MenuItem[];
    products: unknown[];
};

type StoreOption = { id: number; label: string };
type CategoryOption = { id: number; label: string };
type ProductOption = { id: number; label: string; sku: string };
type PageOption = { id: number; label: string; slug: string };

type MenuFormData = {
    store_id: number | null;
    parent_id: number | null;
    type: LinkType;
    label: string;
    url: string;
    category_id: number | null;
    product_id: number | null;
    page_id: number | null;
    is_active: boolean;
    expand_products: boolean;
    sort_order: number;
};

const TYPE_LABELS: Record<LinkType, string> = {
    all_categories: 'Todas las categorias',
    category: 'Categoria',
    product: 'Producto',
    page: 'Pagina',
    custom: 'URL personalizada',
    link: 'URL personalizada',
};

export default function HeaderMenuIndex({
    stores,
    currentStoreId,
    tree,
    categories,
    products,
    pages,
}: {
    stores: StoreOption[];
    currentStoreId: number | null;
    tree: MenuItem[];
    categories: CategoryOption[];
    products: ProductOption[];
    pages: PageOption[];
}) {
    const { can } = usePermissions();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [addingParentId, setAddingParentId] = useState<
        number | 'root' | null
    >(null);
    const [deleteTarget, setDeleteTarget] = useState<MenuItem | null>(null);
    const [deleting, setDeleting] = useState(false);

    const onStoreChange = (event: ChangeEvent<HTMLSelectElement>) => {
        router.get(
            headerMenu.index().url,
            { store_id: event.target.value },
            { preserveState: false },
        );
    };

    const destroyItem = () => {
        if (!deleteTarget) {
            return;
        }

        setDeleting(true);

        router.delete(headerMenu.destroy(deleteTarget.id).url, {
            preserveScroll: true,
            onSuccess: () => setDeleteTarget(null),
            onFinish: () => setDeleting(false),
        });
    };

    return (
        <>
            <Head title="Menu del header" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">Menu del header</h1>
                    <p className="mt-1 text-sm text-neutral-500">
                        Elige destinos desde categorias, productos o enlaces
                        personalizados.
                    </p>
                </div>
                <div className="flex items-center gap-2">
                    <select
                        value={currentStoreId ?? ''}
                        onChange={onStoreChange}
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        {stores.map((store) => (
                            <option key={store.id} value={store.id}>
                                {store.label}
                            </option>
                        ))}
                    </select>
                    {can('settings.storefront') && currentStoreId && (
                        <Button onClick={() => setAddingParentId('root')}>
                            <Plus className="mr-1 size-4" />
                            Nuevo item
                        </Button>
                    )}
                </div>
            </div>

            <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                {tree.length === 0 && addingParentId !== 'root' ? (
                    <p className="py-8 text-center text-sm text-neutral-500">
                        No hay items de menu en esta tienda.
                    </p>
                ) : (
                    <ul className="space-y-2">
                        {tree.map((item) => (
                            <TreeNode
                                key={item.id}
                                item={item}
                                storeId={currentStoreId}
                                depth={0}
                                can={can}
                                categories={categories}
                                products={products}
                                pages={pages}
                                onDelete={setDeleteTarget}
                                editingId={editingId}
                                setEditingId={setEditingId}
                                addingParentId={addingParentId}
                                setAddingParentId={setAddingParentId}
                            />
                        ))}
                        {addingParentId === 'root' && (
                            <li className="mt-2">
                                <AddForm
                                    storeId={currentStoreId}
                                    parentId={null}
                                    categories={categories}
                                    products={products}
                                    pages={pages}
                                    defaultSortOrder={tree.length}
                                    onDone={() => setAddingParentId(null)}
                                />
                            </li>
                        )}
                    </ul>
                )}
            </div>

            <ConfirmDialog
                open={deleteTarget !== null}
                onOpenChange={(open) => {
                    if (!open && !deleting) {
                        setDeleteTarget(null);
                    }
                }}
                onConfirm={destroyItem}
                loading={deleting}
                title="Eliminar item del menú"
                description={
                    deleteTarget ? (
                        <>
                            Vas a eliminar{' '}
                            <span className="font-semibold text-foreground">
                                {deleteTarget.label}
                            </span>
                            {deleteTarget.children.length > 0
                                ? ' y todos sus subitems.'
                                : '.'}{' '}
                            Esta acción no se puede deshacer.
                        </>
                    ) : null
                }
                confirmLabel="Eliminar"
            />
        </>
    );
}

function TreeNode({
    item,
    storeId,
    depth,
    can,
    categories,
    products,
    pages,
    onDelete,
    editingId,
    setEditingId,
    addingParentId,
    setAddingParentId,
}: {
    item: MenuItem;
    storeId: number | null;
    depth: number;
    can: (permission: string) => boolean;
    categories: CategoryOption[];
    products: ProductOption[];
    pages: PageOption[];
    onDelete: (item: MenuItem) => void;
    editingId: number | null;
    setEditingId: (id: number | null) => void;
    addingParentId: number | 'root' | null;
    setAddingParentId: (id: number | 'root' | null) => void;
}) {
    const isEditing = editingId === item.id;
    const isAddingChild = addingParentId === item.id;

    return (
        <li>
            <div
                className="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800"
                style={{ paddingLeft: `${depth * 1.5 + 0.5}rem` }}
            >
                <span className="flex min-w-0 items-center gap-2 text-sm">
                    <GripVertical className="size-3.5 shrink-0 cursor-grab text-neutral-300" />
                    <span className="truncate">{item.label}</span>
                    <Badge variant="outline" className="shrink-0 text-[10px]">
                        {TYPE_LABELS[item.type] ?? item.type}
                    </Badge>
                    {item.expand_products && (
                        <Badge className="shrink-0 text-[10px]">
                            Mega menu
                        </Badge>
                    )}
                    {!item.is_active && (
                        <Badge
                            variant="secondary"
                            className="shrink-0 text-[10px]"
                        >
                            Oculto
                        </Badge>
                    )}
                </span>
                <div className="flex shrink-0 items-center gap-2">
                    {can('settings.storefront') && (
                        <Button
                            variant="ghost"
                            size="sm"
                            onClick={() => setAddingParentId(item.id)}
                        >
                            <Plus className="size-3.5" />
                        </Button>
                    )}
                    {can('settings.storefront') && !isEditing && (
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => setEditingId(item.id)}
                        >
                            Editar
                        </Button>
                    )}
                    {can('settings.storefront') && (
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => onDelete(item)}
                        >
                            <X className="size-3.5" />
                        </Button>
                    )}
                </div>
            </div>

            {isEditing && (
                <div
                    className="mb-1 rounded-md border border-dashed border-neutral-300 p-3 dark:border-neutral-700"
                    style={{ marginLeft: `${depth * 1.5 + 0.5}rem` }}
                >
                    <EditForm
                        item={item}
                        categories={categories}
                        products={products}
                        pages={pages}
                        onDone={() => setEditingId(null)}
                    />
                </div>
            )}

            {isAddingChild && (
                <div
                    className="mb-1 rounded-md border border-dashed border-neutral-300 p-3 dark:border-neutral-700"
                    style={{ marginLeft: `${(depth + 1) * 1.5 + 0.5}rem` }}
                >
                    <AddForm
                        storeId={storeId}
                        parentId={item.id}
                        categories={categories}
                        products={products}
                        pages={pages}
                        defaultSortOrder={item.children.length}
                        onDone={() => setAddingParentId(null)}
                    />
                </div>
            )}

            {item.children.length > 0 && (
                <ul className="space-y-1">
                    {item.children.map((child) => (
                        <TreeNode
                            key={child.id}
                            item={child}
                            storeId={storeId}
                            depth={depth + 1}
                            can={can}
                            categories={categories}
                            products={products}
                            pages={pages}
                            onDelete={onDelete}
                            editingId={editingId}
                            setEditingId={setEditingId}
                            addingParentId={addingParentId}
                            setAddingParentId={setAddingParentId}
                        />
                    ))}
                </ul>
            )}
        </li>
    );
}

function AddForm({
    storeId,
    parentId,
    categories,
    products,
    pages,
    defaultSortOrder,
    onDone,
}: {
    storeId: number | null;
    parentId: number | null;
    categories: CategoryOption[];
    products: ProductOption[];
    pages: PageOption[];
    defaultSortOrder: number;
    onDone: () => void;
}) {
    const form = useForm<MenuFormData>({
        store_id: storeId,
        parent_id: parentId,
        type: 'custom',
        label: '',
        url: '',
        category_id: null,
        product_id: null,
        page_id: null,
        is_active: true,
        expand_products: false,
        sort_order: defaultSortOrder,
    });

    const save = () => {
        form.post(headerMenu.store().url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <MenuItemForm
            form={form}
            categories={categories}
            products={products}
            pages={pages}
            onSubmit={save}
            onCancel={onDone}
            submitLabel="Crear"
        />
    );
}

function EditForm({
    item,
    categories,
    products,
    pages,
    onDone,
}: {
    item: MenuItem;
    categories: CategoryOption[];
    products: ProductOption[];
    pages: PageOption[];
    onDone: () => void;
}) {
    const form = useForm<MenuFormData>({
        store_id: item.store_id,
        parent_id: item.parent_id,
        type: item.type === 'link' ? 'custom' : item.type,
        label: item.label,
        url: item.url ?? '',
        category_id: item.category_id,
        product_id: item.product_id,
        page_id: item.page_id,
        is_active: item.is_active,
        expand_products: item.expand_products,
        sort_order: item.sort_order,
    });

    const update = () => {
        form.put(headerMenu.update(item.id).url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <MenuItemForm
            form={form}
            categories={categories}
            products={products}
            pages={pages}
            onSubmit={update}
            onCancel={onDone}
            submitLabel="Guardar"
        />
    );
}

function MenuItemForm({
    form,
    categories,
    products,
    pages,
    onSubmit,
    onCancel,
    submitLabel,
}: {
    form: ReturnType<typeof useForm<MenuFormData>>;
    categories: CategoryOption[];
    products: ProductOption[];
    pages: PageOption[];
    onSubmit: () => void;
    onCancel: () => void;
    submitLabel: string;
}) {
    const { data, setData, processing } = form;
    const supportsMegaMenu =
        data.type === 'all_categories' || data.type === 'category';
    const canSubmit =
        data.label.trim().length > 0 &&
        (data.type !== 'custom' || data.url.trim().length > 0) &&
        (data.type !== 'category' || data.category_id !== null) &&
        (data.type !== 'product' || data.product_id !== null) &&
        (data.type !== 'page' || data.page_id !== null);

    const changeType = (type: string) => {
        const nextType = type as LinkType;

        setData('type', nextType);
        setData('url', nextType === 'custom' ? data.url : '');
        setData('category_id', null);
        setData('product_id', null);
        setData('page_id', null);
        setData(
            'expand_products',
            nextType === 'all_categories' || nextType === 'category'
                ? data.expand_products
                : false,
        );
    };

    return (
        <div className="grid gap-3 md:grid-cols-[minmax(10rem,14rem)_minmax(5rem,7rem)_minmax(10rem,1fr)_minmax(12rem,1.2fr)_auto] md:items-end">
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Tipo</Label>
                <Select value={data.type} onValueChange={changeType}>
                    <SelectTrigger>
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all_categories">
                            Todas las categorias
                        </SelectItem>
                        <SelectItem value="category">Categoria</SelectItem>
                        <SelectItem value="product">Producto</SelectItem>
                        <SelectItem value="page">Pagina</SelectItem>
                        <SelectItem value="custom">
                            URL personalizada
                        </SelectItem>
                    </SelectContent>
                </Select>
            </div>

            <div className="flex flex-col gap-1">
                <Label className="text-xs">Orden</Label>
                <Input
                    type="number"
                    min={0}
                    value={data.sort_order}
                    onChange={(event: ChangeEvent<HTMLInputElement>) =>
                        setData('sort_order', Number(event.target.value))
                    }
                />
            </div>

            <div className="flex flex-col gap-1">
                <Label className="text-xs">Etiqueta</Label>
                <Input
                    value={data.label}
                    onChange={(event: ChangeEvent<HTMLInputElement>) =>
                        setData('label', event.target.value)
                    }
                />
            </div>

            <MenuTargetField
                form={form}
                categories={categories}
                products={products}
                pages={pages}
            />

            <div className="flex flex-wrap items-center gap-2">
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={`active-${data.parent_id ?? 'root'}-${submitLabel}`}
                        checked={data.is_active}
                        onCheckedChange={(value) =>
                            setData('is_active', value === true)
                        }
                    />
                    <Label
                        htmlFor={`active-${data.parent_id ?? 'root'}-${submitLabel}`}
                        className="text-xs"
                    >
                        Visible
                    </Label>
                </div>
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={`mega-${data.parent_id ?? 'root'}-${submitLabel}`}
                        checked={data.expand_products}
                        disabled={!supportsMegaMenu}
                        onCheckedChange={(value) =>
                            setData('expand_products', value === true)
                        }
                    />
                    <Label
                        htmlFor={`mega-${data.parent_id ?? 'root'}-${submitLabel}`}
                        className="text-xs"
                    >
                        Mega menu
                    </Label>
                </div>
                <Button
                    size="sm"
                    onClick={onSubmit}
                    disabled={!canSubmit || processing}
                >
                    {submitLabel}
                </Button>
                <Button variant="outline" size="sm" onClick={onCancel}>
                    Cancelar
                </Button>
            </div>
        </div>
    );
}

function MenuTargetField({
    form,
    categories,
    products,
    pages,
}: {
    form: ReturnType<typeof useForm<MenuFormData>>;
    categories: CategoryOption[];
    products: ProductOption[];
    pages: PageOption[];
}) {
    const { data, setData } = form;

    if (data.type === 'all_categories') {
        return (
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Destino</Label>
                <Input value="Raices activas del catalogo" disabled />
            </div>
        );
    }

    if (data.type === 'category') {
        return (
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Categoria</Label>
                <Select
                    value={
                        data.category_id ? String(data.category_id) : undefined
                    }
                    onValueChange={(value) =>
                        setData('category_id', Number(value))
                    }
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Selecciona categoria" />
                    </SelectTrigger>
                    <SelectContent>
                        {categories.map((category) => (
                            <SelectItem
                                key={category.id}
                                value={String(category.id)}
                            >
                                {category.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        );
    }

    if (data.type === 'product') {
        return (
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Producto</Label>
                <Select
                    value={
                        data.product_id ? String(data.product_id) : undefined
                    }
                    onValueChange={(value) =>
                        setData('product_id', Number(value))
                    }
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Selecciona producto" />
                    </SelectTrigger>
                    <SelectContent>
                        {products.map((product) => (
                            <SelectItem
                                key={product.id}
                                value={String(product.id)}
                            >
                                {product.label} ({product.sku})
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        );
    }

    if (data.type === 'page') {
        return (
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Pagina</Label>
                <Select
                    value={data.page_id ? String(data.page_id) : undefined}
                    onValueChange={(value) => setData('page_id', Number(value))}
                >
                    <SelectTrigger>
                        <SelectValue placeholder="Selecciona pagina" />
                    </SelectTrigger>
                    <SelectContent>
                        {pages.map((page) => (
                            <SelectItem key={page.id} value={String(page.id)}>
                                {page.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-1">
            <Label className="text-xs">URL</Label>
            <Input
                value={data.url}
                onChange={(event: ChangeEvent<HTMLInputElement>) =>
                    setData('url', event.target.value)
                }
                placeholder="/contacto"
            />
        </div>
    );
}
