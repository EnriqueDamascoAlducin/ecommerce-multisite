import { Form, Head, Link, router } from '@inertiajs/react';
import { Copy, Eye, FileIcon, Search } from 'lucide-react';
import { FormEvent, useState } from 'react';
import MediaController from '@/actions/App/Http/Controllers/Admin/MediaController';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import media from '@/routes/admin/media';

type MediaUsage = {
    context: string;
    label: string;
    title: string;
    description: string | null;
};

type MediaItem = {
    id: number;
    name: string;
    url: string;
    is_image: boolean;
    mime_type: string | null;
    size: number;
    visibility: string;
    title: string | null;
    alt: string | null;
    created_at: string | null;
    usages: MediaUsage[];
};

type FilterOption = {
    value: string;
    label: string;
};

type Filters = {
    name: string;
    type: string;
    usage: string;
    context: string;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

type Props = {
    media: Paginated<MediaItem>;
    filters: Filters;
    filterOptions: {
        types: FilterOption[];
        usages: FilterOption[];
        contexts: FilterOption[];
    };
};

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

function usageSummary(usages: MediaUsage[]): string {
    if (usages.length === 0) return 'Sin uso detectado';

    return usages
        .slice(0, 2)
        .map((usage) => usage.label)
        .join(', ');
}

export default function MediaIndex({
    media: page,
    filters,
    filterOptions,
}: Props) {
    const { can } = usePermissions();
    const [editing, setEditing] = useState<MediaItem | null>(null);
    const [viewingUsages, setViewingUsages] = useState<MediaItem | null>(null);
    const [copiedId, setCopiedId] = useState<number | null>(null);
    const [filterValues, setFilterValues] = useState<Filters>(filters);

    const destroy = (item: MediaItem) => {
        if (confirm(`¿Eliminar ${item.name}?`)) {
            router.delete(media.destroy(item.id).url, { preserveScroll: true });
        }
    };

    const submitFilters = (event: FormEvent) => {
        event.preventDefault();

        router.get(media.index().url, filterValues, {
            preserveScroll: true,
            preserveState: true,
        });
    };

    const copyUrl = async (item: MediaItem) => {
        await navigator.clipboard.writeText(item.url);
        setCopiedId(item.id);
        window.setTimeout(
            () =>
                setCopiedId((current) =>
                    current === item.id ? null : current,
                ),
            1800,
        );
    };

    return (
        <>
            <Head title="Biblioteca de medios" />

            <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">
                        Biblioteca de medios
                    </h1>
                    <p className="text-sm text-neutral-500">
                        Busca archivos, copia URLs y revisa dónde se están
                        usando.
                    </p>
                </div>
            </div>

            {can('media.upload') && (
                <Form
                    {...MediaController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="mb-6 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                >
                    {({ processing, errors }) => (
                        <div className="flex flex-wrap items-end gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="files">Subir archivos</Label>
                                <input
                                    id="files"
                                    type="file"
                                    name="files[]"
                                    multiple
                                    className="text-sm"
                                />
                                <InputError
                                    message={errors['files.0'] ?? errors.files}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="visibility">Visibilidad</Label>
                                <select
                                    id="visibility"
                                    name="visibility"
                                    defaultValue="public"
                                    className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                                >
                                    <option value="public">Pública</option>
                                    <option value="private">
                                        Privada (descargable)
                                    </option>
                                </select>
                            </div>
                            <Button disabled={processing}>Subir</Button>
                        </div>
                    )}
                </Form>
            )}

            <form
                onSubmit={submitFilters}
                className="mb-6 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 lg:grid-cols-[minmax(220px,1fr)_repeat(3,180px)_auto] dark:border-neutral-800 dark:bg-neutral-900"
            >
                <div className="grid gap-2">
                    <Label htmlFor="name">Nombre de imagen</Label>
                    <div className="relative">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-neutral-400" />
                        <Input
                            id="name"
                            value={filterValues.name}
                            onChange={(event) =>
                                setFilterValues({
                                    ...filterValues,
                                    name: event.target.value,
                                })
                            }
                            className="pl-9"
                            placeholder="Buscar por archivo, título o alt"
                        />
                    </div>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="type">Tipo</Label>
                    <select
                        id="type"
                        value={filterValues.type}
                        onChange={(event) =>
                            setFilterValues({
                                ...filterValues,
                                type: event.target.value,
                            })
                        }
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        {filterOptions.types.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="usage">Uso</Label>
                    <select
                        id="usage"
                        value={filterValues.usage}
                        onChange={(event) =>
                            setFilterValues({
                                ...filterValues,
                                usage: event.target.value,
                            })
                        }
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        {filterOptions.usages.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="context">Dónde se usa</Label>
                    <select
                        id="context"
                        value={filterValues.context}
                        onChange={(event) =>
                            setFilterValues({
                                ...filterValues,
                                context: event.target.value,
                            })
                        }
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        {filterOptions.contexts.map((option) => (
                            <option key={option.value} value={option.value}>
                                {option.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="flex items-end gap-2">
                    <Button type="submit">Filtrar</Button>
                    <Button variant="outline" type="button" asChild>
                        <Link href={media.index().url} preserveScroll>
                            Limpiar
                        </Link>
                    </Button>
                </div>
            </form>

            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                {page.data.map((item) => (
                    <div
                        key={item.id}
                        className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div className="flex aspect-video items-center justify-center bg-neutral-100 dark:bg-neutral-800">
                            {item.is_image ? (
                                <img
                                    src={item.url}
                                    alt={item.alt ?? item.name}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <FileIcon className="size-10 text-neutral-400" />
                            )}
                        </div>
                        <div className="space-y-3 p-3">
                            <div>
                                <p
                                    className="truncate text-sm font-medium"
                                    title={item.name}
                                >
                                    {item.name}
                                </p>
                                <p
                                    className="truncate text-xs text-neutral-500"
                                    title={item.mime_type ?? undefined}
                                >
                                    {item.mime_type ?? 'Sin tipo'} ·{' '}
                                    {formatSize(item.size)}
                                </p>
                            </div>

                            <div className="flex flex-wrap gap-1.5">
                                <Badge
                                    variant={
                                        item.visibility === 'private'
                                            ? 'outline'
                                            : 'secondary'
                                    }
                                >
                                    {item.visibility === 'private'
                                        ? 'Privada'
                                        : 'Pública'}
                                </Badge>
                                {item.usages.length === 0 ? (
                                    <Badge variant="outline">Sin uso</Badge>
                                ) : (
                                    item.usages
                                        .slice(0, 3)
                                        .map((usage, index) => (
                                            <Badge
                                                key={`${usage.context}-${usage.title}-${index}`}
                                                variant="secondary"
                                            >
                                                {usage.label}
                                            </Badge>
                                        ))
                                )}
                            </div>

                            <p className="line-clamp-2 min-h-8 text-xs text-neutral-500">
                                {usageSummary(item.usages)}
                                {item.usages.length > 2
                                    ? ` y ${item.usages.length - 2} más`
                                    : ''}
                            </p>

                            <div className="grid grid-cols-2 gap-2">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => copyUrl(item)}
                                >
                                    <Copy className="size-4" />
                                    {copiedId === item.id
                                        ? 'Copiada'
                                        : 'Copiar URL'}
                                </Button>
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() => setViewingUsages(item)}
                                >
                                    <Eye className="size-4" />
                                    Ver usos
                                </Button>
                                {can('media.upload') && (
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEditing(item)}
                                    >
                                        Editar
                                    </Button>
                                )}
                                {can('media.delete') && (
                                    <Button
                                        variant="destructive"
                                        size="sm"
                                        onClick={() => destroy(item)}
                                    >
                                        Eliminar
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
                {page.data.length === 0 && (
                    <p className="col-span-full rounded-lg border border-dashed border-neutral-300 py-10 text-center text-neutral-500 dark:border-neutral-700">
                        No hay archivos con estos filtros.
                    </p>
                )}
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-neutral-500">
                <span>{page.total} archivos</span>
                <div className="flex gap-2">
                    {page.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.prev_page_url} preserveScroll>
                                Anterior
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Anterior
                        </Button>
                    )}
                    {page.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.next_page_url} preserveScroll>
                                Siguiente
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Siguiente
                        </Button>
                    )}
                </div>
            </div>

            <Dialog
                open={viewingUsages !== null}
                onOpenChange={(open) => !open && setViewingUsages(null)}
            >
                <DialogContent>
                    <DialogTitle>Usos de {viewingUsages?.name}</DialogTitle>
                    {viewingUsages && (
                        <div className="space-y-3">
                            {viewingUsages.usages.length === 0 ? (
                                <p className="rounded-md border border-dashed border-neutral-300 p-4 text-sm text-neutral-500 dark:border-neutral-700">
                                    No se detectó uso en productos, páginas,
                                    SEO, cintillo, tiendas, websites o
                                    categorías.
                                </p>
                            ) : (
                                viewingUsages.usages.map((usage, index) => (
                                    <div
                                        key={`${usage.context}-${usage.title}-${index}`}
                                        className="rounded-md border border-neutral-200 p-3 text-sm dark:border-neutral-800"
                                    >
                                        <div className="mb-1 flex items-center gap-2">
                                            <Badge variant="secondary">
                                                {usage.label}
                                            </Badge>
                                            <span className="font-medium">
                                                {usage.title}
                                            </span>
                                        </div>
                                        {usage.description && (
                                            <p className="text-neutral-500">
                                                {usage.description}
                                            </p>
                                        )}
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={editing !== null}
                onOpenChange={(open) => !open && setEditing(null)}
            >
                <DialogContent>
                    <DialogTitle>Editar medio</DialogTitle>
                    {editing && (
                        <Form
                            {...MediaController.update.form(editing.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(null)}
                            className="space-y-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <div className="grid gap-2">
                                        <Label htmlFor="title">Título</Label>
                                        <Input
                                            id="title"
                                            name="title"
                                            defaultValue={editing.title ?? ''}
                                        />
                                        <InputError message={errors.title} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="alt">
                                            Texto alternativo
                                        </Label>
                                        <Input
                                            id="alt"
                                            name="alt"
                                            defaultValue={editing.alt ?? ''}
                                        />
                                        <InputError message={errors.alt} />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button
                                                type="button"
                                                variant="secondary"
                                            >
                                                Cancelar
                                            </Button>
                                        </DialogClose>
                                        <Button disabled={processing}>
                                            Guardar
                                        </Button>
                                    </DialogFooter>
                                </>
                            )}
                        </Form>
                    )}
                </DialogContent>
            </Dialog>
        </>
    );
}
