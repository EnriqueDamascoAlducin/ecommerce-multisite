import { Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import catalogRules from '@/routes/admin/catalog-rules';
import { ACTION_LABELS } from './rule-fields';

type RuleRow = {
    id: number;
    name: string;
    action: string;
    value: string;
    website: string | null;
    category: string | null;
    priority: number;
    is_active: boolean;
    ends_at: string | null;
};

export default function CatalogRulesIndex({ rules }: { rules: RuleRow[] }) {
    const { can } = usePermissions();

    const describeValue = (rule: RuleRow) => (rule.action === 'percent' ? `${Number(rule.value)}%` : `$${rule.value}`);

    const remove = (rule: RuleRow) => {
        if (confirm(`¿Eliminar la regla «${rule.name}»?`)) {
            router.delete(catalogRules.destroy(rule.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Reglas de catálogo" />
            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Reglas de catálogo</h1>
                    <p className="text-sm text-neutral-500">Descuentos automáticos de precio por sitio o categoría.</p>
                </div>
                {can('promotions.create') && (
                    <Button asChild><Link href={catalogRules.create()}>Nueva regla</Link></Button>
                )}
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Acción</th>
                            <th className="px-4 py-3 font-medium">Alcance</th>
                            <th className="px-4 py-3 font-medium">Prioridad</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {rules.map((rule) => (
                            <tr key={rule.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td className="px-4 py-3 font-medium">{rule.name}</td>
                                <td className="px-4 py-3">{ACTION_LABELS[rule.action] ?? rule.action} · {describeValue(rule)}</td>
                                <td className="px-4 py-3 text-neutral-500">
                                    {rule.website ?? 'Todos los sitios'}{rule.category ? ` · ${rule.category}` : ' · Todo el catálogo'}
                                </td>
                                <td className="px-4 py-3 text-neutral-500">{rule.priority}</td>
                                <td className="px-4 py-3">
                                    {rule.is_active ? <Badge>Activa</Badge> : <Badge variant="outline">Inactiva</Badge>}
                                </td>
                                <td className="px-4 py-3 text-right">
                                    {can('promotions.edit') && (
                                        <Link href={catalogRules.edit(rule.id)} className="text-sm hover:underline">Editar</Link>
                                    )}
                                    {can('promotions.delete') && (
                                        <button type="button" onClick={() => remove(rule)} className="ml-3 text-sm text-red-600 hover:underline">Eliminar</button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {rules.length === 0 && (
                            <tr><td colSpan={6} className="px-4 py-8 text-center text-neutral-500">No hay reglas de catálogo.</td></tr>
                        )}
                    </tbody>
                </table>
            </div>
        </>
    );
}
