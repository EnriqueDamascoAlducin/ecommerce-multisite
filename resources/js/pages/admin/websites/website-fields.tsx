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
    };
    availableImages: MediaImage[];
}) {
    const [mediaId, setMediaId] = useState<number | null>(null);
    const [filePreview, setFilePreview] = useState<string | null>(null);
    const [removeLogo, setRemoveLogo] = useState(false);
    const fileRef = useRef<HTMLInputElement>(null);

    const onFileChange = (event: React.ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0] ?? null;
        setFilePreview(file ? URL.createObjectURL(file) : null);
        if (file) {
            setMediaId(null);
            setRemoveLogo(false);
        }
    };

    const pickFromLibrary = (id: number) => {
        setMediaId((current) => (current === id ? null : id));
        setRemoveLogo(false);
        if (fileRef.current) {
            fileRef.current.value = '';
        }
        setFilePreview(null);
    };

    const currentPreview =
        filePreview ??
        (mediaId !== null
            ? (availableImages.find((image) => image.id === mediaId)?.url ?? null)
            : (!removeLogo ? (defaults?.logo?.url ?? null) : null));

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="code">Código</Label>
                <Input id="code" name="code" defaultValue={defaults?.code} required placeholder="interferenciales" />
                <InputError message={errors.code} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="name">Nombre</Label>
                <Input id="name" name="name" defaultValue={defaults?.name} required />
                <InputError message={errors.name} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="sort_order">Orden</Label>
                <Input id="sort_order" name="sort_order" type="number" min={0} defaultValue={defaults?.sort_order ?? 0} />
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

            <fieldset className="grid gap-3 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                <legend className="px-1 text-sm font-medium">Logo</legend>

                <div className="flex items-center gap-4">
                    <div className="flex size-16 shrink-0 items-center justify-center overflow-hidden rounded-md border border-dashed border-neutral-300 bg-neutral-50 dark:border-neutral-700 dark:bg-neutral-900">
                        {currentPreview ? (
                            <img src={currentPreview} alt="Logo" className="size-full object-contain" />
                        ) : (
                            <span className="text-xs text-neutral-400">Sin logo</span>
                        )}
                    </div>

                    {defaults?.logo && (
                        <label className="flex items-center gap-2 text-sm text-neutral-600 dark:text-neutral-400">
                            <input
                                type="checkbox"
                                name="remove_logo"
                                value="1"
                                checked={removeLogo}
                                onChange={(event) => {
                                    setRemoveLogo(event.target.checked);
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
                            Quitar logo
                        </label>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="logo_file">Subir imagen</Label>
                    <input
                        ref={fileRef}
                        id="logo_file"
                        name="logo_file"
                        type="file"
                        accept="image/*"
                        onChange={onFileChange}
                        className="text-sm file:mr-3 file:rounded-md file:border-0 file:bg-neutral-100 file:px-3 file:py-1.5 file:text-sm dark:file:bg-neutral-800"
                    />
                    <InputError message={errors.logo_file} />
                </div>

                {availableImages.length > 0 && (
                    <div className="grid gap-2">
                        <span className="text-sm text-neutral-600 dark:text-neutral-400">O elegir de la biblioteca</span>
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
                                    <img src={image.url} alt={image.name} className="size-full object-contain" />
                                </button>
                            ))}
                        </div>
                        {mediaId !== null && <input type="hidden" name="logo_media_id" value={mediaId} />}
                        <InputError message={errors.logo_media_id} />
                    </div>
                )}
            </fieldset>
        </div>
    );
}
