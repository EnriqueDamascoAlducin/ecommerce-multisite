import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import customerGroups from '@/routes/admin/customer-groups';
import CustomerGroupFields, { type GroupData } from './group-fields';

export default function CustomerGroupCreate({ websites }: { websites: { id: number; name: string }[] }) {
    const { data, setData, post, processing, errors } = useForm<GroupData>({
        website_id: '',
        name: '',
        code: '',
        description: '',
        color: '#6366f1',
        is_default: false,
    });

    const submit = () => post(customerGroups.store().url);

    return (
        <>
            <Head title="Nuevo grupo de clientes" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo grupo de clientes</h1>
                <Button variant="outline" asChild>
                    <Link href={customerGroups.index()}>Volver</Link>
                </Button>
            </div>

            <div className="max-w-2xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <CustomerGroupFields data={data} setData={setData} errors={errors} websites={websites} />
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>
                        Crear grupo
                    </Button>
                </div>
            </div>
        </>
    );
}
