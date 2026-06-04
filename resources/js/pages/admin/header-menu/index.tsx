import { Head, router, useForm } from '@inertiajs/react';
import { GripVertical, Plus, X } from 'lucide-react';
import { useState } from 'react';
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

type MenuItem = {
    id: number;
    type: string;
    label: string;
    url: string | null;
    expand_products: boolean;
    children: MenuItem[];
    products: unknown[];
};

type StoreOption = { id: number; label: string };

const TYPE_LABELS: Record<string, string> = {
    link: 'Enlace',
    category: 'Categoría',
    custom: 'Personalizado',
};

export default function HeaderMenuIndex({
    stores,
    currentStoreId,
    tree,
}: {
    stores: StoreOption[];
    currentStoreId: number | null;
    tree: MenuItem[];
}) {
    const { can } = usePermissions();
    const [editingId, setEditingId] = useState<number | null>(null);
    const [addingParentId, setAddingParentId] = useState<number | 'root' | null>(null);

    const onStoreChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        router.get(headerMenu.index().url, { store_id: event.target.value }, { preserveState: false });
    };

    const destroyItem = (item: MenuItem) => {
        if (confirm(`¿Eliminar "${item.label}" y sus hijos?`)) {
            router.delete(headerMenu.destroy(item.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Menú del header" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">Menú del header</h1>
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
                            Nuevo ítem
                        </Button>
                    )}
                </div>
            </div>

            <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                {tree.length === 0 && addingParentId !== 'root' ? (
                    <p className="py-8 text-center text-sm text-neutral-500">
                        No hay ítems de menú en esta tienda.
                    </p>
                ) : (
                    <ul className="space-y-1">
                        {tree.map((item) => (
                            <TreeNode
                                key={item.id}
                                item={item}
                                depth={0}
                                can={can}
                                onDelete={destroyItem}
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
                                    onDone={() => setAddingParentId(null)}
                                />
                            </li>
                        )}
                    </ul>
                )}
            </div>
        </>
    );
}

function TreeNode({
    item,
    depth,
    can,
    onDelete,
    editingId,
    setEditingId,
    addingParentId,
    setAddingParentId,
}: {
    item: MenuItem;
    depth: number;
    can: (permission: string) => boolean;
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
                <span className="flex items-center gap-2 text-sm">
                    <GripVertical className="size-3.5 cursor-grab text-neutral-300" />
                    {item.label}
                    <Badge variant="outline" className="text-[10px]">{TYPE_LABELS[item.type] ?? item.type}</Badge>
                    {item.expand_products && <Badge className="text-[10px]">Mega menú</Badge>}
                </span>
                <div className="flex items-center gap-2">
                    {can('settings.storefront') && (
                        <Button variant="ghost" size="sm" onClick={() => setAddingParentId(item.id)}>
                            <Plus className="size-3.5" />
                        </Button>
                    )}
                    {can('settings.storefront') && !isEditing && (
                        <Button variant="outline" size="sm" onClick={() => setEditingId(item.id)}>
                            Editar
                        </Button>
                    )}
                    {can('settings.storefront') && (
                        <Button variant="destructive" size="sm" onClick={() => onDelete(item)}>
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
                    <EditForm item={item} onDone={() => setEditingId(null)} />
                </div>
            )}

            {isAddingChild && (
                <div
                    className="mb-1 rounded-md border border-dashed border-neutral-300 p-3 dark:border-neutral-700"
                    style={{ marginLeft: `${(depth + 1) * 1.5 + 0.5}rem` }}
                >
                    <AddForm storeId={null} parentId={item.id} onDone={() => setAddingParentId(null)} />
                </div>
            )}

            {item.children.length > 0 && (
                <ul className="space-y-1">
                    {item.children.map((child) => (
                        <TreeNode
                            key={child.id}
                            item={child}
                            depth={depth + 1}
                            can={can}
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
    onDone,
}: {
    storeId: number | null;
    parentId: number | null;
    onDone: () => void;
}) {
    const { data, setData, post, processing } = useForm({
        store_id: storeId,
        parent_id: parentId,
        type: 'link',
        label: '',
        url: '',
        is_active: true,
        expand_products: false,
    });

    const save = () => {
        post(headerMenu.store().url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Tipo</Label>
                <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                    <SelectTrigger className="w-32">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="link">Enlace</SelectItem>
                        <SelectItem value="category">Categoría</SelectItem>
                        <SelectItem value="custom">Personalizado</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Etiqueta</Label>
                <Input
                    value={data.label}
                    onChange={(e) => setData('label', e.target.value)}
                    className="w-40"
                />
            </div>
            <div className="flex flex-col gap-1">
                <Label className="text-xs">URL</Label>
                <Input
                    value={data.url}
                    onChange={(e) => setData('url', e.target.value)}
                    className="w-48"
                />
            </div>
            <div className="flex items-center gap-2 pb-1">
                <Checkbox
                    id="add-expand"
                    checked={data.expand_products}
                    onCheckedChange={(v) => setData('expand_products', v === true)}
                />
                <Label htmlFor="add-expand" className="text-xs">Mostrar productos</Label>
            </div>
            <div className="flex items-center gap-2">
                <Button size="sm" onClick={save} disabled={!data.label || processing}>
                    Crear
                </Button>
                <Button variant="outline" size="sm" onClick={onDone}>
                    Cancelar
                </Button>
            </div>
        </div>
    );
}

function EditForm({ item, onDone }: { item: MenuItem; onDone: () => void }) {
    const { data, setData, put, processing } = useForm({
        type: item.type,
        label: item.label,
        url: item.url ?? '',
        is_active: true,
        expand_products: item.expand_products,
    });

    const update = () => {
        put(headerMenu.update(item.id).url, {
            preserveScroll: true,
            onSuccess: onDone,
        });
    };

    return (
        <div className="flex flex-wrap items-end gap-3">
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Tipo</Label>
                <Select value={data.type} onValueChange={(v) => setData('type', v)}>
                    <SelectTrigger className="w-32">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="link">Enlace</SelectItem>
                        <SelectItem value="category">Categoría</SelectItem>
                        <SelectItem value="custom">Personalizado</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div className="flex flex-col gap-1">
                <Label className="text-xs">Etiqueta</Label>
                <Input
                    value={data.label}
                    onChange={(e) => setData('label', e.target.value)}
                    className="w-40"
                />
            </div>
            <div className="flex flex-col gap-1">
                <Label className="text-xs">URL</Label>
                <Input
                    value={data.url}
                    onChange={(e) => setData('url', e.target.value)}
                    className="w-48"
                />
            </div>
            <div className="flex items-center gap-2 pb-1">
                <Checkbox
                    id="edit-expand"
                    checked={data.expand_products}
                    onCheckedChange={(v) => setData('expand_products', v === true)}
                />
                <Label htmlFor="edit-expand" className="text-xs">Mostrar productos</Label>
            </div>
            <div className="flex items-center gap-2">
                <Button size="sm" onClick={update} disabled={!data.label || processing}>
                    Guardar
                </Button>
                <Button variant="outline" size="sm" onClick={onDone}>
                    Cancelar
                </Button>
            </div>
        </div>
    );
}
