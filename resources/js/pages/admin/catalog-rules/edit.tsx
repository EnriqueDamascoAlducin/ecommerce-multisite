import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import catalogRules from '@/routes/admin/catalog-rules';
import CatalogRuleFields, { type CatalogRuleData } from './rule-fields';

type Rule = {
    id: number;
    name: string;
    description: string | null;
    website_id: number | null;
    category_id: number | null;
    action: string;
    value: string;
    priority: number;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
};

export default function CatalogRuleEdit({
    rule,
    websites,
    categories,
    actions,
}: {
    rule: Rule;
    websites: { id: number; name: string }[];
    categories: { id: number; label: string }[];
    actions: string[];
}) {
    const { data, setData, put, processing, errors } = useForm<CatalogRuleData>({
        name: rule.name,
        description: rule.description ?? '',
        website_id: rule.website_id ? String(rule.website_id) : '',
        category_id: rule.category_id ? String(rule.category_id) : '',
        action: rule.action,
        value: rule.value,
        priority: String(rule.priority),
        starts_at: rule.starts_at ?? '',
        ends_at: rule.ends_at ?? '',
        is_active: rule.is_active,
    });

    const submit = () => put(catalogRules.update(rule.id).url);

    return (
        <>
            <Head title={`Editar ${rule.name}`} />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar regla de catálogo</h1>
                <Button variant="outline" asChild><Link href={catalogRules.index()}>Volver</Link></Button>
            </div>

            <div className="max-w-2xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <CatalogRuleFields data={data} setData={setData} errors={errors} websites={websites} categories={categories} actions={actions} />
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>Guardar cambios</Button>
                </div>
            </div>
        </>
    );
}
