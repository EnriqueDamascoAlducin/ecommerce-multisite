import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Edit,
    ExternalLink,
    Link2Off,
    Plus,
    Store,
    Trash2,
} from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useState } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import storefrontPages from '@/routes/admin/storefront/pages';

type StoreOption = { id: number; label: string };
type TemplateOption = { key: string; label: string; description: string };
type MediaOption = { id: number; label: string; url: string };
type PageRow = {
    id: number;
    title: string;
    slug: string;
    is_published: boolean;
    updated_at: string | null;
    url: string;
    is_home: boolean;
    stores: StoreOption[];
};
type PendingAction = {
    kind: 'unlink' | 'delete';
    page: PageRow;
} | null;

export default function StorefrontPagesIndex({
    stores,
    currentStoreId,
    availableTemplates,
    pages,
    media,
}: {
    stores: StoreOption[];
    currentStoreId: number;
    availableTemplates: TemplateOption[];
    pages: PageRow[];
    media: MediaOption[];
}) {
    const form = useForm({
        store_id: currentStoreId,
        store_ids: [currentStoreId],
        title: '',
        slug: '',
        template: availableTemplates[0]?.key ?? 'flexible',
        is_published: false,
        seo: {
            meta_title: '',
            meta_description: '',
            meta_keywords: '',
            robots_index: true,
            robots_follow: true,
            canonical_url: '',
            og_title: '',
            og_description: '',
            og_media_id: null as number | null,
        },
    });
    const [pendingAction, setPendingAction] = useState<PendingAction>(null);
    const [actionProcessing, setActionProcessing] = useState(false);

    const selectedTemplate = availableTemplates.find(
        (template) => template.key === form.data.template,
    );

    const changeStore = (event: ChangeEvent<HTMLSelectElement>) => {
        router.get(
            storefrontPages.index.url(),
            { store_id: event.target.value },
            { preserveState: false },
        );
    };

    const toggleStore = (storeId: number, checked: boolean) => {
        if (!checked && storeId === currentStoreId) {
            return;
        }

        form.setData(
            'store_ids',
            checked
                ? [...new Set([...form.data.store_ids, storeId])]
                : form.data.store_ids.filter((id) => id !== storeId),
        );
    };

    const createPage = () => {
        form.post(storefrontPages.store.url(), {
            preserveScroll: true,
            onSuccess: () => form.reset('title', 'slug', 'seo'),
        });
    };

    const confirmAction = () => {
        if (!pendingAction) {
            return;
        }

        setActionProcessing(true);

        const url =
            pendingAction.kind === 'unlink'
                ? storefrontPages.unlink.url({
                      page: pendingAction.page.id,
                      store: currentStoreId,
                  })
                : storefrontPages.destroy.url(pendingAction.page.id);

        router.delete(url, {
            preserveScroll: true,
            onSuccess: () => setPendingAction(null),
            onFinish: () => setActionProcessing(false),
        });
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
                        Crea una pagina una vez y asignala a las tiendas que la
                        necesiten.
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
                <div className="grid gap-3 md:grid-cols-3">
                    <div className="grid gap-1">
                        <Label>Titulo</Label>
                        <Input
                            value={form.data.title}
                            onChange={(event) =>
                                form.setData('title', event.target.value)
                            }
                            placeholder="Bolsa de trabajo"
                        />
                        {form.errors.title && (
                            <p className="text-xs text-red-600">
                                {form.errors.title}
                            </p>
                        )}
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
                            placeholder="bolsa-de-trabajo"
                        />
                        {form.errors.slug && (
                            <p className="text-xs text-red-600">
                                {form.errors.slug}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-1">
                        <Label>Plantilla</Label>
                        <select
                            value={form.data.template}
                            onChange={(event) =>
                                form.setData('template', event.target.value)
                            }
                            className="h-9 rounded-md border border-neutral-300 bg-white px-3 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            {availableTemplates.map((template) => (
                                <option key={template.key} value={template.key}>
                                    {template.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="mt-4">
                    <Label>Disponible en tiendas</Label>
                    <div className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                        {stores.map((store) => (
                            <label
                                key={store.id}
                                className="flex min-w-0 items-center gap-2 rounded-md border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-800"
                            >
                                <Checkbox
                                    checked={form.data.store_ids.includes(
                                        store.id,
                                    )}
                                    onCheckedChange={(value) =>
                                        toggleStore(store.id, value === true)
                                    }
                                />
                                <span className="truncate">{store.label}</span>
                            </label>
                        ))}
                    </div>
                    {form.errors.store_ids && (
                        <p className="mt-1 text-xs text-red-600">
                            {form.errors.store_ids}
                        </p>
                    )}
                </div>

                <details className="mt-4 rounded-md border border-neutral-200 p-4 dark:border-neutral-800">
                    <summary className="cursor-pointer text-sm font-semibold">
                        Metadatos SEO iniciales
                    </summary>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                        <div className="grid gap-1">
                            <Label>Título SEO</Label>
                            <Input
                                value={form.data.seo.meta_title}
                                onChange={(event) =>
                                    form.setData('seo', {
                                        ...form.data.seo,
                                        meta_title: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Palabras clave</Label>
                            <Input
                                value={form.data.seo.meta_keywords}
                                onChange={(event) =>
                                    form.setData('seo', {
                                        ...form.data.seo,
                                        meta_keywords: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-1 md:col-span-2">
                            <Label>Meta descripción</Label>
                            <textarea
                                value={form.data.seo.meta_description}
                                onChange={(event) =>
                                    form.setData('seo', {
                                        ...form.data.seo,
                                        meta_description: event.target.value,
                                    })
                                }
                                className="min-h-24 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            />
                        </div>
                        <div className="grid gap-1 md:col-span-2">
                            <Label>Canonical personalizado</Label>
                            <Input
                                value={form.data.seo.canonical_url}
                                onChange={(event) =>
                                    form.setData('seo', {
                                        ...form.data.seo,
                                        canonical_url: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Título Open Graph</Label>
                            <Input
                                value={form.data.seo.og_title}
                                onChange={(event) =>
                                    form.setData('seo', {
                                        ...form.data.seo,
                                        og_title: event.target.value,
                                    })
                                }
                            />
                        </div>
                        <div className="grid gap-1">
                            <Label>Imagen Open Graph</Label>
                            <select
                                value={form.data.seo.og_media_id ?? ''}
                                onChange={(event) =>
                                    form.setData('seo', {
                                        ...form.data.seo,
                                        og_media_id: event.target.value
                                            ? Number(event.target.value)
                                            : null,
                                    })
                                }
                                className="h-9 rounded-md border border-neutral-300 bg-white px-3 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            >
                                <option value="">Sin imagen</option>
                                {media.map((item) => (
                                    <option key={item.id} value={item.id}>
                                        {item.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div className="grid gap-1 md:col-span-2">
                            <Label>Descripción Open Graph</Label>
                            <textarea
                                value={form.data.seo.og_description}
                                onChange={(event) =>
                                    form.setData('seo', {
                                        ...form.data.seo,
                                        og_description: event.target.value,
                                    })
                                }
                                className="min-h-20 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                            />
                        </div>
                        <div className="flex flex-wrap gap-4 md:col-span-2">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.seo.robots_index}
                                    onCheckedChange={(checked) =>
                                        form.setData('seo', {
                                            ...form.data.seo,
                                            robots_index: checked === true,
                                        })
                                    }
                                />
                                Indexar
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.seo.robots_follow}
                                    onCheckedChange={(checked) =>
                                        form.setData('seo', {
                                            ...form.data.seo,
                                            robots_follow: checked === true,
                                        })
                                    }
                                />
                                Seguir enlaces
                            </label>
                        </div>
                    </div>
                </details>
                <div className="mt-3 flex flex-wrap items-center justify-between gap-3">


                    <p className="text-sm text-neutral-500">
                        {selectedTemplate?.description}
                    </p>
                    <div className="flex items-center gap-4">
                        <label className="flex items-center gap-2 text-sm">
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
                                !form.data.slug ||
                                form.data.store_ids.length === 0
                            }
                        >
                            <Plus className="mr-1 size-4" />
                            Crear
                        </Button>
                    </div>
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <div className="hidden grid-cols-[minmax(0,1fr)_minmax(12rem,1fr)_8rem_8rem_11rem] gap-3 border-b border-neutral-200 px-4 py-3 text-xs font-semibold text-neutral-500 uppercase md:grid dark:border-neutral-800">
                    <span>Pagina</span>
                    <span>Tiendas</span>
                    <span>Estado</span>
                    <span>Actualizada</span>
                    <span className="text-right">Acciones</span>
                </div>
                {pages.map((page) => (
                    <div
                        key={page.id}
                        className="grid gap-3 border-b border-neutral-100 px-4 py-4 last:border-0 md:grid-cols-[minmax(0,1fr)_minmax(12rem,1fr)_8rem_8rem_11rem] md:items-center dark:border-neutral-800"
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
                            <p className="truncate text-sm text-neutral-500">
                                {page.url}
                            </p>
                        </div>
                        <div className="flex min-w-0 flex-wrap gap-1">
                            {page.stores.map((store) => (
                                <Badge
                                    key={store.id}
                                    variant="outline"
                                    className="max-w-full"
                                >
                                    <Store className="size-3" />
                                    <span className="truncate">
                                        {store.label}
                                    </span>
                                </Badge>
                            ))}
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
                            <Button
                                asChild
                                variant="outline"
                                size="icon"
                                title="Editar pagina"
                            >
                                <Link
                                    href={storefrontPages.edit.url(page.id, {
                                        query: {
                                            store_id: currentStoreId,
                                        },
                                    })}
                                >
                                    <Edit className="size-4" />
                                </Link>
                            </Button>
                            <Button
                                asChild
                                variant="outline"
                                size="icon"
                                title="Ver pagina"
                            >
                                <a
                                    href={page.url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <ExternalLink className="size-4" />
                                </a>
                            </Button>
                            {!page.is_home && page.stores.length > 1 && (
                                <Button
                                    variant="outline"
                                    size="icon"
                                    title="Desvincular de esta tienda"
                                    onClick={() =>
                                        setPendingAction({
                                            kind: 'unlink',
                                            page,
                                        })
                                    }
                                >
                                    <Link2Off className="size-4" />
                                </Button>
                            )}
                            <Button
                                variant="destructive"
                                size="icon"
                                title="Eliminar de todas las tiendas"
                                disabled={page.is_home}
                                onClick={() =>
                                    setPendingAction({
                                        kind: 'delete',
                                        page,
                                    })
                                }
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>
                    </div>
                ))}
            </div>

            <ConfirmDialog
                open={pendingAction !== null}
                onOpenChange={(open) => {
                    if (!open && !actionProcessing) {
                        setPendingAction(null);
                    }
                }}
                onConfirm={confirmAction}
                title={
                    pendingAction?.kind === 'unlink'
                        ? 'Desvincular pagina de esta tienda'
                        : 'Eliminar pagina de todas las tiendas'
                }
                description={
                    pendingAction?.kind === 'unlink'
                        ? `La pagina "${pendingAction.page.title}" dejara de estar disponible en esta tienda y se quitaran sus enlaces del menu.`
                        : `La pagina "${pendingAction?.page.title ?? ''}" y todo su contenido se eliminaran de todas las tiendas asignadas.`
                }
                confirmLabel={
                    pendingAction?.kind === 'unlink'
                        ? 'Desvincular'
                        : 'Eliminar globalmente'
                }
                loading={actionProcessing}
            />
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
