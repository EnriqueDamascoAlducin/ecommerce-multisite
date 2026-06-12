import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Save } from 'lucide-react';
import { ProductFields } from './product-fields';
import type { ProductDefaults } from './product-fields';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { Button } from '@/components/ui/button';
import products from '@/routes/admin/products';

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
    relatedProducts,
    labels,
}: {
    product: EditableProduct;
    stores: { id: number; label: string }[];
    availableImages: { id: number; url: string; name: string }[];
    categories: { id: number; label: string }[];
    attributes: AttributeDef[];
    configurableAttributes?: ConfigurableAttrDef[];
    componentProducts?: { id: number; sku: string; name: string }[];
    relatedProducts?: {
        id: number;
        sku: string;
        name: string;
        status: string;
        type: string;
    }[];
    labels?: {
        id: number;
        text: string;
        text_color: string;
        background_color: string;
        website: string | null;
    }[];
}) {
    return (
        <>
            <Head title={`Editar ${product.name}`} />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="mb-2 flex items-center gap-2 text-xs text-neutral-500">
                        <Link
                            href={products.index()}
                            className="hover:text-neutral-900 dark:hover:text-neutral-100"
                        >
                            Catalogo
                        </Link>
                        <span>/</span>
                        <span>{product.sku}</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Editar producto
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Ajusta informacion multisitio, precios, atributos y
                        media.
                    </p>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" asChild>
                        <Link href={products.index()}>
                            <ArrowLeft className="size-4" />
                            Cancelar
                        </Link>
                    </Button>
                    <Button form="product-form">
                        <Save className="size-4" />
                        Guardar cambios
                    </Button>
                </div>
            </div>

            <Form
                {...ProductController.update.form(product.id)}
                id="product-form"
                className="max-w-6xl"
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        {processing && (
                            <div className="rounded-lg border border-neutral-200 bg-white px-4 py-3 text-sm text-neutral-500 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                                Guardando cambios...
                            </div>
                        )}
                        <ProductFields
                            errors={errors}
                            stores={stores}
                            availableImages={availableImages}
                            categories={categories}
                            attributes={attributes}
                            configurableAttributes={configurableAttributes}
                            componentProducts={componentProducts}
                            relatedProducts={relatedProducts}
                            labels={labels}
                            defaults={product}
                        />
                    </div>
                )}
            </Form>
        </>
    );
}
