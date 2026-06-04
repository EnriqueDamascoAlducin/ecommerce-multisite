import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import promotions from '@/routes/admin/promotions';
import { ACTION_LABELS } from './rule-fields';

type RuleRow = {
    id: number;
    name: string;
    coupon_code: string | null;
    action: string;
    value: string;
    website: string | null;
    is_active: boolean;
    times_used: number;
    usage_limit: number | null;
    ends_at: string | null;
};

export default function PromotionsIndex({ rules }: { rules: RuleRow[] }) {
    const { can } = usePermissions();

    const describeValue = (rule: RuleRow) => {
        if (rule.action === 'percent') return `${Number(rule.value)}%`;
        if (rule.action === 'fixed') return `$${rule.value}`;
        return '—';
    };

    const remove = (rule: RuleRow) => {
        if (confirm(`¿Eliminar la regla «${rule.name}»?`)) {
            router.delete(promotions.destroy(rule.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Promociones" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Promociones</h1>
                {can('promotions.create') && (
                    <Button asChild><Link href={promotions.create()}>Nueva regla</Link></Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Cupón</th>
                            <th className="px-4 py-3 font-medium">Acción</th>
                            <th className="px-4 py-3 font-medium">Sitio</th>
                            <th className="px-4 py-3 font-medium">Usos</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {rules.map((rule) => (
                            <tr key={rule.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td className="px-4 py-3 font-medium">{rule.name}</td>
                                <td className="px-4 py-3">
                                    {rule.coupon_code
                                        ? <span className="font-mono text-xs">{rule.coupon_code}</span>
                                        : <Badge variant="outline">Automática</Badge>}
                                </td>
                                <td className="px-4 py-3">{ACTION_LABELS[rule.action] ?? rule.action} · {describeValue(rule)}</td>
                                <td className="px-4 py-3 text-neutral-500">{rule.website ?? 'Todos'}</td>
                                <td className="px-4 py-3 text-neutral-500">{rule.times_used}{rule.usage_limit ? ` / ${rule.usage_limit}` : ''}</td>
                                <td className="px-4 py-3">
                                    {rule.is_active
                                        ? <Badge>Activa</Badge>
                                        : <Badge variant="outline">Inactiva</Badge>}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {can('promotions.edit') && (
                                        <Link href={promotions.edit(rule.id)} className="text-sm hover:underline">Editar</Link>
                                    )}
                                    {can('promotions.delete') && (
                                        <button type="button" onClick={() => remove(rule)} className="ml-3 text-sm text-red-600 hover:underline">Eliminar</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {rules.length === 0 && (
                            <tr><td colSpan={7} className="px-4 py-8 text-center text-neutral-500">No hay reglas de carrito.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
