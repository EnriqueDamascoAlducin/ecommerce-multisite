import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import promotions from '@/routes/admin/promotions';
import RuleFields, { type RuleData } from './rule-fields';

export default function PromotionCreate({
    websites,
    actions,
}: {
    websites: { id: number; name: string }[];
    actions: string[];
}) {
    const { data, setData, post, processing, errors } = useForm<RuleData>({
        name: '',
        description: '',
        website_id: '',
        coupon_code: '',
        action: actions[0] ?? 'percent',
        value: '10',
        min_subtotal: '',
        starts_at: '',
        ends_at: '',
        is_active: true,
        usage_limit: '',
    });

    const submit = () => post(promotions.store().url);

    return (
        <>
            <Head title="Nueva regla" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nueva regla de carrito</h1>
                <Button variant="outline" asChild><Link href={promotions.index()}>Volver</Link></Button>
            </div>

            <div className="max-w-2xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <RuleFields data={data} setData={setData} errors={errors} websites={websites} actions={actions} />
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>Crear regla</Button>
                </div>
            </div>
        </>
    );
}
