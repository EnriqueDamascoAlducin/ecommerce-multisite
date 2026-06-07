import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import customers from '@/routes/admin/customers';
import CustomerFields, { type CustomerData } from './customer-fields';

type WebsiteOption = { id: number; name: string };
type GroupOption = { id: number; name: string; website_id: number };

export default function CustomerCreate({
    websites,
    groups,
}: {
    websites: WebsiteOption[];
    groups: GroupOption[];
}) {
    const { data, setData, post, processing, errors } = useForm<CustomerData>({
        website_id: '',
        group_id: '',
        name: '',
        email: '',
        phone: '',
        password: '',
        addresses: [],
    });

    const submit = () => post(customers.store().url);

    return (
        <>
            <Head title="Nuevo cliente" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo cliente</h1>
                <Button variant="outline" asChild>
                    <Link href={customers.index()}>Volver</Link>
                </Button>
            </div>

            <div className="max-w-3xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <CustomerFields data={data} setData={setData} errors={errors} websites={websites} groups={groups} isCreate />
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>
                        Crear cliente
                    </Button>
                </div>
            </div>
        </>
    );
}
