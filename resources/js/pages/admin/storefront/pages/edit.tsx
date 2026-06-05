import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import {
    ArrowLeft,
    Copy,
    Eye,
    GripVertical,
    ImagePlus,
    Plus,
    Save,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import ProductSelector from '@/components/admin/product-selector';
import mediaRoutes from '@/routes/admin/media';
import storefrontPages from '@/routes/admin/storefront/pages';

type MediaOption = { id: number; label: string; url: string };
type StoreOption = { id: number; label: string };
type ProductOption = { id: number; label: string };
type CmsMedia = { id: number; url: string; alt: string | null } | null;
type SectionSettings = Record<string, FormDataConvertible> & {
    media?: CmsMedia;
    items?: BuilderItem[];
    buttons?: BuilderButton[];
    brands?: string[];
    interest_areas?: string[];
    product_ids?: number[];
    display_type?: string;
};
type Section = {
    id: number;
    type: string;
    sort_order: number;
    is_active: boolean;
    settings: SectionSettings;
};
type Page = {
    id: number;
    store_id: number;
    title: string;
    slug: string;
    is_published: boolean;
    sections: Section[];
};
type BuilderItem = {
    title?: string;
    text?: string;
    icon?: string;
    link?: string;
    highlighted?: boolean;
    media_id?: number | null;
    media?: CmsMedia;
    cta_label?: string;
    cta_url?: string;
};
type BuilderButton = { label?: string; url?: string };

const TYPE_LABELS: Record<string, string> = {
    hero: 'Hero',
    specialty_grid: 'Especialidades',
    feature_cards: 'Servicios / Educacion',
    brand_strip: 'Marcas',
    inquiry_form: 'Formulario contacto',
    text_image: 'Texto + imagen',
    gallery: 'Galeria',
    cta_banner: 'Banner CTA',
    featured_products: 'Productos destacados',
};

export default function StorefrontPageEdit({
    stores,
    currentStoreId,
    page,
    sectionTypes,
    media,
    productOptions,
    publicUrl,
    isHome,
}: {
    stores: StoreOption[];
    currentStoreId: number;
    page: Page;
    sectionTypes: string[];
    media: MediaOption[];
    productOptions: ProductOption[];
    publicUrl: string;
    isHome: boolean;
}) {
    const pageForm = useForm({
        store_id: currentStoreId,
        title: page.title,
        slug: page.slug,
        is_published: page.is_published,
    });
    const [selectedId, setSelectedId] = useState<number | null>(
        page.sections[0]?.id ?? null,
    );
    const [draggedId, setDraggedId] = useState<number | null>(null);

    const sortedSections = useMemo(
        () => [...page.sections].sort((a, b) => a.sort_order - b.sort_order),
        [page.sections],
    );
    const selected =
        sortedSections.find((section) => section.id === selectedId) ??
        sortedSections[0] ??
        null;

    const savePage = () => {
        pageForm.put(
            isHome
                ? storefrontPages.home.update.url()
                : storefrontPages.update.url(page.id),
            {
                preserveScroll: true,
            },
        );
    };

    const createSection = (type: string) => {
        router.post(
            storefrontPages.sections.store.url(page.id),
            sectionPayload({
                store_id: currentStoreId,
                type,
                is_active: true,
                settings: defaultSettings(type),
            }),
            { preserveScroll: true },
        );
    };

    const duplicateSection = (section: Section) => {
        router.post(
            storefrontPages.sections.store.url(page.id),
            sectionPayload({
                store_id: currentStoreId,
                type: section.type,
                is_active: section.is_active,
                settings: stripResolvedMedia(section.settings),
            }),
            { preserveScroll: true },
        );
    };

    const destroySection = (section: Section) => {
        if (
            confirm(
                `Eliminar seccion "${TYPE_LABELS[section.type] ?? section.type}"?`,
            )
        ) {
            router.delete(
                storefrontPages.sections.destroy.url([page.id, section.id]),
                {
                    preserveScroll: true,
                },
            );
        }
    };

    const dropOnSection = (targetId: number) => {
        if (!draggedId || draggedId === targetId) {
            return;
        }

        const next = [...sortedSections];
        const from = next.findIndex((section) => section.id === draggedId);
        const to = next.findIndex((section) => section.id === targetId);

        if (from < 0 || to < 0) {
            return;
        }

        const [moved] = next.splice(from, 1);
        next.splice(to, 0, moved);

        router.post(
            storefrontPages.sections.reorder.url(page.id),
            {
                sections: next.map((section, index) => ({
                    id: section.id,
                    sort_order: index,
                })),
            },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title={`Editar ${page.title}`} />

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div className="flex items-center gap-3">
                    <Button asChild variant="outline" size="sm">
                        <Link
                            href={storefrontPages.index.url({
                                query: { store_id: currentStoreId },
                            })}
                        >
                            <ArrowLeft className="size-4" />
                        </Link>
                    </Button>
                    <div>
                        <h1 className="text-2xl font-semibold">{page.title}</h1>
                        <p className="text-sm text-neutral-500">{publicUrl}</p>
                    </div>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button asChild variant="outline">
                        <a href={publicUrl} target="_blank" rel="noreferrer">
                            <Eye className="mr-1 size-4" />
                            Vista
                        </a>
                    </Button>
                    <Button onClick={savePage} disabled={pageForm.processing}>
                        <Save className="mr-1 size-4" />
                        Guardar pagina
                    </Button>
                </div>
            </div>

            <div className="mb-5 grid gap-3 rounded-lg border border-neutral-200 bg-white p-4 md:grid-cols-[1fr_1fr_auto] md:items-end dark:border-neutral-800 dark:bg-neutral-900">
                <div className="grid gap-1">
                    <Label>Titulo</Label>
                    <Input
                        value={pageForm.data.title}
                        onChange={(event) =>
                            pageForm.setData('title', event.target.value)
                        }
                    />
                </div>
                <div className="grid gap-1">
                    <Label>Slug</Label>
                    <Input
                        value={pageForm.data.slug}
                        disabled={isHome}
                        onChange={(event) =>
                            pageForm.setData(
                                'slug',
                                slugify(event.target.value),
                            )
                        }
                    />
                </div>
                <label className="flex items-center gap-2 pb-2 text-sm">
                    <Checkbox
                        checked={pageForm.data.is_published}
                        onCheckedChange={(value) =>
                            pageForm.setData('is_published', value === true)
                        }
                    />
                    Publicada
                </label>
            </div>

            <div className="grid gap-5 lg:grid-cols-[19rem_1fr_24rem]">
                <aside className="rounded-lg border border-neutral-200 bg-white p-3 dark:border-neutral-800 dark:bg-neutral-900">
                    <h2 className="mb-3 font-semibold">Bloques</h2>
                    <div className="mb-4 grid gap-2">
                        <Select onValueChange={createSection}>
                            <SelectTrigger>
                                <SelectValue placeholder="Agregar seccion" />
                            </SelectTrigger>
                            <SelectContent>
                                {sectionTypes.map((type) => (
                                    <SelectItem key={type} value={type}>
                                        {TYPE_LABELS[type] ?? type}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-2">
                        {sortedSections.map((section) => (
                            <button
                                key={section.id}
                                type="button"
                                draggable
                                onClick={() => setSelectedId(section.id)}
                                onDragStart={() => setDraggedId(section.id)}
                                onDragOver={(event) => event.preventDefault()}
                                onDrop={() => dropOnSection(section.id)}
                                className={[
                                    'flex w-full items-center gap-2 rounded-md border px-3 py-2 text-left text-sm transition',
                                    selected?.id === section.id
                                        ? 'border-red-800 bg-red-50 text-red-950'
                                        : 'border-neutral-200 bg-white hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950',
                                ].join(' ')}
                            >
                                <GripVertical className="size-4 shrink-0 text-neutral-400" />
                                <span className="min-w-0 flex-1 truncate">
                                    {TYPE_LABELS[section.type] ?? section.type}
                                </span>
                                {!section.is_active && (
                                    <span className="text-xs text-neutral-500">
                                        Oculto
                                    </span>
                                )}
                            </button>
                        ))}
                        {sortedSections.length === 0 && (
                            <p className="py-8 text-center text-sm text-neutral-500">
                                Agrega primera seccion.
                            </p>
                        )}
                    </div>
                </aside>

                <main className="min-h-[34rem] overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-950">
                    {sortedSections.map((section) => (
                        <SectionPreview
                            key={section.id}
                            section={section}
                            active={selected?.id === section.id}
                            onClick={() => setSelectedId(section.id)}
                        />
                    ))}
                </main>

                <aside className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900">
                    {selected ? (
                        <SectionEditor
                            pageId={page.id}
                            storeId={currentStoreId}
                            section={selected}
                            media={media}
                            productOptions={productOptions}
                            onDuplicate={() => duplicateSection(selected)}
                            onDelete={() => destroySection(selected)}
                        />
                    ) : (
                        <p className="text-sm text-neutral-500">
                            Selecciona una seccion.
                        </p>
                    )}
                </aside>
            </div>
        </>
    );
}

function SectionEditor({
    pageId,
    storeId,
    section,
    media,
    productOptions,
    onDuplicate,
    onDelete,
}: {
    pageId: number;
    storeId: number;
    section: Section;
    media: MediaOption[];
    productOptions: ProductOption[];
    onDuplicate: () => void;
    onDelete: () => void;
}) {
    const [draft, setDraft] = useState({
        store_id: storeId,
        type: section.type,
        is_active: section.is_active,
        settings: stripResolvedMedia(section.settings),
    });
    const [isSaving, setIsSaving] = useState(false);

    const setSetting = (key: string, value: FormDataConvertible) => {
        setDraft((data) => ({
            ...data,
            settings: { ...data.settings, [key]: value },
        }));
    };

    const save = () => {
        router.put(
            storefrontPages.sections.update.url([pageId, section.id]),
            sectionPayload(draft),
            {
                preserveScroll: true,
                onStart: () => setIsSaving(true),
                onFinish: () => setIsSaving(false),
            },
        );
    };

    return (
        <div className="space-y-4">
            <div className="flex items-center justify-between gap-2">
                <div>
                    <h2 className="font-semibold">
                        {TYPE_LABELS[section.type] ?? section.type}
                    </h2>
                    <label className="mt-2 flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={draft.is_active}
                            onCheckedChange={(value) =>
                                setDraft((data) => ({
                                    ...data,
                                    is_active: value === true,
                                }))
                            }
                        />
                        Visible
                    </label>
                </div>
                <div className="flex gap-2">
                    <Button variant="outline" size="sm" onClick={onDuplicate}>
                        <Copy className="size-4" />
                    </Button>
                    <Button variant="destructive" size="sm" onClick={onDelete}>
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>

            <TextField
                label="Etiqueta pequeña"
                value={text(draft.settings.eyebrow)}
                onChange={(value) => setSetting('eyebrow', value)}
            />
            <TextField
                label="Titulo"
                value={text(draft.settings.title)}
                onChange={(value) => setSetting('title', value)}
            />
            <TextArea
                label="Texto / subtitulo"
                value={
                    text(draft.settings.subtitle) || text(draft.settings.text)
                }
                onChange={(value) => {
                    setSetting(
                        section.type === 'hero' ? 'subtitle' : 'text',
                        value,
                    );
                }}
            />

            {usesMainImage(section.type) && (
                <MediaPicker
                    label="Imagen"
                    media={media}
                    value={numberValue(draft.settings.media_id)}
                    onChange={(value) => setSetting('media_id', value)}
                />
            )}

            {section.type === 'hero' && (
                <ButtonList
                    value={arrayValue<BuilderButton>(draft.settings.buttons)}
                    onChange={(value) => setSetting('buttons', value)}
                />
            )}

            {usesItems(section.type) && (
                <ItemList
                    type={section.type}
                    media={media}
                    value={arrayValue<BuilderItem>(draft.settings.items)}
                    onChange={(value) => setSetting('items', value)}
                />
            )}

            {section.type === 'brand_strip' && (
                <StringList
                    label="Marcas"
                    value={arrayValue<string>(draft.settings.brands)}
                    onChange={(value) => setSetting('brands', value)}
                />
            )}

            {section.type === 'featured_products' && (
                <>
                    <ProductSelector
                        label="Productos"
                        options={productOptions}
                        value={arrayValue<number>(draft.settings.product_ids)}
                        onChange={(value) => setSetting('product_ids', value)}
                    />
                    <div className="space-y-1.5">
                        <Label>Mostrar como</Label>
                        <Select
                            value={text(draft.settings.display_type) || 'grid'}
                            onValueChange={(value) => setSetting('display_type', value)}
                        >
                            <SelectTrigger className="w-full">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="grid">Grid de productos</SelectItem>
                                <SelectItem value="carrousel">Carrusel</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                </>
            )}

            {section.type === 'inquiry_form' && (
                <>
                    <TextField
                        label="Telefono"
                        value={text(draft.settings.phone)}
                        onChange={(value) => setSetting('phone', value)}
                    />
                    <TextField
                        label="Email visible"
                        value={text(draft.settings.email)}
                        onChange={(value) => setSetting('email', value)}
                    />
                    <StringList
                        label="Areas de interes"
                        value={arrayValue<string>(
                            draft.settings.interest_areas,
                        )}
                        onChange={(value) =>
                            setSetting('interest_areas', value)
                        }
                    />
                </>
            )}

            <UploadMedia />

            <Button className="w-full" onClick={save} disabled={isSaving}>
                Guardar seccion
            </Button>
        </div>
    );
}

function SectionPreview({
    section,
    active,
    onClick,
}: {
    section: Section;
    active: boolean;
    onClick: () => void;
}) {
    const settings = section.settings;
    const image = (settings.media as CmsMedia) ?? null;

    return (
        <button
            type="button"
            onClick={onClick}
            className={[
                'block w-full border-b border-neutral-200 p-6 text-left transition dark:border-neutral-800',
                active
                    ? 'bg-white ring-2 ring-red-800 dark:bg-neutral-900'
                    : 'bg-transparent hover:bg-white dark:hover:bg-neutral-900',
                !section.is_active ? 'opacity-50' : '',
            ].join(' ')}
        >
            <p className="mb-2 text-xs font-semibold tracking-wide text-red-800 uppercase">
                {TYPE_LABELS[section.type] ?? section.type}
            </p>
            <div className="flex gap-4">
                {image && (
                    <img
                        src={image.url}
                        alt={image.alt ?? ''}
                        className="h-24 w-32 rounded-md object-cover"
                    />
                )}
                <div>
                    <h3 className="text-xl font-bold">
                        {text(settings.title) || 'Sin titulo'}
                    </h3>
                    <p className="mt-2 line-clamp-2 text-sm text-neutral-600 dark:text-neutral-400">
                        {text(settings.subtitle) ||
                            text(settings.text) ||
                            `${arrayValue(settings.items).length} elementos`}
                    </p>
                </div>
            </div>
        </button>
    );
}

function ItemList({
    type,
    media,
    value,
    onChange,
}: {
    type: string;
    media: MediaOption[];
    value: BuilderItem[];
    onChange: (value: BuilderItem[]) => void;
}) {
    const add = () =>
        onChange([
            ...value,
            { title: 'Nuevo item', text: '', icon: 'activity', media_id: null },
        ]);
    const update = (index: number, item: BuilderItem) =>
        onChange(value.map((row, i) => (i === index ? item : row)));
    const remove = (index: number) =>
        onChange(value.filter((_, i) => i !== index));

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between">
                <Label>Cards</Label>
                <Button variant="outline" size="sm" onClick={add}>
                    <Plus className="mr-1 size-4" />
                    Agregar
                </Button>
            </div>
            {value.map((item, index) => (
                <div
                    key={index}
                    className="space-y-2 rounded-md border border-neutral-200 p-3 dark:border-neutral-800"
                >
                    <TextField
                        label="Titulo"
                        value={item.title ?? ''}
                        onChange={(title) => update(index, { ...item, title })}
                    />
                    <TextArea
                        label="Texto"
                        value={item.text ?? ''}
                        onChange={(text) => update(index, { ...item, text })}
                    />
                    <TextField
                        label="Link"
                        value={item.link ?? item.cta_url ?? ''}
                        onChange={(link) =>
                            update(index, { ...item, link, cta_url: link })
                        }
                    />
                    {['feature_cards', 'gallery', 'text_image'].includes(
                        type,
                    ) && (
                        <MediaPicker
                            label="Imagen"
                            media={media}
                            value={item.media_id ?? null}
                            onChange={(media_id) =>
                                update(index, { ...item, media_id })
                            }
                        />
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={item.highlighted === true}
                            onCheckedChange={(checked) =>
                                update(index, {
                                    ...item,
                                    highlighted: checked === true,
                                })
                            }
                        />
                        Destacado
                    </label>
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => remove(index)}
                    >
                        Quitar
                    </Button>
                </div>
            ))}
        </div>
    );
}

function ButtonList({
    value,
    onChange,
}: {
    value: BuilderButton[];
    onChange: (value: BuilderButton[]) => void;
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label>Botones</Label>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        onChange([...value, { label: 'Boton', url: '#' }])
                    }
                >
                    <Plus className="size-4" />
                </Button>
            </div>
            {value.map((button, index) => (
                <div
                    key={index}
                    className="grid gap-2 rounded-md border border-neutral-200 p-2 dark:border-neutral-800"
                >
                    <Input
                        value={button.label ?? ''}
                        onChange={(event) =>
                            onChange(
                                value.map((row, i) =>
                                    i === index
                                        ? { ...row, label: event.target.value }
                                        : row,
                                ),
                            )
                        }
                    />
                    <Input
                        value={button.url ?? ''}
                        onChange={(event) =>
                            onChange(
                                value.map((row, i) =>
                                    i === index
                                        ? { ...row, url: event.target.value }
                                        : row,
                                ),
                            )
                        }
                    />
                </div>
            ))}
        </div>
    );
}

function StringList({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string[];
    onChange: (value: string[]) => void;
}) {
    return (
        <div className="space-y-2">
            <div className="flex items-center justify-between">
                <Label>{label}</Label>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onChange([...value, 'Nuevo'])}
                >
                    <Plus className="size-4" />
                </Button>
            </div>
            {value.map((item, index) => (
                <Input
                    key={index}
                    value={item}
                    onChange={(event) =>
                        onChange(
                            value.map((row, i) =>
                                i === index ? event.target.value : row,
                            ),
                        )
                    }
                />
            ))}
        </div>
    );
}

function MediaPicker({
    label,
    media,
    value,
    onChange,
}: {
    label: string;
    media: MediaOption[];
    value: number | null;
    onChange: (value: number | null) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            <Select
                value={value ? String(value) : 'none'}
                onValueChange={(next) =>
                    onChange(next === 'none' ? null : Number(next))
                }
            >
                <SelectTrigger>
                    <SelectValue placeholder="Selecciona imagen" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">Sin imagen</SelectItem>
                    {media.map((item) => (
                        <SelectItem key={item.id} value={String(item.id)}>
                            {item.label}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
        </div>
    );
}

function UploadMedia() {
    const [file, setFile] = useState<File | null>(null);
    const upload = () => {
        if (!file) {
            return;
        }

        const data = new FormData();
        data.append('files[]', file);
        data.append('visibility', 'public');

        router.post(mediaRoutes.store.url(), data, {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['media'] }),
        });
    };

    return (
        <div className="grid gap-2 rounded-md border border-dashed border-neutral-300 p-3 dark:border-neutral-700">
            <Label>Subir imagen a biblioteca</Label>
            <input
                type="file"
                accept="image/*"
                className="text-sm"
                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
            />
            <Button
                variant="outline"
                size="sm"
                onClick={upload}
                disabled={!file}
            >
                <ImagePlus className="mr-1 size-4" />
                Subir
            </Button>
        </div>
    );
}

function TextField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            <Input
                value={value}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function TextArea({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-1">
            <Label>{label}</Label>
            <textarea
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="min-h-24 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
            />
        </div>
    );
}

function defaultSettings(type: string): SectionSettings {
    if (type === 'specialty_grid') {
        return {
            title: 'Especialidades',
            items: [
                {
                    title: 'Cinesiterapia',
                    text: 'Equipo profesional.',
                    icon: 'activity',
                },
            ],
        };
    }

    if (type === 'feature_cards') {
        return {
            items: [
                {
                    title: 'Servicio tecnico',
                    text: 'Mantenimiento certificado.',
                    cta_label: 'Agendar',
                    cta_url: '#',
                },
            ],
        };
    }

    if (type === 'brand_strip') {
        return {
            eyebrow: 'Marcas',
            title: 'Nuestros aliados',
            brands: ['CHATTANOOGA', 'BTL MEDICAL'],
        };
    }

    if (type === 'inquiry_form') {
        return {
            title: 'Contactanos',
            text: 'Un asesor puede ayudarte.',
            phone: '',
            email: '',
            interest_areas: ['Equipo medico'],
        };
    }

    if (type === 'text_image') {
        return {
            title: 'Titulo de seccion',
            text: 'Contenido de la seccion.',
            media_id: null,
        };
    }

    if (type === 'gallery') {
        return {
            title: 'Galeria',
            items: [{ title: 'Imagen', media_id: null }],
        };
    }

    if (type === 'cta_banner') {
        return {
            title: 'Agenda una llamada',
            text: 'Conoce nuestras soluciones.',
            buttons: [{ label: 'Contactar', url: '/contacto' }],
        };
    }

    if (type === 'featured_products') {
        return {
            title: 'Productos destacados',
            text: 'Seleccion automatica de productos.',
            product_ids: [],
            display_type: 'grid',
        };
    }

    return {
        eyebrow: 'Campana',
        title: 'Hot Days 2024',
        subtitle: 'Maximiza tu capacidad clinica con precios especiales.',
        media_id: null,
        buttons: [{ label: 'Ver ofertas', url: '#' }],
    };
}

function sectionPayload(section: {
    store_id: number;
    type: string;
    is_active: boolean;
    settings: SectionSettings;
}): Record<string, FormDataConvertible> {
    return {
        store_id: section.store_id,
        type: section.type,
        is_active: section.is_active,
        settings: section.settings,
    };
}

function stripResolvedMedia(settings: SectionSettings): SectionSettings {
    const clone = JSON.parse(JSON.stringify(settings)) as SectionSettings;
    delete clone.media;
    delete clone.products;

    if (Array.isArray(clone.items)) {
        clone.items = clone.items.map((item) => {
            const next = { ...item };
            delete next.media;
            return next;
        });
    }

    return clone;
}

function usesMainImage(type: string): boolean {
    return ['hero', 'text_image'].includes(type);
}

function usesItems(type: string): boolean {
    return ['specialty_grid', 'feature_cards', 'gallery'].includes(type);
}

function text(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function numberValue(value: unknown): number | null {
    return typeof value === 'number' ? value : null;
}

function arrayValue<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
