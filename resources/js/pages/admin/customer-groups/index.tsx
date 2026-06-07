import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import customerGroups from '@/routes/admin/customer-groups';

type GroupRow = {
    id: number;
    name: string;
    code: string;
    color: string;
    description: string | null;
    is_default: boolean;
    website: string;
    customers_count: number;
};

export default function CustomerGroupsIndex({ groups }: { groups: GroupRow[] }) {
    const { can } = usePermissions();

    const remove = (group: GroupRow) => {
        if (confirm(`¿Eliminar el grupo «${group.name}»? Los clientes quedarán sin grupo.`)) {
            router.delete(customerGroups.destroy(group.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Grupos de clientes" />
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Grupos de clientes</h1>
                    <p className="text-sm text-neutral-500">Segmenta a tus clientes por sitio (General, Mayorista, VIP…).</p>
                </div>
                {can('customer_groups.create') && (
                    <Button asChild>
                        <Link href={customerGroups.create()}>Nuevo grupo</Link>
                    </Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Grupo</th>
                            <th className="px-4 py-3 font-medium">Código</th>
                            <th className="px-4 py-3 font-medium">Sitio</th>
                            <th className="px-4 py-3 font-medium">Clientes</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {groups.map((group) => (
                            <tr key={group.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-2">
                                        <Badge className="border-transparent" style={{ backgroundColor: group.color, color: '#ffffff' }}>
                                            {group.name}
                                        </Badge>
                                        {group.is_default && <span className="text-xs text-neutral-400">por defecto</span>}
                                    </div>
                                    {group.description && <p className="mt-1 text-xs text-neutral-500">{group.description}</p>}
                                </td>
                                <td className="px-4 py-3 font-mono text-neutral-500">{group.code}</td>
                                <td className="px-4 py-3 text-neutral-500">{group.website}</td>
                                <td className="px-4 py-3 text-neutral-500">{group.customers_count}</td>
                                <td className="px-4 py-3 text-right">
                                    {can('customer_groups.edit') && (
                                        <Link href={customerGroups.edit(group.id)} className="text-sm hover:underline">
                                            Editar
                                        </Link>
                                    )}
                                    {can('customer_groups.delete') && (
                                        <button type="button" onClick={() => remove(group)} className="ml-3 text-sm text-red-600 hover:underline">
                                            Eliminar
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {groups.length === 0 && (
                            <tr>
                                <td colSpan={5} className="px-4 py-8 text-center text-neutral-500">
                                    No hay grupos todavía.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
