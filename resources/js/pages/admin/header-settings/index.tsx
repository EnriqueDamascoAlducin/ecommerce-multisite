import { Head, router, useForm } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
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

type CintilloMode = 'website' | 'inherit' | 'custom';

type StoreOption = { id: number; label: string };

type FormImage = {
    url: string;
    media_id: number | null;
    alt: string;
    link: string;
};

type SettingsImage = {
    url?: string | null;
    media_id?: number | null;
    alt?: string | null;
    link?: string | null;
};

type FormBlock = {
    type: BlockType;
    text: string;
    social: { platform: string; url: string }[];
    images: FormImage[];
};

type SettingsBlock = SettingsImage & {
    type: BlockType;
    text?: string | null;
    social?: { platform: string; url: string }[];
    images?: SettingsImage[];
};

type FooterLink = {
    label: string;
    url: string;
};

type FooterColumn = {
    title: string;
    title_color: string | null;
    link_color: string | null;
    links: FooterLink[];
};

type FooterContact = {
    label: string;
    value: string;
};

type FooterSocial = {
    platform: string;
    url: string;
};

type FooterSettings = {
    enabled: boolean;
    description: string;
    copyright: string;
    background_color: string | null;
    text_color: string | null;
    columns: FooterColumn[];
    contact: FooterContact[];
    social: FooterSocial[];
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
    footer: FooterSettings;
};

