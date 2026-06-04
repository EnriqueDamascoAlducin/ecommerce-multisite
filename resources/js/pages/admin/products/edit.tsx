import { Form, Head, Link } from '@inertiajs/react';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { Button } from '@/components/ui/button';
import products from '@/routes/admin/products';
import { ProductFields, type ProductDefaults } from './product-fields';

type EditableProduct = ProductDefaults & { id: number };

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

export default function ProductsEdit({
    product,
    stores,
    availableImages,
    categories,
    attributes,
    configurableAttributes,
    componentProducts,
}: {
    product: EditableProduct;
    stores: { id: number; label: string }[];
    availableImages: { id: number; url: string; name: string }[];
    categories: { id: number; label: string }[];
    attributes: AttributeDef[];
    configurableAttributes?: ConfigurableAttrDef[];
    componentProducts?: { id: number; sku: string; name: string }[];
}) {
    return (
        <>
            <Head title={`Editar ${product.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar producto</h1>
                <Button variant="outline" asChild>
                    <Link href={products.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...ProductController.update.form(product.id)} className="max-w-4xl">
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
                            defaults={product}
                        />
                        <Button disabled={processing}>Guardar cambios</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
