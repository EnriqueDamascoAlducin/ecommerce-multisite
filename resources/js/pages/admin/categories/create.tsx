import { Form, Head, Link } from '@inertiajs/react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { Button } from '@/components/ui/button';
import categories from '@/routes/admin/categories';
import { CategoryFields } from './category-fields';

export default function CategoriesCreate({
    websites,
    currentWebsiteId,
    parentOptions,
}: {
    websites: { id: number; name: string }[];
    currentWebsiteId: number | null;
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
                            websites={websites}
                            parentOptions={parentOptions}
                            defaults={{
                                website_id: currentWebsiteId ?? websites[0]?.id,
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
