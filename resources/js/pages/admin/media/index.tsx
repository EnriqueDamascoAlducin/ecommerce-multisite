import { Form, Head, Link, router } from '@inertiajs/react';
import { FileIcon } from 'lucide-react';
import { useState } from 'react';
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
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { usePermissions } from '@/hooks/use-permissions';
import media from '@/routes/admin/media';

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
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

function formatSize(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / 1024 / 1024).toFixed(1)} MB`;
}

export default function MediaIndex({ media: page }: { media: Paginated<MediaItem> }) {
    const { can } = usePermissions();
    const [editing, setEditing] = useState<MediaItem | null>(null);

    const destroy = (item: MediaItem) => {
        if (confirm(`¿Eliminar ${item.name}?`)) {
            router.delete(media.destroy(item.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Biblioteca de medios" />

            <h1 className="mb-6 text-2xl font-semibold">Biblioteca de medios</h1>

            {can('media.upload') && (
                <Form
                    {...MediaController.store.form()}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="mb-8 rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
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
                                <InputError message={errors['files.0'] ?? errors.files} />
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
                                    <option value="private">Privada (descargable)</option>
                                </select>
                            </div>
                            <Button disabled={processing}>Subir</Button>
                        </div>
                    )}
                </Form>
            )}

            <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                {page.data.map((item) => (
                    <div
                        key={item.id}
                        className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <div className="flex aspect-video items-center justify-center bg-neutral-100 dark:bg-neutral-800">
                            {item.is_image ? (
                                <img src={item.url} alt={item.alt ?? item.name} className="h-full w-full object-cover" />
                            ) : (
                                <FileIcon className="size-10 text-neutral-400" />
                            )}
                        </div>
                        <div className="space-y-2 p-3">
                            <p className="truncate text-sm font-medium" title={item.name}>
                                {item.name}
                            </p>
                            <div className="flex items-center justify-between text-xs text-neutral-500">
                                <span>{formatSize(item.size)}</span>
                                <Badge variant={item.visibility === 'private' ? 'outline' : 'secondary'}>
                                    {item.visibility === 'private' ? 'Privada' : 'Pública'}
                                </Badge>
                            </div>
                            <div className="flex gap-2">
                                {can('media.upload') && (
                                    <Button variant="outline" size="sm" onClick={() => setEditing(item)}>
                                        Editar
                                    </Button>
                                )}
                                {can('media.delete') && (
                                    <Button variant="destructive" size="sm" onClick={() => destroy(item)}>
                                        Eliminar
                                    </Button>
                                )}
                            </div>
                        </div>
                    </div>
                ))}
                {page.data.length === 0 && (
                    <p className="col-span-full py-8 text-center text-neutral-500">
                        No hay archivos todavía.
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

            <Dialog open={editing !== null} onOpenChange={(open) => !open && setEditing(null)}>
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
                                        <Input id="title" name="title" defaultValue={editing.title ?? ''} />
                                        <InputError message={errors.title} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label htmlFor="alt">Texto alternativo</Label>
                                        <Input id="alt" name="alt" defaultValue={editing.alt ?? ''} />
                                        <InputError message={errors.alt} />
                                    </div>
                                    <DialogFooter className="gap-2">
                                        <DialogClose asChild>
                                            <Button type="button" variant="secondary">
                                                Cancelar
                                            </Button>
                                        </DialogClose>
                                        <Button disabled={processing}>Guardar</Button>
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
