import { Form, Head, Link } from '@inertiajs/react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { Button } from '@/components/ui/button';
import categories from '@/routes/admin/categories';
import { CategoryFields } from './category-fields';

export default function CategoriesCreate({
    stores,
    currentStoreId,
    parentOptions,
}: {
    stores: { id: number; name: string }[];
    currentStoreId: number | null;
    parentOptions: { id: number; label: string }[];
}) {
    return (
        <>
            <Head title="Nueva categoría" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Nueva categoría</h1>
                <Button variant="outline" asChild>
                    <Link href={categories.index()}>Volver</Link>
                </Button>
            </div>

            <Form {...CategoryController.store.form()} className="max-w-3xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <CategoryFields
                            errors={errors}
                            stores={stores}
                            parentOptions={parentOptions}
                            defaults={{
                                store_id: currentStoreId ?? stores[0]?.id,
                                parent_id: null,
                                name: '',
                                slug: null,
                                description: null,
                                is_active: true,
                                sort_order: 0,
                                meta_title: null,
                                meta_description: null,
                                meta_keywords: null,
                            }}
                        />
                        <Button disabled={processing}>Crear categoría</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
