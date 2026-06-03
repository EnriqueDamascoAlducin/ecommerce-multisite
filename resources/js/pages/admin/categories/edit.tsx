import { Form, Head, Link } from '@inertiajs/react';
import CategoryController from '@/actions/App/Http/Controllers/Admin/CategoryController';
import { Button } from '@/components/ui/button';
import categories from '@/routes/admin/categories';
import { CategoryFields, type CategoryDefaults } from './category-fields';

type EditableCategory = CategoryDefaults & { id: number };

export default function CategoriesEdit({
    category,
    websites,
    parentOptions,
}: {
    category: EditableCategory;
    websites: { id: number; name: string }[];
    parentOptions: { id: number; label: string }[];
}) {
    return (
        <>
            <Head title={`Editar ${category.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Editar categoría</h1>
                <Button variant="outline" asChild>
                    <Link href={categories.index({ query: { website_id: category.website_id } })}>Volver</Link>
                </Button>
            </div>

            <Form {...CategoryController.update.form(category.id)} className="max-w-3xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        <CategoryFields
                            errors={errors}
                            websites={websites}
                            parentOptions={parentOptions}
                            defaults={category}
                            lockWebsite
                        />
                        <Button disabled={processing}>Guardar cambios</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
