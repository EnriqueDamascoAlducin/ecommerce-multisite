import type { ChangeEvent } from 'react';
import { useRef, useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

export type MediaImage = { id: number; url: string; name: string };

export function WebsiteFields({
    errors,
    defaults,
    availableImages,
}: {
    errors: Record<string, string>;
    defaults?: {
        code: string;
        name: string;
        is_default: boolean;
        sort_order: number;
        logo?: { id: number; url: string } | null;
        favicon?: { id: number; url: string } | null;
    };
    availableImages: MediaImage[];
}) {
    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="code">Código</Label>
                <Input
                    id="code"
                    name="code"
                    defaultValue={defaults?.code}
                    required
                    placeholder="interferenciales"
                />
                <InputError message={errors.code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input
                    id="name"
                    name="name"
                    defaultValue={defaults?.name}
                    required
                />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="sort_order">Orden</Label>
                <Input
                    id="sort_order"
                    name="sort_order"
                    type="number"
                    min={0}
                    defaultValue={defaults?.sort_order ?? 0}
                />
                <InputError message={errors.sort_order} />
            </div>

            <label className="flex items-center gap-2 text-sm">
                <input
                    type="checkbox"
                    name="is_default"
                    value="1"
                    defaultChecked={defaults?.is_default}
                    className="size-4 rounded border-neutral-300 dark:border-neutral-700"
                />
                Website por defecto
            </label>

            <MediaPicker
                title="Logo"
                emptyText="Sin logo"
                current={defaults?.logo ?? null}
                fileName="logo_file"
                mediaName="logo_media_id"
                removeName="remove_logo"
                removeLabel="Quitar logo"
                uploadLabel="Subir logo"
                availableImages={availableImages}
                errors={errors}
            />

            <MediaPicker
                title="Favicon / icono PWA"
                emptyText="Sin favicon"
                current={defaults?.favicon ?? null}
                fileName="favicon_file"
                mediaName="favicon_media_id"
                removeName="remove_favicon"
                removeLabel="Quitar favicon"
                uploadLabel="Subir favicon"
                availableImages={availableImages}
                errors={errors}
            />
        </div>
    );
}

function MediaPicker({
    title,
    emptyText,
    current,
    fileName,
    mediaName,
    removeName,
    removeLabel,
    uploadLabel,
    availableImages,
    errors,
}: {
    title: string;
    emptyText: string;
    current: { id: number; url: string } | null;
    fileName: string;
    mediaName: string;
    removeName: string;
    removeLabel: string;
    uploadLabel: string;
    availableImages: MediaImage[];
    errors: Record<string, string>;
}) {
    const [mediaId, setMediaId] = useState<number | null>(null);
    const [filePreview, setFilePreview] = useState<string | null>(null);
    const [removeMedia, setRemoveMedia] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    const onFileChange = (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        setFilePreview(file ? URL.createObjectURL(file) : null);
        if (file) {
            setMediaId(null);
            setRemoveMedia(false);
        }
    };

    const pickFromLibrary = (id: number) => {
        setMediaId((currentId) => (currentId === id ? null : id));
        setRemoveMedia(false);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
        setFilePreview(null);
    };

    const currentPreview =
        filePreview ??
        (mediaId !== null
            ? (availableImages.find((image) => image.id === mediaId)?.url ??
              null)
            : !removeMedia
              ? (current?.url ?? null)
              : null);

    return (
        <fieldset className="grid gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
            <legend className="px-1 text-sm font-medium">{title}</legend>

            <div className="flex items-center gap-4">
                <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed border-neutral-300 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                    {currentPreview ? (
                        <img
                            src={currentPreview}
                            alt={title}
                            className="size-full object-contain"
                        />
                    ) : (
                        <span className="text-xs text-neutral-400">
                            {emptyText}
                        </span>
                    )}
                </div>

                {current && (
                    <label className="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                        <input
                            type="checkbox"
                            name={removeName}
                            value="1"
                            checked={removeMedia}
                            onChange={(event) => {
                                setRemoveMedia(event.target.checked);
                                if (event.target.checked) {
                                    setMediaId(null);
                                    setFilePreview(null);
                                    if (fileRef.current) {
                                        fileRef.current.value = '';
                                    }
                                }
                            }}
                            className="size-4 rounded border-neutral-300 dark:border-neutral-700"
                        />
                        {removeLabel}
                    </label>
                )}
            </div>

            <div className="grid gap-2">
                <Label htmlFor={fileName}>{uploadLabel}</Label>
                <input
                    ref={fileRef}
                    id={fileName}
                    name={fileName}
                    type="file"
                    accept="image/*,.ico"
                    onChange={onFileChange}
                    className="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm dark:file:bg-neutral-800"
                />
                <InputError message={errors[fileName]} />
            </div>

            {availableImages.length > 0 && (
                <div className="grid gap-2">
                    <span className="text-sm text-neutral-600 dark:text-neutral-400">
                        O elegir de la biblioteca
                    </span>
                    <div className="grid max-h-48 grid-cols-4 gap-2 overflow-y-auto sm:grid-cols-6">
                        {availableImages.map((image) => (
                            <button
                                key={image.id}
                                type="button"
                                onClick={() => pickFromLibrary(image.id)}
                                title={image.name}
                                className={cn(
                                    'flex aspect-square items-center justify-center overflow-hidden rounded-md border bg-neutral-50 p-1 transition-colors dark:bg-neutral-900',
                                    mediaId === image.id
                                        ? 'border-neutral-900 ring-2 ring-neutral-900 dark:border-neutral-100 dark:ring-neutral-100'
                                        : 'border-neutral-200 hover:border-neutral-400 dark:border-neutral-800',
                                )}
                            >
                                <img
                                    src={image.url}
                                    alt={image.name}
                                    className="size-full object-contain"
                                />
                            </button>
                        ))}
                    </div>
                    {mediaId !== null && (
                        <input type="hidden" name={mediaName} value={mediaId} />
                    )}
                    <InputError message={errors[mediaName]} />
                </div>
            )}
        </fieldset>
    );
}
