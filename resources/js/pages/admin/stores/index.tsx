import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import stores from '@/routes/admin/stores';

type StoreRow = {
    id: number;
    code: string;
    name: string;
    website: string;
    is_default: boolean;
    is_active: boolean;
    domains_count: number;
};

export default function StoresIndex({ stores: items }: { stores: StoreRow[] }) {
    const destroy = (store: StoreRow) => {
        if (confirm(`¿Eliminar la tienda ${store.name}?`)) {
            router.delete(stores.destroy(store.id).url);
        }
    };

    return (
        <>
            <Head title="Tiendas" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Tiendas</h1>
                <Button asChild>
                    <Link href={stores.create()}>Nueva tienda</Link>
                </Button>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Website</th>
                            <th className="px-4 py-3 font-medium">Código</th>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Dominios</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {items.map((store) => (
                            <tr key={store.id}>
                                <td className="px-4 py-3 text-neutral-500">{store.website}</td>
                                <td className="px-4 py-3 font-mono text-xs">{store.code}</td>
                                <td className="px-4 py-3">
                                    {store.name}{' '}
                                    {store.is_default && <Badge variant="secondary">por defecto</Badge>}
                                </td>
                                <td className="px-4 py-3 text-neutral-500">{store.domains_count}</td>
                                <td className="px-4 py-3">
                                    <Badge variant={store.is_active ? 'default' : 'outline'}>
                                        {store.is_active ? 'Activa' : 'Inactiva'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={stores.edit(store.id)}>Editar</Link>
                                        </Button>
                                        {!store.is_default && (
                                            <Button variant="destructive" size="sm" onClick={() => destroy(store)}>
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
