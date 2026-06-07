import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import customerGroups from '@/routes/admin/customer-groups';
import CustomerGroupFields, { type GroupData } from './group-fields';

type Group = {
    id: number;
    website_id: number;
    name: string;
    code: string;
    description: string | null;
    color: string;
    is_default: boolean;
};

export default function CustomerGroupEdit({
    group,
    websites,
}: {
    group: Group;
    websites: { id: number; name: string }[];
}) {
    const { data, setData, put, processing, errors } = useForm<GroupData>({
        website_id: group.website_id,
        name: group.name,
        code: group.code,
        description: group.description ?? '',
        color: group.color,
        is_default: group.is_default,
    });

    const submit = () => put(customerGroups.update(group.id).url);

    return (
        <>
            <Head title={`Editar ${group.name}`} />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar grupo</h1>
                <Button variant="outline" asChild>
                    <Link href={customerGroups.index()}>Volver</Link>
                </Button>
            </div>

            <div className="max-w-2xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <CustomerGroupFields data={data} setData={setData} errors={errors} websites={websites} lockWebsite />
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>
                        Guardar cambios
                    </Button>
                </div>
            </div>
        </>
    );
}
