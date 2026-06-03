import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import categories from '@/routes/admin/categories';

type TreeNode = {
    id: number;
    name: string;
    slug: string;
    is_active: boolean;
    children: TreeNode[];
};

type WebsiteOption = { id: number; name: string };

export default function CategoriesIndex({
    websites,
    currentWebsiteId,
    tree,
}: {
    websites: WebsiteOption[];
    currentWebsiteId: number | null;
    tree: TreeNode[];
}) {
    const { can } = usePermissions();

    const onWebsiteChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        router.get(categories.index().url, { website_id: event.target.value }, { preserveState: false });
    };

    const destroy = (node: TreeNode) => {
        if (confirm(`¿Eliminar la categoría "${node.name}" y sus subcategorías?`)) {
            router.delete(categories.destroy(node.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Categorías" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">Categorías</h1>
                <div className="flex items-center gap-2">
                    <select
                        value={currentWebsiteId ?? ''}
                        onChange={onWebsiteChange}
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        {websites.map((website) => (
                            <option key={website.id} value={website.id}>
                                {website.name}
                            </option>
                        ))}
                    </select>
                    {can('catalog.categories.create') && currentWebsiteId && (
                        <Button asChild>
                            <Link href={categories.create({ query: { website_id: currentWebsiteId } })}>Nueva categoría</Link>
                        </Button>
                    )}
                </div>
            </div>

            <div className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                {tree.length === 0 ? (
                    <p className="py-8 text-center text-sm text-neutral-500">No hay categorías en este website.</p>
                ) : (
                    <ul className="space-y-1">
                        {tree.map((node) => (
                            <CategoryRow key={node.id} node={node} depth={0} can={can} onDelete={destroy} />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function CategoryRow({
    node,
    depth,
    can,
    onDelete,
}: {
    node: TreeNode;
    depth: number;
    can: (permission: string) => boolean;
    onDelete: (node: TreeNode) => void;
}) {
    return (
        <li>
            <div
                className="flex items-center justify-between rounded-md px-2 py-1.5 hover:bg-neutral-50 dark:hover:bg-neutral-800"
                style={{ paddingLeft: `${depth * 1.5 + 0.5}rem` }}
            >
                <span className="flex items-center gap-2 text-sm">
                    {node.name}
                    {!node.is_active && <Badge variant="outline">Inactiva</Badge>}
                    <span className="font-mono text-xs text-neutral-400">/{node.slug}</span>
                </span>
                <div className="flex gap-2">
                    {can('catalog.categories.edit') && (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={categories.edit(node.id)}>Editar</Link>
                        </Button>
                    )}
                    {can('catalog.categories.delete') && (
                        <Button variant="destructive" size="sm" onClick={() => onDelete(node)}>
                            Eliminar
                        </Button>
                    )}
                </div>
            </div>
            {node.children.length > 0 && (
                <ul className="space-y-1">
                    {node.children.map((child) => (
                        <CategoryRow key={child.id} node={child} depth={depth + 1} can={can} onDelete={onDelete} />
                    ))}
                </ul>
            )}
        </li>
    );
}
