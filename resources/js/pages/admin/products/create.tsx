import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, PackagePlus } from 'lucide-react';
import { ProductFields } from './product-fields';
import ProductController from '@/actions/App/Http/Controllers/Admin/ProductController';
import { Button } from '@/components/ui/button';
import products from '@/routes/admin/products';

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
            <Head title="Nuevo producto" />

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
                        <span>Nuevo producto</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Crear producto
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Configura informacion comercial, tiendas, atributos y
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
                    <Button form="product-form" disabled={false}>
                        <PackagePlus className="size-4" />
                        Crear producto
                    </Button>
                </div>
            </div>

            <Form
                {...ProductController.store.form()}
                id="product-form"
                className="max-w-6xl"
            >
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        {processing && (
                            <div className="rounded-lg border border-neutral-200 bg-white px-4 py-3 text-sm text-neutral-500 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                                Guardando producto...
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
                            labels={labels}
                        />
                    </div>
                )}
            </Form>
        </>
    );
}
