import { MediaPicker, type MediaImage } from '@/components/admin/media-picker';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
export type { MediaImage };

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
