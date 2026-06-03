import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import inventorySources from '@/routes/admin/inventory-sources';

type SourceRow = {
    id: number;
    code: string;
    name: string;
    is_default: boolean;
    is_active: boolean;
    stocks_count: number;
};

export default function SourcesIndex({ sources }: { sources: SourceRow[] }) {
    const { can } = usePermissions();

    const destroy = (source: SourceRow) => {
        if (confirm(`¿Eliminar el almacén "${source.code}"?`)) {
            router.delete(inventorySources.destroy(source.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Almacenes" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Almacenes</h1>
                {can('inventory.adjust') && (
                    <Button asChild>
                        <Link href={inventorySources.create()}>Nuevo almacén</Link>
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Código</th>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Productos con stock</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {sources.map((source) => (
                            <tr key={source.id}>
                                <td className="px-4 py-3 font-mono text-xs">{source.code}</td>
                                <td className="px-4 py-3">
                                    <span className="flex items-center gap-2">
                                        {source.name}
                                        {source.is_default && <Badge>Por defecto</Badge>}
                                    </span>
                                </td>
                                <td className="px-4 py-3">{source.stocks_count}</td>
                                <td className="px-4 py-3">
                                    <Badge variant={source.is_active ? 'default' : 'outline'}>
                                        {source.is_active ? 'Activo' : 'Inactivo'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {can('inventory.adjust') && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={inventorySources.edit(source.id)}>Editar</Link>
                                            </Button>
                                        )}
                                        {can('inventory.adjust') && !source.is_default && (
                                            <Button variant="destructive" size="sm" onClick={() => destroy(source)}>
                                                Eliminar
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {sources.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                                    No hay almacenes.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
