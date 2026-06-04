import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import promotions from '@/routes/admin/promotions';
import RuleFields, { type RuleData } from './rule-fields';

type Rule = {
    id: number;
    name: string;
    description: string | null;
    website_id: number | null;
    coupon_code: string | null;
    action: string;
    value: string;
    min_subtotal: string | null;
    starts_at: string | null;
    ends_at: string | null;
    is_active: boolean;
    usage_limit: number | null;
    times_used: number;
};

export default function PromotionEdit({
    rule,
    websites,
    actions,
}: {
    rule: Rule;
    websites: { id: number; name: string }[];
    actions: string[];
}) {
    const { data, setData, put, processing, errors } = useForm<RuleData>({
        name: rule.name,
        description: rule.description ?? '',
        website_id: rule.website_id ? String(rule.website_id) : '',
        coupon_code: rule.coupon_code ?? '',
        action: rule.action,
        value: rule.value,
        min_subtotal: rule.min_subtotal ?? '',
        starts_at: rule.starts_at ?? '',
        ends_at: rule.ends_at ?? '',
        is_active: rule.is_active,
        usage_limit: rule.usage_limit ? String(rule.usage_limit) : '',
    });

    const submit = () => put(promotions.update(rule.id).url);

    return (
        <>
            <Head title={`Editar ${rule.name}`} />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar regla</h1>
                <Button variant="outline" asChild><Link href={promotions.index()}>Volver</Link></Button>
            </div>

            <div className="max-w-2xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <RuleFields data={data} setData={setData} errors={errors} websites={websites} actions={actions} />
                <p className="mt-4 text-sm text-neutral-500">Usos registrados: <strong>{rule.times_used}</strong></p>
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>Guardar cambios</Button>
                </div>
            </div>
        </>
    );
}
