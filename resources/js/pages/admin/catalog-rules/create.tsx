import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import catalogRules from '@/routes/admin/catalog-rules';
import CatalogRuleFields, { type CatalogRuleData } from './rule-fields';

export default function CatalogRuleCreate({
    websites,
    categories,
    actions,
}: {
    websites: { id: number; name: string }[];
    categories: { id: number; label: string }[];
    actions: string[];
}) {
    const { data, setData, post, processing, errors } = useForm<CatalogRuleData>({
        name: '',
        description: '',
        website_id: '',
        category_id: '',
        action: actions[0] ?? 'percent',
        value: '15',
        priority: '0',
        starts_at: '',
        ends_at: '',
        is_active: true,
    });

    const submit = () => post(catalogRules.store().url);

    return (
        <>
            <Head title="Nueva regla de catálogo" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nueva regla de catálogo</h1>
                <Button variant="outline" asChild><Link href={catalogRules.index()}>Volver</Link></Button>
            </div>

            <div className="max-w-2xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <CatalogRuleFields data={data} setData={setData} errors={errors} websites={websites} categories={categories} actions={actions} />
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>Crear regla</Button>
                </div>
            </div>
        </>
    );
}
