import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import attributes from '@/routes/admin/attributes';

type AttributeRow = {
    id: number;
    code: string;
    name: string;
    type: string;
    is_filterable: boolean;
    is_configurable: boolean;
    options_count: number;
};

export default function AttributesIndex({ attributes: rows }: { attributes: AttributeRow[] }) {
    const { can } = usePermissions();

    const destroy = (attribute: AttributeRow) => {
        if (confirm(`¿Eliminar el atributo "${attribute.code}"?`)) {
            router.delete(attributes.destroy(attribute.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Atributos" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Atributos</h1>
                {can('catalog.attributes.create') && (
                    <Button asChild>
                        <Link href={attributes.create()}>Nuevo atributo</Link>
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Código</th>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Tipo</th>
                            <th className="px-4 py-3 font-medium">Opciones</th>
                            <th className="px-4 py-3 font-medium">Flags</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {rows.map((attribute) => (
                            <tr key={attribute.id}>
                                <td className="px-4 py-3 font-mono text-xs">{attribute.code}</td>
                                <td className="px-4 py-3">{attribute.name}</td>
                                <td className="px-4 py-3">{attribute.type}</td>
                                <td className="px-4 py-3">{attribute.options_count || '—'}</td>
                                <td className="px-4 py-3">
                                    <div className="flex gap-1">
                                        {attribute.is_filterable && <Badge variant="outline">Filtrable</Badge>}
                                        {attribute.is_configurable && <Badge variant="outline">Configurable</Badge>}
                                    </div>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {can('catalog.attributes.edit') && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={attributes.edit(attribute.id)}>Editar</Link>
                                            </Button>
                                        )}
                                        {can('catalog.attributes.delete') && (
                                            <Button variant="destructive" size="sm" onClick={() => destroy(attribute)}>
                                                Eliminar
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {rows.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                    No hay atributos.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
