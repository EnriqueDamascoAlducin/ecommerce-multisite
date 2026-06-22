import { useState } from 'react';
import { MediaPicker, type MediaImage } from '@/components/admin/media-picker';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type StoreDefaults = {
    website_id: number;
    code: string;
    name: string;
    is_default: boolean;
    is_active: boolean;
    sort_order: number;
    domains: string[];
    logo?: { id: number; url: string } | null;
    pwa_icon?: { id: number; url: string } | null;
};

export function StoreFields({
    errors,
    websites,
    defaults,
    availableImages,
}: {
    errors: Record<string, string>;
    websites: { id: number; name: string }[];
    availableImages: MediaImage[];
    defaults?: StoreDefaults;
}) {
    // El primer dominio se marca como primario en el backend.
    const [domains, setDomains] = useState<string[]>(
        defaults?.domains.length ? defaults.domains : [''],
    );

    const updateDomain = (index: number, value: string) => {
        setDomains((current) =>
            current.map((host, i) => (i === index ? value : host)),
        );
    };

    return (
        <div className="space-y-6">
            <div className="grid gap-2">
                <Label htmlFor="website_id">Website</Label>
                <select
                    id="website_id"
                    name="website_id"
                    defaultValue={defaults?.website_id}
                    required
                    className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    <option value="">Selecciona…</option>
                    {websites.map((website) => (
                        <option key={website.id} value={website.id}>
                            {website.name}
                        </option>
                    ))}
                </select>
                <InputError message={errors.website_id} />
            </div>

            <div className="grid gap-2 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="code">Código</Label>
                    <Input
                        id="code"
                        name="code"
                        defaultValue={defaults?.code}
                        required
                        placeholder="main, sports…"
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
            </div>

            <div className="flex gap-6">
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="is_default"
                        value="1"
                        defaultChecked={defaults?.is_default}
                        className="size-4 rounded"
                    />
                    Tienda por defecto del website
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        name="is_active"
                        value="1"
                        defaultChecked={defaults?.is_active ?? true}
                        className="size-4 rounded"
                    />
                    Activa
                </label>
            </div>

            <div className="grid gap-2">
                <Label>Dominios</Label>
                <p className="text-xs text-neutral-500">
                    El primero es el dominio primario. Las tiendas sin dominio
                    se alcanzan por prefijo de ruta (p. ej.{' '}
                    <code>/{defaults?.code || 'codigo'}</code>).
                </p>
                {domains.map((host, index) => (
                    <div key={index} className="flex gap-2">
                        <Input
                            name="domains[]"
                            value={host}
                            onChange={(event) =>
                                updateDomain(index, event.target.value)
                            }
                            placeholder="tienda.com.mx"
                        />
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() =>
                                setDomains((current) =>
                                    current.filter((_, i) => i !== index),
                                )
                            }
                        >
                            Quitar
                        </Button>
                    </div>
                ))}
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => setDomains((current) => [...current, ''])}
                >
                    Añadir dominio
                </Button>
                <InputError message={errors.domains} />
            </div>

            <MediaPicker
                title="Logo de tienda"
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

            <div className="grid gap-2">
                <p className="text-xs text-neutral-500">
                    El icono de instalación debe ser PNG, JPG o WebP, cuadrado
                    y de al menos 512×512 px.
                </p>
                <MediaPicker
                    title="Icono de instalación"
                    emptyText="Sin icono"
                    current={defaults?.pwa_icon ?? null}
                    fileName="pwa_icon_file"
                    mediaName="pwa_icon_media_id"
                    removeName="remove_pwa_icon"
                    removeLabel="Quitar icono"
                    uploadLabel="Subir icono"
                    availableImages={[]}
                    errors={errors}
                />
            </div>
        </div>
    );
}
