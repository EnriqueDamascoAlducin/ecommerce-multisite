import { Head, Link, router } from '@inertiajs/react';
import { ProductLabels } from '@/components/product-labels';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import productLabels from '@/routes/admin/product-labels';

type LabelRow = {
    id: number;
    text: string;
    text_color: string;
    background_color: string;
    website: string | null;
    is_active: boolean;
    sort_order: number;
};

export default function ProductLabelsIndex({ labels }: { labels: LabelRow[] }) {
    const { can } = usePermissions();

    const remove = (label: LabelRow) => {
        if (confirm(`¿Eliminar la etiqueta «${label.text}»?`)) {
            router.delete(productLabels.destroy(label.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Etiquetas" />
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Etiquetas</h1>
                    <p className="text-sm text-neutral-500">Badges personalizables para resaltar productos en el catálogo.</p>
                </div>
                {can('catalog.labels.create') && (
                    <Button asChild>
                        <Link href={productLabels.create()}>Nueva etiqueta</Link>
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Vista previa</th>
                            <th className="px-4 py-3 font-medium">Sitio</th>
                            <th className="px-4 py-3 font-medium">Orden</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {labels.map((label) => (
                            <tr key={label.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td className="px-4 py-3">
                                    <ProductLabels
                                        labels={[{ text: label.text, text_color: label.text_color, background_color: label.background_color }]}
                                    />
                                </td>
                                <td className="px-4 py-3 text-neutral-500">{label.website ?? '—'}</td>
                                <td className="px-4 py-3 text-neutral-500">{label.sort_order}</td>
                                <td className="px-4 py-3">
                                    {label.is_active ? <Badge>Activa</Badge> : <Badge variant="outline">Inactiva</Badge>}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {can('catalog.labels.edit') && (
                                        <Link href={productLabels.edit(label.id)} className="text-sm hover:underline">Editar</Link>
                                    )}
                                    {can('catalog.labels.delete') && (
                                        <button type="button" onClick={() => remove(label)} className="ml-3 text-sm text-red-600 hover:underline">
                                            Eliminar
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {labels.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">No hay etiquetas.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
