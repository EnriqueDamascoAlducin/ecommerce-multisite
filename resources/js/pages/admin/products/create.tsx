import { Form, Head, Link } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { Button } from '@/components/ui/button';
import products from '@/routes/admin/products';
import { ProductFields } from './product-fields';

type AttributeDef = {
    id: number;
    code: string;
    name: string;
    type: string;
    is_required: boolean;
    options: { label: string; value: string }[];
};

export default function ProductsCreate({
    stores,
    availableImages,
    categories,
    attributes,
}: {
    stores: { id: number; label: string }[];
    availableImages: { id: number; url: string; name: string }[];
    categories: { id: number; label: string }[];
    attributes: AttributeDef[];
}) {
    return (
        <>
            <Head title="Nuevo producto" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nuevo producto</h1>
                <Button variant="outline" asChild>
                    <Link href={products.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...ProductController.store.form()} className="max-w-4xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <ProductFields
                            errors={errors}
                            stores={stores}
                            availableImages={availableImages}
                            categories={categories}
                            attributes={attributes}
                        />
                        <Button disabled={processing}>Crear producto</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
