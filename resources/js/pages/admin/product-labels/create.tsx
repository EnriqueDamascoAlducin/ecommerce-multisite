import { Head, Link, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import productLabels from '@/routes/admin/product-labels';
import ProductLabelFields, { type ProductLabelData } from './label-fields';

export default function ProductLabelCreate({ websites }: { websites: { id: number; name: string }[] }) {
    const { data, setData, post, processing, errors } = useForm<ProductLabelData>({
        website_id: '',
        text: '',
        text_color: '#ffffff',
        background_color: '#111827',
        is_active: true,
        sort_order: '0',
    });

    const submit = () => post(productLabels.store().url);

    return (
        <>
            <Head title="Nueva etiqueta" />
            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nueva etiqueta</h1>
                <Button variant="outline" asChild>
                    <Link href={productLabels.index()}>Volver</Link>
                </Button>
            </div>

            <div className="max-w-2xl rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
                <ProductLabelFields data={data} setData={setData} errors={errors} websites={websites} />
                <div className="mt-6 flex justify-end">
                    <Button onClick={submit} disabled={processing}>Crear etiqueta</Button>
                </div>
            </div>
        </>
    );
}
