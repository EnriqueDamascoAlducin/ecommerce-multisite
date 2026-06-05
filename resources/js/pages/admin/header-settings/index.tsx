import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import {
    CintilloBar,
    type CintilloData,
} from '@/components/storefront/cintillo';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import headerSettings from '@/routes/admin/header-settings';

type BlockType = 'text' | 'social' | 'image';

type FormBlock = {
    type: BlockType;
    text: string;
    social: { platform: string; url: string }[];
    url: string;
    media_id: number | null;
    alt: string;
    link: string;
};

type SettingsBlock = {
    type: BlockType;
    text?: string | null;
    social?: { platform: string; url: string }[];
    url?: string | null;
    media_id?: number | null;
    alt?: string | null;
    link?: string | null;
};

type Settings = {
    cintillo_enabled: boolean;
    cintillo_show_on_mobile: boolean;
    cintillo_blocks: SettingsBlock[];
    cintillo_text_color: string;
    cintillo_background_color: string;
    header_text_color: string | null;
    header_background_color: string | null;
    menu_text_color: string | null;
    menu_background_color: string | null;
};

type CintilloForm = {
    website_id: number;
    cintillo_enabled: boolean;
    cintillo_show_on_mobile: boolean;
    cintillo_blocks: FormBlock[];
    cintillo_text_color: string;
    cintillo_background_color: string;
    header_text_color: string | null;
    header_background_color: string | null;
    menu_text_color: string | null;
    menu_background_color: string | null;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function HeaderSettingsPage({
    websites,
    currentWebsiteId,
    settings,
    platforms,
}: {
    websites: { id: number; label: string }[];
    currentWebsiteId: number | null;
    settings: Settings | null;
    platforms: string[];
}) {
    const onWebsiteChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        router.get(
            headerSettings.edit().url,
            { website_id: Number(event.target.value) },
            { preserveState: false, preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="Cintillo del encabezado" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">Cintillo del encabezado</h1>
                    <p className="text-sm text-neutral-500">
                        Franja superior con bloques de texto o redes (máx 3). Se aplica a todas las tiendas del sitio.
                    </p>
                </div>

                {currentWebsiteId !== null && (
                    <div className="grid gap-1">
                        <Label htmlFor="website_id" className="text-xs text-neutral-500">
                            Sitio (website)
                        </Label>
                        <select
                            id="website_id"
                            value={currentWebsiteId}
                            onChange={onWebsiteChange}
                            className={fieldClass}
                        >
                            {websites.map((website) => (
                                <option key={website.id} value={website.id}>
                                    {website.label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}
            </div>

            {settings && currentWebsiteId !== null ? (
                <CintilloForm
                    key={currentWebsiteId}
                    websiteId={currentWebsiteId}
                    settings={settings}
                    platforms={platforms}
                />
            ) : (
                <p className="text-sm text-neutral-500">No hay ningún sitio configurado.</p>
            )}
        </>
    );
}

function CintilloForm({
    websiteId,
    settings,
    platforms,
}: {
    websiteId: number;
    settings: Settings;
    platforms: string[];
}) {
    const { data, setData, put, processing, errors } = useForm<CintilloForm>({
        website_id: websiteId,
        cintillo_enabled: settings.cintillo_enabled,
        cintillo_show_on_mobile: settings.cintillo_show_on_mobile,
        cintillo_blocks: settings.cintillo_blocks.map((block) => ({
            type: block.type,
            text: block.text ?? '',
            social: block.social ?? [],
            url: block.url ?? '',
            media_id: block.media_id ?? null,
            alt: block.alt ?? '',
            link: block.link ?? '',
        })),
        cintillo_text_color: settings.cintillo_text_color,
        cintillo_background_color: settings.cintillo_background_color,
        header_text_color: settings.header_text_color,
        header_background_color: settings.header_background_color,
        menu_text_color: settings.menu_text_color,
        menu_background_color: settings.menu_background_color,
    });

    const blocks = data.cintillo_blocks;

    const preview: CintilloData = {
        enabled: true,
        show_on_mobile: true,
        blocks,
        text_color: data.cintillo_text_color,
        background_color: data.cintillo_background_color,
    };

    const updateBlocks = (next: FormBlock[]) => setData('cintillo_blocks', next);

    const patchBlock = (index: number, patch: Partial<FormBlock>) =>
        updateBlocks(blocks.map((block, i) => (i === index ? { ...block, ...patch } : block)));

    const addBlock = () => {
        if (blocks.length >= 3) {
            return;
        }
        updateBlocks([
            ...blocks,
            { type: 'text', text: '', social: [], url: '', media_id: null, alt: '', link: '' },
        ]);
    };

    const removeBlock = (index: number) => updateBlocks(blocks.filter((_, i) => i !== index));

    const uploadImage = async (index: number, file: File) => {
        const form = new FormData();
        form.append('file', file);

        const xsrf = decodeURIComponent(
            document.cookie.split('; ').find((row) => row.startsWith('XSRF-TOKEN='))?.split('=')[1] ?? '',
        );

        const response = await fetch(headerSettings.image.url(), {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
        });

        if (!response.ok) {
            return;
        }

        const result = (await response.json()) as { id: number; url: string };
        patchBlock(index, { url: result.url, media_id: result.id });
    };

    const addSocial = (index: number) => {
        const used = blocks[index].social.map((s) => s.platform);
        const next = platforms.find((platform) => !used.includes(platform)) ?? platforms[0];
        patchBlock(index, { social: [...blocks[index].social, { platform: next, url: '' }] });
    };

    const updateSocial = (index: number, socialIndex: number, key: 'platform' | 'url', value: string) =>
        patchBlock(index, {
            social: blocks[index].social.map((s, i) => (i === socialIndex ? { ...s, [key]: value } : s)),
        });

    const removeSocial = (index: number, socialIndex: number) =>
        patchBlock(index, { social: blocks[index].social.filter((_, i) => i !== socialIndex) });

    const headerCustom = data.header_text_color !== null;
    const menuCustom = data.menu_text_color !== null;

    const setHeaderCustom = (on: boolean) => {
        setData('header_text_color', on ? '#111827' : null);
        setData('header_background_color', on ? '#ffffff' : null);
    };

    const setMenuCustom = (on: boolean) => {
        setData('menu_text_color', on ? '#525252' : null);
        setData('menu_background_color', on ? '#ffffff' : null);
    };

    const submit = () => put(headerSettings.update().url);

    return (
        <div className="max-w-2xl space-y-6 rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
            <div className="flex flex-wrap gap-x-6 gap-y-2">
                <label className="flex items-center gap-2 text-sm font-medium">
                    <input
                        type="checkbox"
                        checked={data.cintillo_enabled}
                        onChange={(e) => setData('cintillo_enabled', e.target.checked)}
                        className="size-4 rounded"
                    />
                    Mostrar cintillo
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={data.cintillo_show_on_mobile}
                        onChange={(e) => setData('cintillo_show_on_mobile', e.target.checked)}
                        className="size-4 rounded"
                    />
                    Mostrar en mobile
                </label>
            </div>

            <div className="grid gap-3">
                <div className="flex items-center justify-between">
                    <Label>Bloques (máx 3)</Label>
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={addBlock}
                        disabled={blocks.length >= 3}
                    >
                        <Plus className="size-4" /> Agregar bloque
                    </Button>
                </div>
                <InputError message={errors.cintillo_blocks} />

                {blocks.length === 0 && (
                    <p className="text-sm text-neutral-500">Sin bloques. Agrega uno para mostrar contenido.</p>
                )}

                {blocks.map((block, index) => (
                    <div
                        key={index}
                        className="grid gap-3 rounded-md border border-neutral-200 p-4 dark:border-neutral-800"
                    >
                        <div className="flex items-center gap-2">
                            <select
                                value={block.type}
                                onChange={(e) =>
                                    patchBlock(index, { type: e.target.value as BlockType })
                                }
                                className={`${fieldClass} w-40`}
                            >
                                <option value="text">Texto</option>
                                <option value="social">Redes sociales</option>
                                <option value="image">Imagen</option>
                            </select>
                            <span className="text-xs text-neutral-500">Bloque {index + 1}</span>
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                className="ml-auto"
                                onClick={() => removeBlock(index)}
                            >
                                <Trash2 className="size-4" />
                            </Button>
                        </div>

                        {block.type === 'text' && (
                            <Input
                                value={block.text}
                                maxLength={255}
                                onChange={(e) => patchBlock(index, { text: e.target.value })}
                                placeholder="Envío gratis en compras mayores a $999"
                            />
                        )}

                        {block.type === 'image' && (
                            <div className="grid gap-2">
                                {block.url && (
                                    <div className="flex h-12 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50 p-1 dark:border-neutral-800 dark:bg-neutral-900">
                                        <img src={block.url} alt={block.alt} className="h-full w-auto object-contain" />
                                    </div>
                                )}
                                <input
                                    type="file"
                                    accept="image/*"
                                    onChange={(e) => {
                                        const file = e.target.files?.[0];
                                        if (file) {
                                            void uploadImage(index, file);
                                        }
                                    }}
                                    className="text-sm"
                                />
                                <Input
                                    value={block.alt}
                                    maxLength={255}
                                    onChange={(e) => patchBlock(index, { alt: e.target.value })}
                                    placeholder="Texto alternativo (accesibilidad)"
                                />
                                <Input
                                    value={block.link}
                                    onChange={(e) => patchBlock(index, { link: e.target.value })}
                                    placeholder="Enlace al hacer clic (opcional) https://…"
                                />
                                <InputError message={(errors as Record<string, string>)[`cintillo_blocks.${index}.link`]} />
                            </div>
                        )}

                        {block.type === 'social' && (
                            <div className="grid gap-2">
                                {block.social.map((row, socialIndex) => (
                                    <div key={socialIndex} className="flex items-center gap-2">
                                        <select
                                            value={row.platform}
                                            onChange={(e) =>
                                                updateSocial(index, socialIndex, 'platform', e.target.value)
                                            }
                                            className={`${fieldClass} w-40 capitalize`}
                                        >
                                            {platforms.map((platform) => (
                                                <option key={platform} value={platform} className="capitalize">
                                                    {platform}
                                                </option>
                                            ))}
                                        </select>
                                        <Input
                                            value={row.url}
                                            placeholder="https://…"
                                            onChange={(e) =>
                                                updateSocial(index, socialIndex, 'url', e.target.value)
                                            }
                                        />
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => removeSocial(index, socialIndex)}
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                ))}
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="w-fit"
                                    onClick={() => addSocial(index)}
                                >
                                    <Plus className="size-4" /> Agregar red
                                </Button>
                            </div>
                        )}
                    </div>
                ))}
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
                <ColorField
                    label="Color de letra"
                    value={data.cintillo_text_color}
                    onChange={(value) => setData('cintillo_text_color', value)}
                    error={errors.cintillo_text_color}
                />
                <ColorField
                    label="Color de fondo"
                    value={data.cintillo_background_color}
                    onChange={(value) => setData('cintillo_background_color', value)}
                    error={errors.cintillo_background_color}
                />
            </div>

            <div className="grid gap-2">
                <Label>Vista previa</Label>
                <div className="overflow-hidden rounded-md border border-neutral-200 dark:border-neutral-800">
                    <CintilloBar cintillo={preview} preview />
                </div>
            </div>

            <div className="grid gap-4 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                <div>
                    <h2 className="text-sm font-semibold">Colores del encabezado y menú</h2>
                    <p className="text-xs text-neutral-500">
                        Si no los personalizas, se usa el estilo por defecto (con modo oscuro).
                    </p>
                </div>

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={headerCustom}
                        onChange={(e) => setHeaderCustom(e.target.checked)}
                        className="size-4 rounded"
                    />
                    Personalizar colores del header
                </label>
                {headerCustom && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <ColorField
                            label="Texto del header"
                            value={data.header_text_color ?? '#111827'}
                            onChange={(value) => setData('header_text_color', value)}
                            error={errors.header_text_color}
                        />
                        <ColorField
                            label="Fondo del header"
                            value={data.header_background_color ?? '#ffffff'}
                            onChange={(value) => setData('header_background_color', value)}
                            error={errors.header_background_color}
                        />
                    </div>
                )}

                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={menuCustom}
                        onChange={(e) => setMenuCustom(e.target.checked)}
                        className="size-4 rounded"
                    />
                    Personalizar colores del mega menú
                </label>
                {menuCustom && (
                    <div className="grid gap-4 sm:grid-cols-2">
                        <ColorField
                            label="Texto del menú"
                            value={data.menu_text_color ?? '#525252'}
                            onChange={(value) => setData('menu_text_color', value)}
                            error={errors.menu_text_color}
                        />
                        <ColorField
                            label="Fondo del menú"
                            value={data.menu_background_color ?? '#ffffff'}
                            onChange={(value) => setData('menu_background_color', value)}
                            error={errors.menu_background_color}
                        />
                    </div>
                )}
            </div>

            <div className="flex justify-end">
                <Button onClick={submit} disabled={processing}>
                    Guardar
                </Button>
            </div>
        </div>
    );
}

function ColorField({
    label,
    value,
    onChange,
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <div className="flex items-center gap-2">
                <input
                    type="color"
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="h-9 w-12 rounded border border-neutral-300 dark:border-neutral-700"
                />
                <Input value={value} onChange={(e) => onChange(e.target.value)} className="font-mono" />
            </div>
            <InputError message={error} />
        </div>
    );
}
