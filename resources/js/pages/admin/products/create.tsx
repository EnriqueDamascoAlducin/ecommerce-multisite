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
    is_configurable?: boolean;
    options: { label: string; value: string }[];
};

type ConfigurableAttrDef = {
    id: number;
    code: string;
    name: string;
    type: string;
    options: { label: string; value: string }[];
};

export default function ProductsCreate({
    stores,
    availableImages,
    categories,
    attributes,
    configurableAttributes,
    componentProducts,
    labels,
}: {
    stores: { id: number; label: string }[];
    availableImages: { id: number; url: string; name: string }[];
    categories: { id: number; label: string }[];
    attributes: AttributeDef[];
    configurableAttributes?: ConfigurableAttrDef[];
    componentProducts?: { id: number; sku: string; name: string }[];
    labels?: { id: number; text: string; text_color: string; background_color: string; website: string | null }[];
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
                            configurableAttributes={configurableAttributes}
                            componentProducts={componentProducts}
                            labels={labels}
                        />
                        <Button disabled={processing}>Crear producto</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
