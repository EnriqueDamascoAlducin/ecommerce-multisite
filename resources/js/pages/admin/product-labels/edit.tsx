import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import productLabels from '@/routes/admin/product-labels';
import ProductLabelFields, { type ProductLabelData } from './label-fields';

type Label = {
    id: number;
    website_id: number;
    text: string;
    text_color: string;
    background_color: string;
    is_active: boolean;
    sort_order: number;
};

export default function ProductLabelEdit({
    label,
    websites,
}: {
    label: Label;
    websites: { id: number; name: string }[];
}) {
    const { data, setData, put, processing, errors } = useForm<ProductLabelData>({
        website_id: String(label.website_id),
        text: label.text,
        text_color: label.text_color,
        background_color: label.background_color,
        is_active: label.is_active,
        sort_order: String(label.sort_order),
    });

    const submit = () => put(productLabels.update(label.id).url);

    return (
        <>
            <Head title={`Editar ${label.text}`} />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar etiqueta</h1>
                <Button variant="outline" asChild>
                    <Link href={productLabels.index()}>Volver</Link>
                </Button>
            </div>

            <div className="max-w-2xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <ProductLabelFields data={data} setData={setData} errors={errors} websites={websites} />
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>Guardar cambios</Button>
                </div>
            </div>
        </>
    );
}
