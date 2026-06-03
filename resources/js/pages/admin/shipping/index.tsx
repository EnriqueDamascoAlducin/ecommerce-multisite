import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import shipping from '@/routes/admin/shipping';
import shippingStores from '@/routes/admin/shipping-stores';

type MethodRow = {
    id: number;
    code: string;
    name: string;
    type: string;
    is_active: boolean;
    stores_count: number;
};

const TYPE_LABELS: Record<string, string> = {
    flat_rate: 'Tarifa fija',
    free_shipping: 'Envío gratis',
    pickup: 'Recoger en tienda',
};

export default function ShippingIndex({ methods }: { methods: MethodRow[] }) {
    const { can } = usePermissions();

    const destroy = (method: MethodRow) => {
        if (confirm(`¿Eliminar el método "${method.code}"?`)) {
            router.delete(shipping.destroy(method.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Envíos" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Métodos de envío</h1>
                <div className="flex gap-2">
                    <Button variant="outline" asChild>
                        <Link href={shippingStores.edit()}>Configurar por tienda</Link>
                    </Button>
                    <Button asChild>
                        <Link href={shipping.create()}>Nuevo método</Link>
                    </Button>
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Código</th>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Tipo</th>
                            <th className="px-4 py-3 font-medium">Tiendas</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {methods.map((method) => (
                            <tr key={method.id}>
                                <td className="px-4 py-3 font-mono text-xs">{method.code}</td>
                                <td className="px-4 py-3">{method.name}</td>
                                <td className="px-4 py-3">{TYPE_LABELS[method.type] ?? method.type}</td>
                                <td className="px-4 py-3">{method.stores_count}</td>
                                <td className="px-4 py-3">
                                    <Badge variant={method.is_active ? 'default' : 'outline'}>
                                        {method.is_active ? 'Activo' : 'Inactivo'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {can('settings.shipping') && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={shipping.edit(method.id)}>Editar</Link>
                                            </Button>
                                        )}
                                        {can('settings.shipping') && (
                                            <Button variant="destructive" size="sm" onClick={() => destroy(method)}>
                                                Eliminar
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {methods.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                    No hay métodos de envío.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