type CintilloForm = {
    website_id: number;
    store_id: number | null;
    cintillo_mode: CintilloMode;
    cintillo_enabled: boolean;
    cintillo_show_on_mobile: boolean;
    cintillo_blocks: FormBlock[];
    cintillo_text_color: string;
    cintillo_background_color: string;
    header_text_color: string | null;
    header_background_color: string | null;
    menu_text_color: string | null;
    menu_background_color: string | null;
    footer: FooterSettings;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

const textareaClass =
    'min-h-24 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function HeaderSettingsPage({
    websites,
    stores,
    currentWebsiteId,
    currentStoreId,
    cintilloMode,
    settings,
    inheritedCintillo,
    platforms,
}: {
    websites: { id: number; label: string }[];
    stores: StoreOption[];
    currentWebsiteId: number | null;
    currentStoreId: number | null;
    cintilloMode: CintilloMode;
    settings: Settings | null;
    inheritedCintillo: CintilloData | null;
    platforms: string[];
}) {
    const onWebsiteChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        router.get(
            headerSettings.edit().url,
            { website_id: Number(event.target.value) },
            { preserveState: false, preserveScroll: true },
        );
    };

    const onCintilloTargetChange = (
        event: React.ChangeEvent<HTMLSelectElement>,
    ) => {
        const value = event.target.value;
        const query =
            value === 'website'
                ? { website_id: currentWebsiteId }
                : { website_id: currentWebsiteId, store_id: Number(value) };

        router.get(headerSettings.edit().url, query, {
            preserveState: false,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Header y footer" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">Header y footer</h1>
                    <p className="text-sm text-neutral-500">
                        Configura cintillo, colores del encabezado, menú y pie
                        de página por website.
                    </p>
                </div>

                {currentWebsiteId !== null && (
                    <div className="flex flex-wrap items-end gap-3">
                        <div className="grid gap-1">
                            <Label
                                htmlFor="website_id"
                                className="text-xs text-neutral-500"
                            >
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
                        <div className="grid gap-1">
                            <Label
                                htmlFor="cintillo_target"
                                className="text-xs text-neutral-500"
                            >
                                Cintillo para
                            </Label>
                            <select
                                id="cintillo_target"
                                value={currentStoreId ?? 'website'}
                                onChange={onCintilloTargetChange}
                                className={fieldClass}
                            >
                                <option value="website">Website base</option>
                                {stores.map((store) => (
                                    <option key={store.id} value={store.id}>
                                        {store.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}
            </div>

            {settings && currentWebsiteId !== null ? (
                <CintilloForm
                    key={`${currentWebsiteId}-${currentStoreId ?? 'website'}-${cintilloMode}`}
                    websiteId={currentWebsiteId}
                    storeId={currentStoreId}
                    mode={cintilloMode}
                    settings={settings}
                    inheritedCintillo={inheritedCintillo}
                    platforms={platforms}
                />
            ) : (
                <p className="text-sm text-neutral-500">
                    No hay ningún sitio configurado.
                </p>
            )}
        </>
    );
}

function CintilloForm({
    websiteId,
    storeId,
    mode,
    settings,
    inheritedCintillo,
    platforms,
}: {
    websiteId: number;
    storeId: number | null;
    mode: CintilloMode;
    settings: Settings;
    inheritedCintillo: CintilloData | null;
    platforms: string[];
}) {
    const { data, setData, put, processing, errors } = useForm<CintilloForm>({
        website_id: websiteId,
        store_id: storeId,
        cintillo_mode: mode,
        cintillo_enabled: settings.cintillo_enabled,
        cintillo_show_on_mobile: settings.cintillo_show_on_mobile,
        cintillo_blocks: settings.cintillo_blocks.map((block) => ({
            type: block.type,
            text: block.text ?? '',
            social: block.social ?? [],
            images:
                block.images?.map((image) => ({
                    url: image.url ?? '',
                    media_id: image.media_id ?? null,
                    alt: image.alt ?? '',
                    link: image.link ?? '',
                })) ??
                (block.url
                    ? [
                          {
                              url: block.url,
                              media_id: block.media_id ?? null,
                              alt: block.alt ?? '',
                              link: block.link ?? '',
                          },
                      ]
                    : []),
        })),
        cintillo_text_color: settings.cintillo_text_color,
        cintillo_background_color: settings.cintillo_background_color,
        header_text_color: settings.header_text_color,
        header_background_color: settings.header_background_color,
        menu_text_color: settings.menu_text_color,
        menu_background_color: settings.menu_background_color,
        footer: normalizeFooter(settings.footer),
    });
    const [imageUploadError, setImageUploadError] = useState<string | null>(
        null,
    );

    const blocks = data.cintillo_blocks;

    const preview: CintilloData = {
        enabled: true,
        show_on_mobile: true,
        blocks,
        text_color: data.cintillo_text_color,
        background_color: data.cintillo_background_color,
    };
    const isStoreTarget = data.store_id !== null;
    const isInheriting = isStoreTarget && data.cintillo_mode === 'inherit';
    const effectivePreview =
        isInheriting && inheritedCintillo ? inheritedCintillo : preview;

    const updateBlocks = (next: FormBlock[]) =>
        setData('cintillo_blocks', next);

    const patchBlock = (index: number, patch: Partial<FormBlock>) =>
        updateBlocks(
            blocks.map((block, i) =>
                i === index ? { ...block, ...patch } : block,
            ),
        );

    const addBlock = () => {
        if (blocks.length >= 3) {
            return;
        }
        updateBlocks([
            ...blocks,
            {
                type: 'text',
                text: '',
                social: [],
                images: [],
            },
        ]);
    };

    const removeBlock = (index: number) =>
        updateBlocks(blocks.filter((_, i) => i !== index));

    const patchImage = (
        blockIndex: number,
        imageIndex: number,
        patch: Partial<FormImage>,
    ) =>
        patchBlock(blockIndex, {
            images: blocks[blockIndex].images.map((image, index) =>
                index === imageIndex ? { ...image, ...patch } : image,
            ),
        });

    const addImage = (blockIndex: number) => {
        if (blocks[blockIndex].images.length >= 6) {
            return;
        }

        patchBlock(blockIndex, {
            images: [
                ...blocks[blockIndex].images,
                { url: '', media_id: null, alt: '', link: '' },
            ],
        });
    };

    const removeImage = (blockIndex: number, imageIndex: number) =>
        patchBlock(blockIndex, {
            images: blocks[blockIndex].images.filter(
                (_, index) => index !== imageIndex,
            ),
        });

    const uploadImage = async (
        blockIndex: number,
        imageIndex: number,
        file: File,
    ) => {
        setImageUploadError(null);

        const form = new FormData();
        form.append('file', file);

        const xsrf = decodeURIComponent(
            document.cookie
                .split('; ')
                .find((row) => row.startsWith('XSRF-TOKEN='))
                ?.split('=')[1] ?? '',
        );

        const response = await fetch(headerSettings.image.url(), {
            method: 'POST',
            body: form,
            credentials: 'same-origin',
            headers: { Accept: 'application/json', 'X-XSRF-TOKEN': xsrf },
        });

        if (!response.ok) {
            const result = (await response.json().catch(() => null)) as {
                message?: string;
                errors?: { file?: string[] };
            } | null;

            setImageUploadError(
                result?.errors?.file?.[0] ??
                    result?.message ??
                    'No se pudo subir la imagen.',
            );

            return;
        }

        const result = (await response.json()) as { id: number; url: string };
        patchImage(blockIndex, imageIndex, {
            url: result.url,
            media_id: result.id,
        });
    };

    const addSocial = (index: number) => {
        const used = blocks[index].social.map((s) => s.platform);
        const next =
            platforms.find((platform) => !used.includes(platform)) ??
            platforms[0];
        patchBlock(index, {
            social: [...blocks[index].social, { platform: next, url: '' }],
        });
    };

    const updateSocial = (
        index: number,
        socialIndex: number,
        key: 'platform' | 'url',
        value: string,
    ) =>
        patchBlock(index, {
            social: blocks[index].social.map((s, i) =>
                i === socialIndex ? { ...s, [key]: value } : s,
            ),
        });

    const removeSocial = (index: number, socialIndex: number) =>
        patchBlock(index, {
            social: blocks[index].social.filter((_, i) => i !== socialIndex),
        });

    const headerCustom = data.header_text_color !== null;
    const menuCustom = data.menu_text_color !== null;
    const footerCustom = data.footer.background_color !== null;

    const setHeaderCustom = (on: boolean) => {
        setData('header_text_color', on ? '#111827' : null);
        setData('header_background_color', on ? '#ffffff' : null);
    };

    const setMenuCustom = (on: boolean) => {
        setData('menu_text_color', on ? '#525252' : null);
        setData('menu_background_color', on ? '#ffffff' : null);
    };

    const setFooter = (footer: FooterSettings) => setData('footer', footer);
    const patchFooter = (patch: Partial<FooterSettings>) =>
        setFooter({ ...data.footer, ...patch });

    const setFooterCustom = (on: boolean) => {
        patchFooter({
            background_color: on ? '#f5f5f5' : null,
            text_color: on ? '#404040' : null,
        });
    };

    const addFooterColumn = () =>
        patchFooter({
            columns: [
                ...data.footer.columns,
                {
                    title: 'Nueva columna',
                    title_color: null,
                    link_color: null,
                    links: [{ label: '', url: '' }],
                },
            ],
        });

    const updateFooterColumn = (index: number, patch: Partial<FooterColumn>) =>
        patchFooter({
            columns: data.footer.columns.map((column, i) =>
                i === index ? { ...column, ...patch } : column,
            ),
        });

    const removeFooterColumn = (index: number) =>
        patchFooter({
            columns: data.footer.columns.filter((_, i) => i !== index),
        });

    const addFooterLink = (columnIndex: number) =>
        updateFooterColumn(columnIndex, {
            links: [
                ...data.footer.columns[columnIndex].links,
                { label: '', url: '' },
            ],
        });

    const updateFooterLink = (
        columnIndex: number,
        linkIndex: number,
        patch: Partial<FooterLink>,
    ) =>
        updateFooterColumn(columnIndex, {
            links: data.footer.columns[columnIndex].links.map((link, i) =>
                i === linkIndex ? { ...link, ...patch } : link,
            ),
        });

    const removeFooterLink = (columnIndex: number, linkIndex: number) =>
        updateFooterColumn(columnIndex, {
            links: data.footer.columns[columnIndex].links.filter(
                (_, i) => i !== linkIndex,
            ),
        });

    const addFooterContact = () =>
        patchFooter({
            contact: [...data.footer.contact, { label: '', value: '' }],
        });

    const updateFooterContact = (
        index: number,
        patch: Partial<FooterContact>,
    ) =>
        patchFooter({
            contact: data.footer.contact.map((row, i) =>
                i === index ? { ...row, ...patch } : row,
            ),
        });

    const removeFooterContact = (index: number) =>
        patchFooter({
            contact: data.footer.contact.filter((_, i) => i !== index),
        });

    const addFooterSocial = () => {
        const used = data.footer.social.map((social) => social.platform);
        const platform =
            platforms.find((candidate) => !used.includes(candidate)) ??
            platforms[0];

        patchFooter({ social: [...data.footer.social, { platform, url: '' }] });
    };

    const updateFooterSocial = (index: number, patch: Partial<FooterSocial>) =>
        patchFooter({
            social: data.footer.social.map((social, i) =>
                i === index ? { ...social, ...patch } : social,
            ),
        });

    const removeFooterSocial = (index: number) =>
        patchFooter({
            social: data.footer.social.filter((_, i) => i !== index),
        });

    const setCintilloMode = (nextMode: 'inherit' | 'custom') => {
        setData('cintillo_mode', nextMode);
    };

    const submit = () => put(headerSettings.update().url);

    return (
        <div className="max-w-2xl space-y-6 rounded-lg border border-neutral-200 p-6 dark:border-neutral-800">
            {isStoreTarget && (
                <div className="grid gap-3 rounded-md border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-900/40">
                    <div>
                        <h2 className="text-sm font-semibold">
                            Modo de cintillo
                        </h2>
                        <p className="mt-1 text-xs text-neutral-500">
                            Esta tienda puede heredar el cintillo del website o
                            usar uno propio.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-3">
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="cintillo_mode"
                                checked={data.cintillo_mode === 'inherit'}
                                onChange={() => setCintilloMode('inherit')}
                                className="size-4"
                            />
                            Heredar del website
                        </label>
                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="radio"
                                name="cintillo_mode"
                                checked={data.cintillo_mode === 'custom'}
                                onChange={() => setCintilloMode('custom')}
                                className="size-4"
                            />
                            Personalizar cintillo
                        </label>
                    </div>
                    {isInheriting && inheritedCintillo && (
                        <div className="grid gap-2">
                            <Label>Vista previa heredada</Label>
                            <div className="overflow-hidden rounded-md border border-neutral-200 dark:border-neutral-800">
                                <CintilloBar
                                    cintillo={inheritedCintillo}
                                    preview
                                />
                            </div>
                        </div>
                    )}
                </div>
            )}

            <fieldset
                disabled={isInheriting}
                className={`space-y-6 ${isInheriting ? 'opacity-60' : ''}`}
            >
                <div className="flex flex-wrap gap-x-6 gap-y-2">
                    <label className="flex items-center gap-2 text-sm font-medium">
                        <input
                            type="checkbox"
                            checked={data.cintillo_enabled}
                            onChange={(e) =>
                                setData('cintillo_enabled', e.target.checked)
                            }
                            className="size-4 rounded"
                        />
                        Mostrar cintillo
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.cintillo_show_on_mobile}
                            onChange={(e) =>
                                setData(
                                    'cintillo_show_on_mobile',
                                    e.target.checked,
                                )
                            }
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
                        <p className="text-sm text-neutral-500">
                            Sin bloques. Agrega uno para mostrar contenido.
                        </p>
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
                                        patchBlock(index, {
                                            type: e.target.value as BlockType,
                                        })
                                    }
                                    className={`${fieldClass} w-40`}
                                >
                                    <option value="text">Texto</option>
                                    <option value="social">
                                        Redes sociales
                                    </option>
                                    <option value="image">Imagen</option>
                                </select>
                                <span className="text-xs text-neutral-500">
                                    Bloque {index + 1}
                                </span>
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
                                    onChange={(e) =>
                                        patchBlock(index, {
                                            text: e.target.value,
                                        })
                                    }
                                    placeholder="Envío gratis en compras mayores a $999"
                                />
                            )}

                            {block.type === 'image' && (
                                <div className="grid gap-3">
                                    <div className="flex items-center justify-between gap-3">
                                        <p className="text-xs text-neutral-500">
                                            Logos enlazados (máx. 6)
                                        </p>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => addImage(index)}
                                            disabled={block.images.length >= 6}
                                        >
                                            <Plus className="size-4" /> Agregar
                                            logo
                                        </Button>
                                    </div>

                                    {block.images.length === 0 && (
                                        <p className="text-sm text-neutral-500">
                                            Agrega una imagen para configurar su
                                            enlace.
                                        </p>
                                    )}

                                    <InputError
                                        message={imageUploadError ?? undefined}
                                    />

                                    {block.images.map((image, imageIndex) => (
                                        <div
                                            key={imageIndex}
                                            className="grid gap-2 rounded-md border border-neutral-200 p-3 dark:border-neutral-800"
                                        >
                                            <div className="flex items-center gap-3">
                                                {image.url ? (
                                                    <div className="flex h-12 min-w-24 items-center justify-center overflow-hidden rounded-md border border-neutral-200 bg-neutral-50 p-1 dark:border-neutral-800 dark:bg-neutral-900">
                                                        <img
                                                            src={image.url}
                                                            alt={image.alt}
                                                            className="h-full max-w-32 object-contain"
                                                        />
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-neutral-500">
                                                        Sin imagen
                                                    </span>
                                                )}
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    className="ml-auto"
                                                    onClick={() =>
                                                        removeImage(
                                                            index,
                                                            imageIndex,
                                                        )
                                                    }
                                                >
                                                    <Trash2 className="size-4" />
                                                </Button>
                                            </div>
                                            <input
                                                type="file"
                                                accept="image/*"
                                                onChange={(e) => {
                                                    const file =
                                                        e.target.files?.[0];
                                                    if (file) {
                                                        void uploadImage(
                                                            index,
                                                            imageIndex,
                                                            file,
                                                        );
                                                    }
                                                }}
                                                className="text-sm"
                                            />
                                            <Input
                                                value={image.alt}
                                                maxLength={255}
                                                onChange={(e) =>
                                                    patchImage(
                                                        index,
                                                        imageIndex,
                                                        {
                                                            alt: e.target.value,
                                                        },
                                                    )
                                                }
                                                placeholder="Nombre del sitio o texto alternativo"
                                            />
                                            <Input
                                                value={image.link}
                                                onChange={(e) =>
                                                    patchImage(
                                                        index,
                                                        imageIndex,
                                                        {
                                                            link: e.target
                                                                .value,
                                                        },
                                                    )
                                                }
                                                placeholder="https://otro-sitio.com"
                                            />
                                            <InputError
                                                message={
                                                    (
                                                        errors as Record<
                                                            string,
                                                            string
                                                        >
                                                    )[
                                                        `cintillo_blocks.${index}.images.${imageIndex}.link`
                                                    ]
                                                }
                                            />
                                        </div>
                                    ))}
                                </div>
                            )}

                            {block.type === 'social' && (
                                <div className="grid gap-2">
                                    {block.social.map((row, socialIndex) => (
                                        <div
                                            key={socialIndex}
                                            className="flex items-center gap-2"
                                        >
                                            <select
                                                value={row.platform}
                                                onChange={(e) =>
                                                    updateSocial(
                                                        index,
                                                        socialIndex,
                                                        'platform',
                                                        e.target.value,
                                                    )
                                                }
                                                className={`${fieldClass} w-40 capitalize`}
                                            >
                                                {platforms.map((platform) => (
                                                    <option
                                                        key={platform}
                                                        value={platform}
                                                        className="capitalize"
                                                    >
                                                        {platform}
                                                    </option>
                                                ))}
                                            </select>
                                            <Input
                                                value={row.url}
                                                placeholder="https://…"
                                                onChange={(e) =>
                                                    updateSocial(
                                                        index,
                                                        socialIndex,
                                                        'url',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            <Button
                                                type="button"
                                                variant="ghost"
                                                size="icon"
                                                onClick={() =>
                                                    removeSocial(
                                                        index,
                                                        socialIndex,
                                                    )
                                                }
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
                        onChange={(value) =>
                            setData('cintillo_text_color', value)
                        }
                        error={errors.cintillo_text_color}
                    />
                    <ColorField
                        label="Color de fondo"
                        value={data.cintillo_background_color}
                        onChange={(value) =>
                            setData('cintillo_background_color', value)
                        }
                        error={errors.cintillo_background_color}
                    />
                </div>

                <div className="grid gap-2">
                    <Label>Vista previa</Label>
                    <div className="overflow-hidden rounded-md border border-neutral-200 dark:border-neutral-800">
                        <CintilloBar cintillo={effectivePreview} preview />
                    </div>
                </div>
            </fieldset>

            {!isStoreTarget && (
                <>
                    <div className="grid gap-4 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Colores del encabezado y menú
                            </h2>
                            <p className="text-xs text-neutral-500">
                                Si no los personalizas, se usa el estilo por
                                defecto (con modo oscuro).
                            </p>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={headerCustom}
                                onChange={(e) =>
                                    setHeaderCustom(e.target.checked)
                                }
                                className="size-4 rounded"
                            />
                            Personalizar colores del header
                        </label>
                        {headerCustom && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <ColorField
                                    label="Texto del header"
                                    value={data.header_text_color ?? '#111827'}
                                    onChange={(value) =>
                                        setData('header_text_color', value)
                                    }
                                    error={errors.header_text_color}
                                />
                                <ColorField
                                    label="Fondo del header"
                                    value={
                                        data.header_background_color ??
                                        '#ffffff'
                                    }
                                    onChange={(value) =>
                                        setData(
                                            'header_background_color',
                                            value,
                                        )
                                    }
                                    error={errors.header_background_color}
                                />
                            </div>
                        )}

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={menuCustom}
                                onChange={(e) =>
                                    setMenuCustom(e.target.checked)
                                }
                                className="size-4 rounded"
                            />
                            Personalizar colores del mega menú
                        </label>
                        {menuCustom && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <ColorField
                                    label="Texto del menú"
                                    value={data.menu_text_color ?? '#525252'}
                                    onChange={(value) =>
                                        setData('menu_text_color', value)
                                    }
                                    error={errors.menu_text_color}
                                />
                                <ColorField
                                    label="Fondo del menú"
                                    value={
                                        data.menu_background_color ?? '#ffffff'
                                    }
                                    onChange={(value) =>
                                        setData('menu_background_color', value)
                                    }
                                    error={errors.menu_background_color}
                                />
                            </div>
                        )}
                    </div>

                    <div className="grid gap-5 border-t border-neutral-200 pt-6 dark:border-neutral-800">
                        <div>
                            <h2 className="text-sm font-semibold">
                                Información del footer
                            </h2>
                            <p className="text-xs text-neutral-500">
                                Edita el texto, columnas de enlaces, contacto,
                                redes y colores del pie de página.
                            </p>
                        </div>

                        <label className="flex items-center gap-2 text-sm font-medium">
                            <input
                                type="checkbox"
                                checked={data.footer.enabled}
                                onChange={(e) =>
                                    patchFooter({ enabled: e.target.checked })
                                }
                                className="size-4 rounded"
                            />
                            Mostrar footer personalizado
                        </label>

                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label>Descripción</Label>
                                <textarea
                                    value={data.footer.description}
                                    maxLength={500}
                                    onChange={(e) =>
                                        patchFooter({
                                            description: e.target.value,
                                        })
                                    }
                                    placeholder="Breve descripción de la tienda o empresa"
                                    className={textareaClass}
                                />
                                <InputError
                                    message={
                                        (errors as Record<string, string>)[
                                            'footer.description'
                                        ]
                                    }
                                />
                            </div>

                            <div className="grid gap-2">
                                <Label>Copyright</Label>
                                <Input
                                    value={data.footer.copyright}
                                    maxLength={255}
                                    onChange={(e) =>
                                        patchFooter({
                                            copyright: e.target.value,
                                        })
                                    }
                                    placeholder="© {year} Mi empresa. Todos los derechos reservados."
                                />
                                <p className="text-xs text-neutral-500">
                                    Usa {'{year}'} para mostrar el año actual.
                                </p>
                                <InputError
                                    message={
                                        (errors as Record<string, string>)[
                                            'footer.copyright'
                                        ]
                                    }
                                />
                            </div>
                        </div>

                        <label className="flex items-center gap-2 text-sm">
                            <input
                                type="checkbox"
                                checked={footerCustom}
                                onChange={(e) =>
                                    setFooterCustom(e.target.checked)
                                }
                                className="size-4 rounded"
                            />
                            Personalizar colores del footer
                        </label>
                        {footerCustom && (
                            <div className="grid gap-4 sm:grid-cols-2">
                                <ColorField
                                    label="Fondo del footer"
                                    value={
                                        data.footer.background_color ??
                                        '#f5f5f5'
                                    }
                                    onChange={(value) =>
                                        patchFooter({ background_color: value })
                                    }
                                    error={
                                        (errors as Record<string, string>)[
                                            'footer.background_color'
                                        ]
                                    }
                                />
                                <ColorField
                                    label="Texto del footer"
                                    value={data.footer.text_color ?? '#404040'}
                                    onChange={(value) =>
                                        patchFooter({ text_color: value })
                                    }
                                    error={
                                        (errors as Record<string, string>)[
                                            'footer.text_color'
                                        ]
                                    }
                                />
                            </div>
                        )}

                        <FooterListHeader
                            title="Columnas de enlaces"
                            actionLabel="Agregar columna"
                            disabled={data.footer.columns.length >= 4}
                            onAdd={addFooterColumn}
                        />
                        <div className="grid gap-3">
                            {data.footer.columns.length === 0 && (
                                <p className="text-sm text-neutral-500">
                                    Sin columnas. Puedes agregar hasta 4 grupos
                                    de links.
                                </p>
                            )}
                            {data.footer.columns.map((column, columnIndex) => (
                                <div
                                    key={columnIndex}
                                    className="grid gap-3 rounded-md border border-neutral-200 p-4 dark:border-neutral-800"
                                >
                                    <div className="flex items-center gap-2">
                                        <span className="text-xs font-medium text-neutral-500">
                                            Columna {columnIndex + 1}
                                        </span>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="icon"
                                            className="ml-auto"
                                            onClick={() =>
                                                removeFooterColumn(columnIndex)
                                            }
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                    <Input
                                        value={column.title}
                                        maxLength={80}
                                        onChange={(e) =>
                                            updateFooterColumn(columnIndex, {
                                                title: e.target.value,
                                            })
                                        }
                                        placeholder="Compañía"
                                    />
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <ColorField
                                            label="Color del título"
                                            value={
                                                column.title_color ?? '#b91c1c'
                                            }
                                            onChange={(value) =>
                                                updateFooterColumn(
                                                    columnIndex,
                                                    {
                                                        title_color: value,
                                                    },
                                                )
                                            }
                                            error={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )[
                                                    `footer.columns.${columnIndex}.title_color`
                                                ]
                                            }
                                        />
                                        <ColorField
                                            label="Color de links"
                                            value={
                                                column.link_color ??
                                                data.footer.text_color ??
                                                '#404040'
                                            }
                                            onChange={(value) =>
                                                updateFooterColumn(
                                                    columnIndex,
                                                    {
                                                        link_color: value,
                                                    },
                                                )
                                            }
                                            error={
                                                (
                                                    errors as Record<
                                                        string,
                                                        string
                                                    >
                                                )[
                                                    `footer.columns.${columnIndex}.link_color`
                                                ]
                                            }
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        {column.links.map((link, linkIndex) => (
                                            <div
                                                key={linkIndex}
                                                className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                                            >
                                                <Input
                                                    value={link.label}
                                                    maxLength={80}
                                                    onChange={(e) =>
                                                        updateFooterLink(
                                                            columnIndex,
                                                            linkIndex,
                                                            {
                                                                label: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="Etiqueta"
                                                />
                                                <Input
                                                    value={link.url}
                                                    onChange={(e) =>
                                                        updateFooterLink(
                                                            columnIndex,
                                                            linkIndex,
                                                            {
                                                                url: e.target
                                                                    .value,
                                                            },
                                                        )
                                                    }
                                                    placeholder="/nosotros o https://..."
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="icon"
                                                    onClick={() =>
                                                        removeFooterLink(
                                                            columnIndex,
                                                            linkIndex,
                                                        )
                                                    }
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
                                            onClick={() =>
                                                addFooterLink(columnIndex)
                                            }
                                            disabled={column.links.length >= 8}
                                        >
                                            <Plus className="size-4" /> Agregar
                                            link
                                        </Button>
                                    </div>
                                </div>
                            ))}
                        </div>

                        <FooterListHeader
                            title="Contacto"
                            actionLabel="Agregar dato"
                            onAdd={addFooterContact}
                        />
                        <div className="grid gap-2">
                            {data.footer.contact.map((row, index) => (
                                <div
                                    key={index}
                                    className="grid gap-2 sm:grid-cols-[1fr_1fr_auto]"
                                >
                                    <Input
                                        value={row.label}
                                        maxLength={80}
                                        onChange={(e) =>
                                            updateFooterContact(index, {
                                                label: e.target.value,
                                            })
                                        }
                                        placeholder="Teléfono"
                                    />
                                    <Input
                                        value={row.value}
                                        maxLength={160}
                                        onChange={(e) =>
                                            updateFooterContact(index, {
                                                value: e.target.value,
                                            })
                                        }
                                        placeholder="+52 55 1234 5678"
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            removeFooterContact(index)
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            {data.footer.contact.length === 0 && (
                                <p className="text-sm text-neutral-500">
                                    Sin datos de contacto visibles.
                                </p>
                            )}
                        </div>

                        <FooterListHeader
                            title="Redes sociales"
                            actionLabel="Agregar red"
                            onAdd={addFooterSocial}
                        />
                        <div className="grid gap-2">
                            {data.footer.social.map((social, index) => (
                                <div
                                    key={index}
                                    className="grid gap-2 sm:grid-cols-[10rem_1fr_auto]"
                                >
                                    <select
                                        value={social.platform}
                                        onChange={(e) =>
                                            updateFooterSocial(index, {
                                                platform: e.target.value,
                                            })
                                        }
                                        className={`${fieldClass} capitalize`}
                                    >
                                        {platforms.map((platform) => (
                                            <option
                                                key={platform}
                                                value={platform}
                                                className="capitalize"
                                            >
                                                {platform}
                                            </option>
                                        ))}
                                    </select>
                                    <Input
                                        value={social.url}
                                        onChange={(e) =>
                                            updateFooterSocial(index, {
                                                url: e.target.value,
                                            })
                                        }
                                        placeholder="https://..."
                                    />
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() =>
                                            removeFooterSocial(index)
                                        }
                                    >
                                        <Trash2 className="size-4" />
                                    </Button>
                                </div>
                            ))}
                            {data.footer.social.length === 0 && (
                                <p className="text-sm text-neutral-500">
                                    Sin redes sociales visibles en el footer.
                                </p>
                            )}
                        </div>
                    </div>
                </>
            )}

            {isStoreTarget && (
                <p className="rounded-md border border-neutral-200 bg-neutral-50 p-3 text-xs text-neutral-500 dark:border-neutral-800 dark:bg-neutral-900/40">
                    Los colores del header, el menú y el footer se siguen
                    configurando únicamente en el website base.
                </p>
            )}

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
                <Input
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    className="font-mono"
                />
            </div>
            <InputError message={error} />
        </div>
    );
}

function FooterListHeader({
    title,
    actionLabel,
    disabled = false,
    onAdd,
}: {
    title: string;
    actionLabel: string;
    disabled?: boolean;
    onAdd: () => void;
}) {
    return (
        <div className="flex items-center justify-between gap-3">
            <Label>{title}</Label>
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={onAdd}
                disabled={disabled}
            >
                <Plus className="size-4" /> {actionLabel}
            </Button>
        </div>
    );
}

function normalizeFooter(footer?: FooterSettings | null): FooterSettings {
    return {
        enabled: footer?.enabled ?? true,
        description: footer?.description ?? '',
        copyright:
            footer?.copyright ??
            '© {year} Mi tienda. Todos los derechos reservados.',
        background_color: footer?.background_color ?? null,
        text_color: footer?.text_color ?? null,
        columns: (footer?.columns ?? []).map((column) => ({
            title: column.title ?? '',
            title_color: column.title_color ?? null,
            link_color: column.link_color ?? null,
            links: (column.links ?? []).map((link) => ({
                label: link.label ?? '',
                url: link.url ?? '',
            })),
        })),
        contact: (footer?.contact ?? []).map((row) => ({
            label: row.label ?? '',
            value: row.value ?? '',
        })),
        social: (footer?.social ?? []).map((social) => ({
            platform: social.platform ?? 'facebook',
            url: social.url ?? '',
        })),
    };
}
