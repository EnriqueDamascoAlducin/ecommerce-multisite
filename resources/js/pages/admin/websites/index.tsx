import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import websites from '@/routes/admin/websites';

type WebsiteRow = {
    id: number;
    code: string;
    name: string;
    is_default: boolean;
    stores_count: number;
};

export default function WebsitesIndex({ websites: items }: { websites: WebsiteRow[] }) {
    const destroy = (website: WebsiteRow) => {
        if (confirm(`¿Eliminar el website ${website.name}? Se eliminarán sus tiendas.`)) {
            router.delete(websites.destroy(website.id).url);
        }
    };

    return (
        <>
            <Head title="Websites" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Websites</h1>
                <Button asChild>
                    <Link href={websites.create()}>Nuevo website</Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Código</th>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Tiendas</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {items.map((website) => (
                            <tr key={website.id}>
                                <td className="px-4 py-3 font-mono text-xs">{website.code}</td>
                                <td className="px-4 py-3">
                                    {website.name}{' '}
                                    {website.is_default && <Badge variant="secondary">por defecto</Badge>}
                                </td>
                                <td className="px-4 py-3 text-neutral-500">{website.stores_count}</td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={websites.edit(website.id)}>Editar</Link>
                                        </Button>
                                        {!website.is_default && (
                                            <Button variant="destructive" size="sm" onClick={() => destroy(website)}>
                                                Eliminar
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </>
    );
}
