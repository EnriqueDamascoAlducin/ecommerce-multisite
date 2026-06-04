import { Head, Link, router, useForm } from '@inertiajs/react';
import { Edit, ExternalLink, Plus, Trash2 } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import storefrontPages from '@/routes/admin/storefront/pages';

type StoreOption = { id: number; label: string };
type PageRow = {
    id: number;
    title: string;
    slug: string;
    is_published: boolean;
    updated_at: string | null;
    url: string;
    is_home: boolean;
};

export default function StorefrontPagesIndex({
    stores,
    currentStoreId,
    pages,
}: {
    stores: StoreOption[];
    currentStoreId: number;
    pages: PageRow[];
}) {
    const form = useForm({
        store_id: currentStoreId,
        title: '',
        slug: '',
        is_published: false,
    });

    const changeStore = (event: ChangeEvent<HTMLSelectElement>) => {
        router.get(
            storefrontPages.index.url(),
            { store_id: event.target.value },
            { preserveState: false },
        );
    };

    const createPage = () => {
        form.post(storefrontPages.store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('title', 'slug'),
        });
    };

    const destroyPage = (page: PageRow) => {
        if (page.is_home) {
            return;
        }

        if (confirm(`Eliminar pagina "${page.title}"?`)) {
            router.delete(storefrontPages.destroy.url(page.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <>
            <Head title="Paginas storefront" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Paginas storefront
                    </h1>
                    <p className="text-sm text-neutral-500">
                        Crea paginas editables y administra contenido por
                        tienda.
                    </p>
                </div>
                <select
                    value={currentStoreId}
                    onChange={changeStore}
                    className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    {stores.map((store) => (
                        <option key={store.id} value={store.id}>
                            {store.label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="mb-6 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                <h2 className="mb-3 text-lg font-semibold">Nueva pagina</h2>
                <div className="grid gap-3 md:grid-cols-[1fr_1fr_auto_auto] md:items-end">
                    <div className="grid gap-1">
                        <Label>Titulo</Label>
                        <Input
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            placeholder="Nosotros"
                        />
                    </div>
                    <div className="grid gap-1">
                        <Label>Slug</Label>
                        <Input
                            value={form.data.slug}
                            onChange={(event) =>
                                form.setData(
                                    'slug',
                                    slugify(event.target.value),
                                )
                            }
                            placeholder="nosotros"
                        />
                    </div>
                    <label className="flex items-center gap-2 pb-2 text-sm">
                        <Checkbox
                            checked={form.data.is_published}
                            onCheckedChange={(value) =>
                                form.setData('is_published', value === true)
                            }
                        />
                        Publicar
                    </label>
                    <Button
                        onClick={createPage}
                        disabled={
                            form.processing ||
                            !form.data.title ||
                            !form.data.slug
                        }
                    >
                        <Plus className="mr-1 size-4" />
                        Crear
                    </Button>
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <div className="grid grid-cols-[1fr_8rem_8rem_9rem] gap-3 border-b border-neutral-200 px-4 py-3 text-xs font-semibold text-neutral-500 uppercase dark:border-neutral-800">
                    <span>Pagina</span>
                    <span>Estado</span>
                    <span>Actualizada</span>
                    <span className="text-right">Acciones</span>
                </div>
                {pages.map((page) => (
                    <div
                        key={page.id}
                        className="grid grid-cols-[1fr_8rem_8rem_9rem] items-center gap-3 border-b border-neutral-100 px-4 py-3 last:border-0 dark:border-neutral-800"
                    >
                        <div className="min-w-0">
                            <div className="flex items-center gap-2">
                                <p className="truncate font-medium">
                                    {page.title}
                                </p>
                                {page.is_home && (
                                    <Badge variant="secondary">Home</Badge>
                                )}
                            </div>
                            <p className="text-sm text-neutral-500">
                                {page.url}
                            </p>
                        </div>
                        <Badge
                            variant={page.is_published ? 'default' : 'outline'}
                        >
                            {page.is_published ? 'Publicada' : 'Borrador'}
                        </Badge>
                        <span className="text-sm text-neutral-500">
                            {page.updated_at ?? '-'}
                        </span>
                        <div className="flex justify-end gap-2">
                            <Button asChild variant="outline" size="sm">
                                <Link href={storefrontPages.edit.url(page.id)}>
                                    <Edit className="size-4" />
                                </Link>
                            </Button>
                            <Button asChild variant="outline" size="sm">
                                <a
                                    href={page.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink className="size-4" />
                                </a>
                            </Button>
                            <Button
                                variant="destructive"
                                size="sm"
                                disabled={page.is_home}
                                onClick={() => destroyPage(page)}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    </div>
                ))}
            </div>
        </>
    );
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
