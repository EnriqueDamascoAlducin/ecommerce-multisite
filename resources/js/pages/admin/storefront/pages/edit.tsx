import { Head, Link, router, useForm } from '@inertiajs/react';
import type { FormDataConvertible } from '@inertiajs/core';
import {
    Activity,
    ArrowDown,
    ArrowLeft,
    ArrowUp,
    BadgeCheck,
    Eye,
    Grid3X3,
    ImageIcon,
    Layers3,
    Mail,
    MessageSquareText,
    Palette,
    PanelLeft,
    Plus,
    Save,
    ShoppingBag,
    Sparkles,
    Trash2,
    Upload,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useRef, useState, type ChangeEvent, type CSSProperties } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import mediaRoutes from '@/routes/admin/media';
import storefrontPages from '@/routes/admin/storefront/pages';

type MediaOption = { id: number; label: string; url: string };
type ProductOption = { id: number; label: string; sku: string };
type CmsMedia = { id: number; url: string; alt: string | null } | null;
type SectionSettings = Record<string, FormDataConvertible> & {
    media?: CmsMedia;
    slides?: HeroSlide[];
    items?: BuilderItem[];
    buttons?: BuilderButton[];
    brands?: string[];
    interest_areas?: string[];
    product_ids?: number[];
    display_type?: string;
    products?: ProductOption[];
};
type HeroSlide = {
    media_id?: number | null;
    media?: CmsMedia;
    eyebrow?: string;
    title?: string;
    subtitle?: string;
    buttons?: BuilderButton[];
};
type Section = {
    id: number;
    type: string;
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
    wide?: boolean;
    media_id?: number | null;
    media?: CmsMedia;
    cta_label?: string;
    cta_url?: string;
};
type BuilderButton = { label?: string; url?: string };

const TYPE_ORDER = [
    'hero',
    'specialty_grid',
    'feature_cards',
    'brand_strip',
    'inquiry_form',
] as const;
const FIXED_SECTION_TYPES = [...TYPE_ORDER] as string[];
const EXTRA_SECTION_TYPES = ['recommended_products', 'image_banner'] as const;

const SECTION_META: Record<
    string,
    { label: string; description: string; icon: LucideIcon }
> = {
    hero: {
        label: 'Hero',
        description: 'Portada, mensaje principal y botones',
        icon: ImageIcon,
    },
    specialty_grid: {
        label: 'Especialidades',
        description: 'Grid de servicios y áreas clínicas',
        icon: Grid3X3,
    },
    feature_cards: {
        label: 'Servicios / Educación',
        description: 'Cards grandes con imagen y CTA',
        icon: Layers3,
    },
    brand_strip: {
        label: 'Marcas',
        description: 'Aliados y marcas principales',
        icon: BadgeCheck,
    },
    inquiry_form: {
        label: 'Formulario contacto',
        description: 'Texto, datos de contacto y áreas',
        icon: MessageSquareText,
    },
    recommended_products: {
        label: 'Productos recomendados',
        description: 'Grid manual de productos destacados',
        icon: ShoppingBag,
    },
    image_banner: {
        label: 'Banner imagen',
        description: 'Bloque visual con imagen y CTA',
        icon: ImageIcon,
    },
};

export default function StorefrontPageEdit({
    currentStoreId,
    page,
    media,
    products,
    publicUrl,
    isHome,
}: {
    stores: { id: number; label: string }[];
    currentStoreId: number;
    page: Page;
    media: MediaOption[];
    products: ProductOption[];
    publicUrl: string;
    isHome: boolean;
}) {
    const pageForm = useForm({
        store_id: currentStoreId,
        title: page.title,
        slug: page.slug,
        is_published: page.is_published,
    });
    const [sections, setSections] = useState<Section[]>(
        sortSections(page.sections).map((section) => ({
            ...section,
            settings: stripResolvedMedia(section.settings),
        })),
    );
    const [activeSectionId, setActiveSectionId] = useState<number | null>(
        () => sortSections(page.sections)[0]?.id ?? null,
    );
    const [saving, setSaving] = useState(false);

    const activeSection =
        sections.find((section) => section.id === activeSectionId) ??
        sections[0] ??
        null;

    const updateSectionSettings = (id: number, settings: SectionSettings) => {
        setSections((current) =>
            current.map((section) =>
                section.id === id ? { ...section, settings } : section,
            ),
        );
    };

    const moveSection = (id: number, direction: -1 | 1) => {
        setSections((current) => {
            const index = current.findIndex((section) => section.id === id);

            return index === -1 ? current : moveItem(current, index, direction);
        });
    };

    const addSection = (type: (typeof EXTRA_SECTION_TYPES)[number]) => {
        const section = newExtraSection(type);

        setSections((current) => [...current, section]);
        setActiveSectionId(section.id);
    };

    const removeSection = (id: number) => {
        const nextSections = sections.filter((section) => section.id !== id);

        setSections(nextSections);

        if (activeSectionId === id) {
            setActiveSectionId(nextSections[0]?.id ?? null);
        }
    };

    const savePage = () => {
        setSaving(true);
        const payload = {
            store_id: pageForm.data.store_id,
            title: pageForm.data.title,
            is_published: pageForm.data.is_published,
            ...(isHome ? {} : { slug: pageForm.data.slug }),
            sections: sections.map((section) => ({
                ...(section.id > 0
                    ? { id: section.id }
                    : { type: section.type }),
                settings: stripResolvedMedia(section.settings),
            })),
        };

        router.put(storefrontPages.update.url(page.id), payload, {
            preserveScroll: true,
            onFinish: () => setSaving(false),
        });
    };

    return (
        <>
            <Head title={`Editar ${page.title}`} />

            <EditorShell
                pageTitle={page.title}
                publicUrl={publicUrl}
                backUrl={storefrontPages.index.url({
                    query: { store_id: currentStoreId },
                })}
                isHome={isHome}
                title={pageForm.data.title}
                slug={pageForm.data.slug}
                isPublished={pageForm.data.is_published}
                saving={saving}
                onTitleChange={(title) => pageForm.setData('title', title)}
                onSlugChange={(slug) => pageForm.setData('slug', slugify(slug))}
                onPublishedChange={(published) =>
                    pageForm.setData('is_published', published)
                }
                onSave={savePage}
            >
                {sections.length > 0 && activeSection ? (
                    <div className="grid gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
                        <SectionSidebar
                            sections={sections}
                            activeSectionId={activeSection.id}
                            onSelect={setActiveSectionId}
                            onMoveSection={moveSection}
                            onAddSection={addSection}
                            onRemoveSection={removeSection}
                        />
                        <SectionPanel
                            section={activeSection}
                            media={media}
                            products={products}
                            onSettingsChange={(settings) =>
                                updateSectionSettings(
                                    activeSection.id,
                                    settings,
                                )
                            }
                        />
                    </div>
                ) : (
                    <Card className="rounded-lg">
                        <CardContent className="py-10 text-center">
                            <PanelLeft className="mx-auto size-8 text-neutral-400" />
                            <p className="mt-3 text-sm text-neutral-500">
                                Esta página no tiene secciones de template.
                            </p>
                        </CardContent>
                    </Card>
                )}
            </EditorShell>

            <div className="fixed right-4 bottom-4 z-40 rounded-lg border border-neutral-200 bg-white/95 p-2 shadow-xl backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/95">
                <Button onClick={savePage} disabled={saving} size="sm">
                    <Save className="size-4" />
                    {saving ? 'Guardando...' : 'Guardar'}
                </Button>
            </div>
        </>
    );
}

function EditorShell({
    pageTitle,
    publicUrl,
    backUrl,
    isHome,
    title,
    slug,
    isPublished,
    saving,
    children,
    onTitleChange,
    onSlugChange,
    onPublishedChange,
    onSave,
}: {
    pageTitle: string;
    publicUrl: string;
    backUrl: string;
    isHome: boolean;
    title: string;
    slug: string;
    isPublished: boolean;
    saving: boolean;
    children: React.ReactNode;
    onTitleChange: (title: string) => void;
    onSlugChange: (slug: string) => void;
    onPublishedChange: (published: boolean) => void;
    onSave: () => void;
}) {
    return (
        <div className="space-y-5 pb-20">
            <div className="sticky top-0 z-30 -mx-4 border-b border-neutral-200 bg-neutral-50/95 px-4 py-4 backdrop-blur dark:border-neutral-800 dark:bg-neutral-950/95">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <Button asChild variant="outline" size="sm">
                            <Link href={backUrl}>
                                <ArrowLeft className="size-4" />
                            </Link>
                        </Button>
                        <div className="min-w-0">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="truncate text-2xl font-semibold">
                                    {pageTitle}
                                </h1>
                                <Badge
                                    variant={isPublished ? 'default' : 'outline'}
                                    className={
                                        isPublished
                                            ? 'bg-emerald-600 text-white'
                                            : ''
                                    }
                                >
                                    {isPublished ? 'Publicada' : 'Borrador'}
                                </Badge>
                            </div>
                            <p className="truncate text-sm text-neutral-500">
                                {publicUrl}
                            </p>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline" size="sm">
                            <a href={publicUrl} target="_blank" rel="noreferrer">
                                <Eye className="size-4" />
                                Vista
                            </a>
                        </Button>
                        <Button onClick={onSave} disabled={saving} size="sm">
                            <Save className="size-4" />
                            {saving ? 'Guardando...' : 'Guardar página'}
                        </Button>
                    </div>
                </div>
            </div>

            <Card className="rounded-lg">
                <CardHeader className="gap-1">
                    <CardTitle>Información de página</CardTitle>
                    <CardDescription>
                        Datos generales visibles en el admin y publicación.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="grid gap-4 md:grid-cols-[1fr_1fr_auto] md:items-end">
                        <TextField
                            label="Título"
                            value={title}
                            onChange={onTitleChange}
                        />
                        <TextField
                            label="Slug"
                            value={slug}
                            disabled={isHome}
                            onChange={onSlugChange}
                        />
                        <label className="flex h-10 items-center gap-2 rounded-md border border-neutral-200 px-3 text-sm dark:border-neutral-800">
                            <Checkbox
                                checked={isPublished}
                                onCheckedChange={(value) =>
                                    onPublishedChange(value === true)
                                }
                            />
                            Publicada
                        </label>
                    </div>
                </CardContent>
            </Card>

            {children}
        </div>
    );
}

function SectionSidebar({
    sections,
    activeSectionId,
    onSelect,
    onMoveSection,
    onAddSection,
    onRemoveSection,
}: {
    sections: Section[];
    activeSectionId: number;
    onSelect: (id: number) => void;
    onMoveSection: (id: number, direction: -1 | 1) => void;
    onAddSection: (type: (typeof EXTRA_SECTION_TYPES)[number]) => void;
    onRemoveSection: (id: number) => void;
}) {
    const [newSectionType, setNewSectionType] =
        useState<(typeof EXTRA_SECTION_TYPES)[number]>('image_banner');

    return (
        <aside className="lg:sticky lg:top-32 lg:self-start">
            <Card className="rounded-lg border-neutral-200 bg-white py-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <CardHeader className="px-4">
                    <CardTitle className="flex items-center gap-2 text-sm">
                        <PanelLeft className="size-4 text-red-700 dark:text-red-400" />
                        Secciones del home
                    </CardTitle>
                    <CardDescription>
                        Selecciona una sección para editar su contenido.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-3">
                    <div className="mb-3 rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-2 dark:border-neutral-700 dark:bg-neutral-950">
                        <Label className="text-xs">Agregar bloque</Label>
                        <div className="mt-2 flex gap-2">
                            <select
                                value={newSectionType}
                                onChange={(event) =>
                                    setNewSectionType(
                                        event.target
                                            .value as (typeof EXTRA_SECTION_TYPES)[number],
                                    )
                                }
                                className="h-9 min-w-0 flex-1 rounded-md border border-neutral-300 bg-white px-2 text-xs outline-none transition focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900"
                            >
                                <option value="image_banner">
                                    Banner imagen
                                </option>
                                <option value="recommended_products">
                                    Productos recomendados
                                </option>
                            </select>
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                onClick={() => onAddSection(newSectionType)}
                                title="Agregar bloque"
                            >
                                <Plus className="size-4" />
                            </Button>
                        </div>
                    </div>
                    <div className="flex gap-2 overflow-x-auto pb-1 lg:block lg:space-y-2 lg:overflow-visible lg:pb-0">
                        {sections.map((section, index) => (
                            <div
                                key={section.id}
                                className="flex min-w-80 gap-2 lg:min-w-0"
                            >
                                <SectionNavItem
                                    section={section}
                                    index={index}
                                    active={section.id === activeSectionId}
                                    onSelect={() => onSelect(section.id)}
                                />
                                <div className="grid w-9 shrink-0 gap-1">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            onMoveSection(section.id, -1)
                                        }
                                        disabled={index === 0}
                                        title="Subir sección"
                                        className="h-auto min-h-0 px-0"
                                    >
                                        <ArrowUp className="size-4" />
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            onMoveSection(section.id, 1)
                                        }
                                        disabled={index === sections.length - 1}
                                        title="Bajar sección"
                                        className="h-auto min-h-0 px-0"
                                    >
                                        <ArrowDown className="size-4" />
                                    </Button>
                                    {isExtraSection(section.type) && (
                                        <Button
                                            variant="destructive"
                                            size="sm"
                                            onClick={() =>
                                                onRemoveSection(section.id)
                                            }
                                            title="Eliminar bloque"
                                            className="h-auto min-h-0 px-0"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                </CardContent>
            </Card>
        </aside>
    );
}

function SectionNavItem({
    section,
    index,
    active,
    onSelect,
}: {
    section: Section;
    index: number;
    active: boolean;
    onSelect: () => void;
}) {
    const meta = sectionMeta(section.type);
    const Icon = meta.icon;

    return (
        <button
            type="button"
            onClick={onSelect}
            className={cn(
                'w-full rounded-lg border p-3 text-left transition',
                active
                    ? 'border-red-200 bg-red-50 text-neutral-950 shadow-sm ring-1 ring-red-100 dark:border-red-900/70 dark:bg-red-950/20 dark:text-neutral-50 dark:ring-red-900/30'
                    : 'border-neutral-200 bg-white text-neutral-900 hover:border-neutral-300 hover:bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900 dark:text-neutral-100 dark:hover:border-neutral-700 dark:hover:bg-neutral-800/70',
            )}
        >
            <div className="flex items-start gap-3">
                <span
                    className={cn(
                        'flex size-9 shrink-0 items-center justify-center rounded-md',
                        active
                            ? 'bg-red-700 text-white dark:bg-red-500 dark:text-white'
                            : 'bg-neutral-100 text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300',
                    )}
                >
                    <Icon className="size-4" />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="flex items-center justify-between gap-2">
                        <span className="text-sm font-semibold">
                            {meta.label}
                        </span>
                        <span className="flex flex-wrap justify-end gap-1">
                            <Badge
                                variant="outline"
                                className={cn(
                                    'text-[10px]',
                                    active
                                        ? 'border-red-200 bg-white/70 text-red-800 dark:border-red-800 dark:bg-red-950/40 dark:text-red-200'
                                        : '',
                                )}
                            >
                                {index + 1}
                            </Badge>
                            <Badge
                                variant={
                                    isFixedSection(section.type)
                                        ? 'secondary'
                                        : 'outline'
                                }
                                className="text-[10px]"
                            >
                                {isFixedSection(section.type) ? 'Fijo' : 'Extra'}
                            </Badge>
                        </span>
                    </span>
                    <span className="mt-1 block line-clamp-2 text-xs text-neutral-500 dark:text-neutral-400">
                        {sectionSummary(section)}
                    </span>
                </span>
            </div>
        </button>
    );
}

function SectionPanel({
    section,
    media,
    products,
    onSettingsChange,
}: {
    section: Section;
    media: MediaOption[];
    products: ProductOption[];
    onSettingsChange: (settings: SectionSettings) => void;
}) {
    const meta = sectionMeta(section.type);
    const Icon = meta.icon;
    const setSetting = (key: string, value: FormDataConvertible) => {
        onSettingsChange({ ...section.settings, [key]: value });
    };

    return (
        <div className="space-y-5">
            <Card className="overflow-hidden rounded-lg py-0">
                <div className="border-b border-neutral-200 bg-white p-5 dark:border-neutral-800 dark:bg-neutral-950">
                    <div className="flex flex-wrap items-start justify-between gap-3">
                        <div className="flex items-start gap-3">
                            <span className="flex size-11 items-center justify-center rounded-lg bg-red-50 text-red-800 dark:bg-red-950/30 dark:text-red-200">
                                <Icon className="size-5" />
                            </span>
                            <div>
                                <h2 className="text-xl font-semibold">
                                    {meta.label}
                                </h2>
                                <p className="mt-1 text-sm text-neutral-500">
                                    {meta.description}
                                </p>
                            </div>
                        </div>
                        <Badge variant="outline">{section.type}</Badge>
                    </div>
                </div>
                <CardContent className="grid gap-5 p-5 xl:grid-cols-[minmax(0,1fr)_22rem]">
                    <div className="space-y-5">
                        <FieldGroup
                            title="Apariencia"
                            description="Color de banda y ancho interno usado por esta sección."
                            icon={Palette}
                        >
                            <div className="grid gap-4 md:grid-cols-2">
                                <ColorField
                                    label="Color de fondo"
                                    value={text(
                                        section.settings.background_color,
                                    )}
                                    onChange={(value) =>
                                        setSetting('background_color', value)
                                    }
                                />
                                <SelectField
                                    label="Ancho del contenido"
                                    value={contentWidthValue(
                                        section.settings.content_width,
                                    )}
                                    options={[
                                        {
                                            value: 'container',
                                            label: 'Contenedor',
                                        },
                                        {
                                            value: 'full',
                                            label: 'Ancho completo',
                                        },
                                    ]}
                                    onChange={(value) =>
                                        setSetting('content_width', value)
                                    }
                                />
                            </div>
                        </FieldGroup>

                        {section.type === 'hero' && (
                            <HeroFields
                                settings={section.settings}
                                media={media}
                                setSetting={setSetting}
                            />
                        )}
                        {section.type === 'specialty_grid' && (
                            <SpecialtyGridFields
                                settings={section.settings}
                                media={media}
                                setSetting={setSetting}
                            />
                        )}
                        {section.type === 'feature_cards' && (
                            <FeatureCardsFields
                                settings={section.settings}
                                media={media}
                                setSetting={setSetting}
                            />
                        )}
                        {section.type === 'brand_strip' && (
                            <BrandStripFields
                                settings={section.settings}
                                setSetting={setSetting}
                            />
                        )}
                        {section.type === 'inquiry_form' && (
                            <InquiryFormFields
                                settings={section.settings}
                                media={media}
                                setSetting={setSetting}
                            />
                        )}
                        {section.type === 'image_banner' && (
                            <ImageBannerFields
                                settings={section.settings}
                                media={media}
                                setSetting={setSetting}
                            />
                        )}
                        {section.type === 'recommended_products' && (
                            <RecommendedProductsFields
                                settings={section.settings}
                                products={products}
                                setSetting={setSetting}
                            />
                        )}
                    </div>

                    <MiniPreview section={section} />
                </CardContent>
            </Card>
        </div>
    );
}

function FieldGroup({
    title,
    description,
    icon: Icon = Sparkles,
    children,
}: {
    title: string;
    description?: string;
    icon?: LucideIcon;
    children: React.ReactNode;
}) {
    return (
        <section className="rounded-lg border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-950">
            <div className="mb-4 flex items-start gap-3">
                <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                    <Icon className="size-4" />
                </span>
                <div>
                    <h3 className="text-sm font-semibold">{title}</h3>
                    {description && (
                        <p className="mt-1 text-xs leading-5 text-neutral-500">
                            {description}
                        </p>
                    )}
                </div>
            </div>
            <div className="space-y-4">{children}</div>
        </section>
    );
}

function HeroFields({
    settings,
    media,
    setSetting,
}: {
    settings: SectionSettings;
    media: MediaOption[];
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <>
            <FieldGroup
                title="Contenido principal"
                description="Texto visible sobre la imagen o fondo del hero."
                icon={ImageIcon}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Etiqueta"
                        value={text(settings.eyebrow)}
                        onChange={(value) => setSetting('eyebrow', value)}
                    />
                    <TextField
                        label="Título"
                        value={text(settings.title)}
                        onChange={(value) => setSetting('title', value)}
                    />
                    <div className="md:col-span-2">
                        <TextArea
                            label="Subtítulo"
                            value={text(settings.subtitle)}
                            onChange={(value) => setSetting('subtitle', value)}
                        />
                    </div>
                </div>
            </FieldGroup>
            <FieldGroup
                title="Imagen y acciones"
                description="Fallback para homes antiguos sin slides configurados."
                icon={Upload}
            >
                <MediaPicker
                    label="Imagen de fondo fallback"
                    media={media}
                    value={numberValue(settings.media_id)}
                    onChange={(value) => setSetting('media_id', value)}
                />
                <ButtonList
                    value={arrayValue<BuilderButton>(settings.buttons)}
                    onChange={(value) => setSetting('buttons', value)}
                />
            </FieldGroup>
            <FieldGroup
                title="Slides del hero"
                description="Hasta 5 campañas con imagen, texto y botones propios. Con 1 slide se muestra fijo; con 2 o más se activa carrusel."
                icon={Layers3}
            >
                <HeroSlidesList
                    media={media}
                    fallbackSettings={settings}
                    value={arrayValue<HeroSlide>(settings.slides)}
                    onChange={(value) => setSetting('slides', value)}
                />
            </FieldGroup>
        </>
    );
}

function SpecialtyGridFields({
    settings,
    media,
    setSetting,
}: {
    settings: SectionSettings;
    media: MediaOption[];
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <>
            <FieldGroup
                title="Encabezado"
                description="Título del bloque de especialidades."
                icon={Activity}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Etiqueta"
                        value={text(settings.eyebrow)}
                        onChange={(value) => setSetting('eyebrow', value)}
                    />
                    <TextField
                        label="Título"
                        value={text(settings.title)}
                        onChange={(value) => setSetting('title', value)}
                    />
                </div>
            </FieldGroup>
            <FieldGroup
                title="Cards de especialidades"
                description="Puedes marcar una card como destacada o larga."
                icon={Grid3X3}
            >
                <ItemList
                    type="specialty_grid"
                    media={media}
                    value={arrayValue<BuilderItem>(settings.items)}
                    onChange={(value) => setSetting('items', value)}
                />
            </FieldGroup>
        </>
    );
}

function FeatureCardsFields({
    settings,
    media,
    setSetting,
}: {
    settings: SectionSettings;
    media: MediaOption[];
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <FieldGroup
            title="Cards de servicios"
            description="Bloques grandes con imagen, texto y enlace."
            icon={Layers3}
        >
            <ItemList
                type="feature_cards"
                media={media}
                value={arrayValue<BuilderItem>(settings.items)}
                onChange={(value) => setSetting('items', value)}
            />
        </FieldGroup>
    );
}

function BrandStripFields({
    settings,
    setSetting,
}: {
    settings: SectionSettings;
    media?: MediaOption[];
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <>
            <FieldGroup
                title="Encabezado"
                description="Texto superior de la franja de marcas."
                icon={BadgeCheck}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Etiqueta"
                        value={text(settings.eyebrow)}
                        onChange={(value) => setSetting('eyebrow', value)}
                    />
                    <TextField
                        label="Título"
                        value={text(settings.title)}
                        onChange={(value) => setSetting('title', value)}
                    />
                </div>
            </FieldGroup>
            <FieldGroup
                title="Marcas"
                description="Lista de nombres visibles como chips."
                icon={Sparkles}
            >
                <StringList
                    label="Marcas"
                    value={arrayValue<string>(settings.brands)}
                    onChange={(value) => setSetting('brands', value)}
                />
            </FieldGroup>
        </>
    );
}

function InquiryFormFields({
    settings,
    media,
    setSetting,
}: {
    settings: SectionSettings;
    media: MediaOption[];
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <>
            <FieldGroup
                title="Contenido"
                description="Texto y datos que acompañan el formulario."
                icon={Mail}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Título"
                        value={text(settings.title)}
                        onChange={(value) => setSetting('title', value)}
                    />
                    <TextField
                        label="Teléfono"
                        value={text(settings.phone)}
                        onChange={(value) => setSetting('phone', value)}
                    />
                    <TextField
                        label="Email"
                        value={text(settings.email)}
                        onChange={(value) => setSetting('email', value)}
                    />
                    <MediaPicker
                        label="Imagen decorativa"
                        media={media}
                        value={numberValue(settings.media_id)}
                        onChange={(value) => setSetting('media_id', value)}
                    />
                    <div className="md:col-span-2">
                        <TextArea
                            label="Texto"
                            value={text(settings.text)}
                            onChange={(value) => setSetting('text', value)}
                        />
                    </div>
                </div>
            </FieldGroup>
            <FieldGroup
                title="Áreas de interés"
                description="Opciones disponibles en el selector del formulario."
                icon={MessageSquareText}
            >
                <StringList
                    label="Áreas"
                    value={arrayValue<string>(settings.interest_areas)}
                    onChange={(value) => setSetting('interest_areas', value)}
                />
            </FieldGroup>
        </>
    );
}

function ImageBannerFields({
    settings,
    media,
    setSetting,
}: {
    settings: SectionSettings;
    media: MediaOption[];
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <>
            <FieldGroup
                title="Contenido"
                description="Texto principal visible dentro del banner."
                icon={ImageIcon}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Etiqueta"
                        value={text(settings.eyebrow)}
                        onChange={(value) => setSetting('eyebrow', value)}
                    />
                    <TextField
                        label="Titulo"
                        value={text(settings.title)}
                        onChange={(value) => setSetting('title', value)}
                    />
                    <div className="md:col-span-2">
                        <TextArea
                            label="Texto"
                            value={text(settings.text)}
                            onChange={(value) => setSetting('text', value)}
                        />
                    </div>
                </div>
            </FieldGroup>
            <FieldGroup
                title="Imagen y accion"
                description="Imagen, posicion visual y boton opcional."
                icon={Upload}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <div className="md:col-span-2">
                        <MediaPicker
                            label="Imagen"
                            media={media}
                            value={numberValue(settings.media_id)}
                            onChange={(value) => setSetting('media_id', value)}
                        />
                    </div>
                    <SelectField
                        label="Posicion de imagen"
                        value={imagePositionValue(settings.image_position)}
                        options={[
                            { value: 'right', label: 'Derecha' },
                            { value: 'left', label: 'Izquierda' },
                            { value: 'background', label: 'Fondo' },
                        ]}
                        onChange={(value) => setSetting('image_position', value)}
                    />
                    <TextField
                        label="Label boton"
                        value={text(settings.button_label)}
                        onChange={(value) => setSetting('button_label', value)}
                    />
                    <TextField
                        label="URL boton"
                        value={text(settings.button_url)}
                        onChange={(value) => setSetting('button_url', value)}
                    />
                </div>
            </FieldGroup>
        </>
    );
}

function RecommendedProductsFields({
    settings,
    products,
    setSetting,
}: {
    settings: SectionSettings;
    products: ProductOption[];
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <>
            <FieldGroup
                title="Encabezado"
                description="Texto que aparece arriba del grid de productos."
                icon={ShoppingBag}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Etiqueta"
                        value={text(settings.eyebrow)}
                        onChange={(value) => setSetting('eyebrow', value)}
                    />
                    <TextField
                        label="Titulo"
                        value={text(settings.title)}
                        onChange={(value) => setSetting('title', value)}
                    />
                    <div className="md:col-span-2">
                        <TextArea
                            label="Subtitulo"
                            value={text(settings.subtitle)}
                            onChange={(value) => setSetting('subtitle', value)}
                        />
                    </div>
                </div>
            </FieldGroup>
            <FieldGroup
                title="Productos"
                description="Seleccion manual, maximo 12 productos activos de esta tienda."
                icon={ShoppingBag}
            >
                <ProductList
                    products={products}
                    value={arrayValue<number>(settings.product_ids)}
                    onChange={(value) => setSetting('product_ids', value)}
                />
            </FieldGroup>
            <FieldGroup
                title="Layout"
                description="Elige como se muestran los productos en el front."
                icon={Grid3X3}
            >
                <div className="grid gap-4 md:grid-cols-2">
                    <SelectField
                        label="Vista"
                        value={displayTypeValue(settings.display_type)}
                        options={[
                            { value: 'grid', label: 'Grid' },
                            { value: 'carousel', label: 'Carrusel' },
                        ]}
                        onChange={(value) => setSetting('display_type', value)}
                    />
                    <SelectField
                        label="Columnas"
                        value={columnsValue(settings.columns)}
                        options={[
                            { value: '4', label: '4 columnas' },
                            { value: '3', label: '3 columnas' },
                        ]}
                        onChange={(value) =>
                            setSetting('columns', Number(value))
                        }
                    />
                </div>
            </FieldGroup>
        </>
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
            {
                title: 'Nuevo item',
                text: '',
                icon: 'activity',
                highlighted: false,
                wide: false,
                media_id: null,
            },
        ]);
    const update = (index: number, item: BuilderItem) =>
        onChange(value.map((row, i) => (i === index ? item : row)));
    const remove = (index: number) =>
        onChange(value.filter((_, i) => i !== index));
    const move = (index: number, direction: -1 | 1) =>
        onChange(moveItem(value, index, direction));

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <Label>{type === 'feature_cards' ? 'Cards' : 'Items'}</Label>
                    <p className="mt-1 text-xs text-neutral-500">
                        {value.length} elemento{value.length === 1 ? '' : 's'}
                    </p>
                </div>
                <Button variant="outline" size="sm" onClick={add}>
                    <Plus className="size-4" />
                    Agregar
                </Button>
            </div>
            {value.map((item, index) => (
                <ListCard
                    key={index}
                    title={item.title || `Item ${index + 1}`}
                    index={index}
                    total={value.length}
                    badges={[
                        item.highlighted ? 'Destacado' : null,
                        item.wide ? 'Larga' : null,
                        item.media_id ? 'Imagen' : null,
                    ]}
                    onMoveUp={() => move(index, -1)}
                    onMoveDown={() => move(index, 1)}
                    onRemove={() => remove(index)}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <TextField
                            label="Título"
                            value={item.title ?? ''}
                            onChange={(title) =>
                                update(index, { ...item, title })
                            }
                        />
                        <TextField
                            label="Link"
                            value={item.link ?? item.cta_url ?? ''}
                            onChange={(link) =>
                                update(index, { ...item, link, cta_url: link })
                            }
                        />
                        <div className="md:col-span-2">
                            <TextArea
                                label="Texto"
                                value={item.text ?? ''}
                                onChange={(text) =>
                                    update(index, { ...item, text })
                                }
                            />
                        </div>
                        {type === 'feature_cards' && (
                            <>
                                <TextField
                                    label="Texto del botón"
                                    value={item.cta_label ?? ''}
                                    onChange={(cta_label) =>
                                        update(index, { ...item, cta_label })
                                    }
                                />
                                <TextField
                                    label="URL del botón"
                                    value={item.cta_url ?? ''}
                                    onChange={(cta_url) =>
                                        update(index, { ...item, cta_url })
                                    }
                                />
                            </>
                        )}
                        {['feature_cards', 'specialty_grid'].includes(type) && (
                            <div className="md:col-span-2">
                                <MediaPicker
                                    label="Imagen"
                                    media={media}
                                    value={item.media_id ?? null}
                                    onChange={(media_id) =>
                                        update(index, { ...item, media_id })
                                    }
                                />
                            </div>
                        )}
                        {type === 'specialty_grid' && (
                            <div className="flex flex-wrap gap-4 md:col-span-2">
                                <label className="flex items-center gap-2 rounded-md border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-800">
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
                                <label className="flex items-center gap-2 rounded-md border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-800">
                                    <Checkbox
                                        checked={item.wide === true}
                                        onCheckedChange={(checked) =>
                                            update(index, {
                                                ...item,
                                                wide: checked === true,
                                            })
                                        }
                                    />
                                    Card larga
                                </label>
                            </div>
                        )}
                    </div>
                </ListCard>
            ))}
        </div>
    );
}

function ListCard({
    title,
    index,
    total,
    badges,
    children,
    onMoveUp,
    onMoveDown,
    onRemove,
}: {
    title: string;
    index: number;
    total: number;
    badges: (string | null)[];
    children: React.ReactNode;
    onMoveUp: () => void;
    onMoveDown: () => void;
    onRemove: () => void;
}) {
    return (
        <div className="rounded-lg border border-neutral-200 bg-neutral-50/60 p-3 dark:border-neutral-800 dark:bg-neutral-900/40">
            <div className="mb-3 flex flex-wrap items-center justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="outline">{index + 1}</Badge>
                        <h4 className="truncate text-sm font-semibold">
                            {title}
                        </h4>
                        {badges.filter(Boolean).map((badge) => (
                            <Badge
                                key={badge}
                                variant="secondary"
                                className="text-[10px]"
                            >
                                {badge}
                            </Badge>
                        ))}
                    </div>
                </div>
                <div className="flex gap-1">
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onMoveUp}
                        disabled={index === 0}
                        title="Subir"
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={onMoveDown}
                        disabled={index === total - 1}
                        title="Bajar"
                    >
                        <ArrowDown className="size-4" />
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={onRemove}
                        title="Eliminar"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            </div>
            {children}
        </div>
    );
}

function HeroSlidesList({
    media,
    fallbackSettings,
    value,
    onChange,
}: {
    media: MediaOption[];
    fallbackSettings: SectionSettings;
    value: HeroSlide[];
    onChange: (value: HeroSlide[]) => void;
}) {
    const slides = value.slice(0, 5);
    const add = () => {
        if (slides.length >= 5) {
            return;
        }

        onChange([
            ...slides,
            {
                media_id: null,
                eyebrow: text(fallbackSettings.eyebrow),
                title: text(fallbackSettings.title) || 'Nuevo slide',
                subtitle: text(fallbackSettings.subtitle),
                buttons: arrayValue<BuilderButton>(fallbackSettings.buttons).slice(0, 2),
            },
        ]);
    };
    const update = (index: number, slide: HeroSlide) =>
        onChange(slides.map((row, i) => (i === index ? slide : row)));
    const remove = (index: number) =>
        onChange(slides.filter((_, i) => i !== index));
    const move = (index: number, direction: -1 | 1) =>
        onChange(moveItem(slides, index, direction));

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <Label>Slides</Label>
                    <p className="mt-1 text-xs text-neutral-500">
                        {slides.length === 0
                            ? 'Sin slides: se usa el contenido fallback.'
                            : slides.length === 1
                              ? '1 slide: se mostrara como hero fijo.'
                              : `${slides.length} slides: se mostraran como carrusel.`}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={slides.length >= 5}
                    onClick={add}
                >
                    <Plus className="size-4" />
                    Agregar slide
                </Button>
            </div>

            {slides.map((slide, index) => (
                <ListCard
                    key={index}
                    title={slide.title || `Slide ${index + 1}`}
                    index={index}
                    total={slides.length}
                    badges={[
                        index === 0 ? 'Inicial' : null,
                        slide.media_id ? 'Imagen' : null,
                    ]}
                    onMoveUp={() => move(index, -1)}
                    onMoveDown={() => move(index, 1)}
                    onRemove={() => remove(index)}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <div className="md:col-span-2">
                            <MediaPicker
                                label="Imagen del slide"
                                media={media}
                                value={slide.media_id ?? null}
                                onChange={(media_id) =>
                                    update(index, { ...slide, media_id })
                                }
                            />
                        </div>
                        <TextField
                            label="Etiqueta"
                            value={slide.eyebrow ?? ''}
                            onChange={(eyebrow) =>
                                update(index, { ...slide, eyebrow })
                            }
                        />
                        <TextField
                            label="Titulo"
                            value={slide.title ?? ''}
                            onChange={(title) =>
                                update(index, { ...slide, title })
                            }
                        />
                        <div className="md:col-span-2">
                            <TextArea
                                label="Subtitulo"
                                value={slide.subtitle ?? ''}
                                onChange={(subtitle) =>
                                    update(index, { ...slide, subtitle })
                                }
                            />
                        </div>
                        <div className="md:col-span-2">
                            <ButtonList
                                value={arrayValue<BuilderButton>(slide.buttons)}
                                maxItems={2}
                                description="Hasta 2 acciones para este slide."
                                onChange={(buttons) =>
                                    update(index, { ...slide, buttons })
                                }
                            />
                        </div>
                    </div>
                </ListCard>
            ))}
        </div>
    );
}

function ButtonList({
    value,
    onChange,
    maxItems,
    description = 'Acciones visibles en el hero.',
}: {
    value: BuilderButton[];
    onChange: (value: BuilderButton[]) => void;
    maxItems?: number;
    description?: string;
}) {
    const canAdd = maxItems === undefined || value.length < maxItems;
    const update = (index: number, button: BuilderButton) =>
        onChange(value.map((row, i) => (i === index ? button : row)));
    const remove = (index: number) =>
        onChange(value.filter((_, i) => i !== index));
    const move = (index: number, direction: -1 | 1) =>
        onChange(moveItem(value, index, direction));

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <Label>Botones</Label>
                    <p className="mt-1 text-xs text-neutral-500">
                        {description}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    disabled={!canAdd}
                    onClick={() =>
                        onChange([...value, { label: 'Botón', url: '#' }])
                    }
                >
                    <Plus className="size-4" />
                    Agregar
                </Button>
            </div>
            {value.map((button, index) => (
                <ListCard
                    key={index}
                    title={button.label || `Botón ${index + 1}`}
                    index={index}
                    total={value.length}
                    badges={[index === 0 ? 'Principal' : 'Secundario']}
                    onMoveUp={() => move(index, -1)}
                    onMoveDown={() => move(index, 1)}
                    onRemove={() => remove(index)}
                >
                    <div className="grid gap-3 md:grid-cols-2">
                        <TextField
                            label="Label"
                            value={button.label ?? ''}
                            onChange={(label) =>
                                update(index, { ...button, label })
                            }
                        />
                        <TextField
                            label="URL"
                            value={button.url ?? ''}
                            onChange={(url) =>
                                update(index, { ...button, url })
                            }
                        />
                    </div>
                </ListCard>
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
    const update = (index: number, item: string) =>
        onChange(value.map((row, i) => (i === index ? item : row)));
    const remove = (index: number) =>
        onChange(value.filter((_, i) => i !== index));
    const move = (index: number, direction: -1 | 1) =>
        onChange(moveItem(value, index, direction));

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <Label>{label}</Label>
                    <p className="mt-1 text-xs text-neutral-500">
                        {value.length} elemento{value.length === 1 ? '' : 's'}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => onChange([...value, 'Nuevo'])}
                >
                    <Plus className="size-4" />
                    Agregar
                </Button>
            </div>
            {value.map((item, index) => (
                <div
                    key={index}
                    className="flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50/60 p-2 dark:border-neutral-800 dark:bg-neutral-900/40"
                >
                    <Badge variant="outline">{index + 1}</Badge>
                    <Input
                        value={item}
                        onChange={(event) => update(index, event.target.value)}
                    />
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => move(index, -1)}
                        disabled={index === 0}
                        title="Subir"
                    >
                        <ArrowUp className="size-4" />
                    </Button>
                    <Button
                        variant="outline"
                        size="sm"
                        onClick={() => move(index, 1)}
                        disabled={index === value.length - 1}
                        title="Bajar"
                    >
                        <ArrowDown className="size-4" />
                    </Button>
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={() => remove(index)}
                        title="Eliminar"
                    >
                        <Trash2 className="size-4" />
                    </Button>
                </div>
            ))}
        </div>
    );
}

function ProductList({
    products,
    value,
    onChange,
}: {
    products: ProductOption[];
    value: number[];
    onChange: (value: number[]) => void;
}) {
    const [selectedProductId, setSelectedProductId] = useState('');
    const selectedProducts = value
        .map((id) => products.find((product) => product.id === id))
        .filter(Boolean) as ProductOption[];
    const availableProducts = products.filter(
        (product) => !value.includes(product.id),
    );
    const add = () => {
        const productId = Number(selectedProductId);

        if (!productId || value.includes(productId) || value.length >= 12) {
            return;
        }

        onChange([...value, productId]);
        setSelectedProductId('');
    };
    const remove = (productId: number) =>
        onChange(value.filter((id) => id !== productId));
    const move = (index: number, direction: -1 | 1) =>
        onChange(moveItem(value, index, direction));

    return (
        <div className="space-y-3">
            <div className="flex flex-wrap items-end gap-2">
                <div className="grid min-w-0 flex-1 gap-1.5">
                    <Label>Agregar producto</Label>
                    <select
                        value={selectedProductId}
                        onChange={(event) =>
                            setSelectedProductId(event.target.value)
                        }
                        className="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm outline-none transition focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900"
                    >
                        <option value="">Seleccionar</option>
                        {availableProducts.map((product) => (
                            <option key={product.id} value={product.id}>
                                {product.label} ({product.sku})
                            </option>
                        ))}
                    </select>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={add}
                    disabled={!selectedProductId || value.length >= 12}
                >
                    <Plus className="size-4" />
                    Agregar
                </Button>
            </div>
            <div className="space-y-2">
                {selectedProducts.length === 0 && (
                    <div className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-4 text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900">
                        Sin productos seleccionados.
                    </div>
                )}
                {selectedProducts.map((product, index) => (
                    <div
                        key={product.id}
                        className="flex items-center gap-2 rounded-lg border border-neutral-200 bg-neutral-50/60 p-2 dark:border-neutral-800 dark:bg-neutral-900/40"
                    >
                        <Badge variant="outline">{index + 1}</Badge>
                        <div className="min-w-0 flex-1">
                            <p className="truncate text-sm font-medium">
                                {product.label}
                            </p>
                            <p className="text-xs text-neutral-500">
                                SKU {product.sku}
                            </p>
                        </div>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => move(index, -1)}
                            disabled={index === 0}
                            title="Subir"
                        >
                            <ArrowUp className="size-4" />
                        </Button>
                        <Button
                            variant="outline"
                            size="sm"
                            onClick={() => move(index, 1)}
                            disabled={index === selectedProducts.length - 1}
                            title="Bajar"
                        >
                            <ArrowDown className="size-4" />
                        </Button>
                        <Button
                            variant="destructive"
                            size="sm"
                            onClick={() => remove(product.id)}
                            title="Eliminar"
                        >
                            <Trash2 className="size-4" />
                        </Button>
                    </div>
                ))}
            </div>
            <p className="text-xs text-neutral-500">
                {value.length}/12 productos seleccionados.
            </p>
        </div>
    );
}

function MiniPreview({ section }: { section: Section }) {
    const meta = sectionMeta(section.type);

    return (
        <aside className="xl:sticky xl:top-32 xl:self-start">
            <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-900/40">
                <div className="mb-3 flex items-center justify-between gap-2">
                    <div>
                        <h3 className="text-sm font-semibold">Mini preview</h3>
                        <p className="text-xs text-neutral-500">
                            Vista ligera de {meta.label.toLowerCase()}.
                        </p>
                    </div>
                    <div className="flex flex-wrap justify-end gap-2">
                        <Badge variant="outline">
                            {text(section.settings.background_color) ||
                                'default'}
                        </Badge>
                        <Badge variant="outline">
                            {contentWidthLabel(section.settings.content_width)}
                        </Badge>
                    </div>
                </div>
                <PreviewBody section={section} />
            </div>
        </aside>
    );
}

function PreviewBody({ section }: { section: Section }) {
    if (section.type === 'hero') {
        const firstSlide = arrayValue<HeroSlide>(section.settings.slides)[0];
        const previewSettings = firstSlide
            ? ({ ...section.settings, ...firstSlide } as SectionSettings)
            : section.settings;
        const buttons = arrayValue<BuilderButton>(previewSettings.buttons);

        return (
            <PreviewSurface
                settings={previewSettings}
                className="min-h-56 justify-center text-white"
            >
                <p className="w-fit rounded bg-white/15 px-2 py-1 text-[10px] font-bold uppercase">
                    {text(previewSettings.eyebrow) || 'Etiqueta'}
                </p>
                <h4 className="mt-3 text-2xl font-black">
                    {text(section.settings.title) || 'Título del hero'}
                </h4>
                <p className="mt-2 line-clamp-3 text-xs text-white/80">
                    {text(section.settings.subtitle) || 'Subtítulo del hero'}
                </p>
                <div className="mt-4 flex flex-wrap gap-2">
                    {buttons.slice(0, 2).map((button, index) => (
                        <span
                            key={`${button.label}-${index}`}
                            className={cn(
                                'rounded px-3 py-2 text-[10px] font-bold uppercase',
                                index === 0
                                    ? 'bg-white text-red-950'
                                    : 'border border-white text-white',
                            )}
                        >
                            {button.label || 'Botón'}
                        </span>
                    ))}
                </div>
            </PreviewSurface>
        );
    }

    if (section.type === 'specialty_grid') {
        const items = arrayValue<BuilderItem>(section.settings.items);

        return (
            <PreviewSurface settings={section.settings}>
                <PreviewHeading settings={section.settings} />
                <div className="mt-4 grid grid-cols-2 gap-2">
                    {items.slice(0, 4).map((item, index) => (
                        <div
                            key={`${item.title}-${index}`}
                            className={cn(
                                'min-h-20 rounded-md border p-3 text-xs',
                                item.highlighted
                                    ? 'border-red-800 bg-red-800 text-white'
                                    : 'border-neutral-200 bg-white text-neutral-900',
                                item.wide ? 'col-span-2' : '',
                            )}
                        >
                            <p className="font-semibold">
                                {item.title || 'Especialidad'}
                            </p>
                            <p className="mt-1 line-clamp-2 opacity-70">
                                {item.text || 'Texto corto'}
                            </p>
                        </div>
                    ))}
                </div>
            </PreviewSurface>
        );
    }

    if (section.type === 'feature_cards') {
        const items = arrayValue<BuilderItem>(section.settings.items);

        return (
            <PreviewSurface settings={section.settings}>
                <div className="grid gap-3">
                    {items.slice(0, 2).map((item, index) => (
                        <div
                            key={`${item.title}-${index}`}
                            className="rounded-md bg-white p-3 shadow-sm"
                        >
                            <div className="mb-3 h-12 rounded bg-neutral-200" />
                            <p className="font-semibold text-neutral-900">
                                {item.title || 'Card'}
                            </p>
                            <p className="mt-1 line-clamp-2 text-xs text-neutral-500">
                                {item.text || 'Descripción'}
                            </p>
                        </div>
                    ))}
                </div>
            </PreviewSurface>
        );
    }

    if (section.type === 'brand_strip') {
        const brands = arrayValue<string>(section.settings.brands);

        return (
            <PreviewSurface settings={section.settings}>
                <PreviewHeading settings={section.settings} />
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    {brands.slice(0, 6).map((brand) => (
                        <span
                            key={brand}
                            className="rounded bg-neutral-200 px-3 py-2 text-[10px] font-semibold uppercase text-neutral-600"
                        >
                            {brand}
                        </span>
                    ))}
                </div>
            </PreviewSurface>
        );
    }

    if (section.type === 'image_banner') {
        return (
            <PreviewSurface settings={section.settings}>
                <div
                    className={cn(
                        'grid gap-3',
                        imagePositionValue(section.settings.image_position) ===
                            'background'
                            ? ''
                            : 'sm:grid-cols-2',
                    )}
                >
                    <div
                        className={cn(
                            'min-h-28 rounded-md bg-neutral-200',
                            imagePositionValue(section.settings.image_position) ===
                                'left'
                                ? 'sm:order-first'
                                : 'sm:order-last',
                            imagePositionValue(section.settings.image_position) ===
                                'background'
                                ? 'hidden'
                                : '',
                        )}
                    />
                    <div>
                        <p className="text-[10px] font-bold tracking-wide text-red-800 uppercase">
                            {text(section.settings.eyebrow) || 'Etiqueta'}
                        </p>
                        <h4 className="mt-1 text-xl font-black text-neutral-950">
                            {text(section.settings.title) || 'Banner imagen'}
                        </h4>
                        <p className="mt-2 line-clamp-3 text-xs text-neutral-500">
                            {text(section.settings.text) || 'Texto del banner'}
                        </p>
                        {text(section.settings.button_label) && (
                            <span className="mt-3 inline-flex rounded bg-red-800 px-3 py-2 text-[10px] font-bold text-white uppercase">
                                {text(section.settings.button_label)}
                            </span>
                        )}
                    </div>
                </div>
            </PreviewSurface>
        );
    }

    if (section.type === 'recommended_products') {
        const productIds = arrayValue<number>(section.settings.product_ids);
        const columns = columnsValue(section.settings.columns);
        const displayType = displayTypeValue(section.settings.display_type);

        return (
            <PreviewSurface settings={section.settings}>
                <PreviewHeading settings={section.settings} />
                <p className="mx-auto mt-3 max-w-64 text-center text-xs text-neutral-500">
                    {text(section.settings.subtitle) ||
                        `${productIds.length} productos seleccionados`}
                </p>
                <div
                    className={cn(
                        'mt-4 gap-2',
                        displayType === 'carousel'
                            ? 'flex overflow-x-auto pb-2'
                            : 'grid',
                        displayType === 'grid' && columns === '3'
                            ? 'grid-cols-3'
                            : '',
                        displayType === 'grid' && columns === '4'
                            ? 'grid-cols-4'
                            : '',
                    )}
                >
                    {Array.from({ length: Math.min(productIds.length || 4, 8) }).map(
                        (_, index) => (
                            <div
                                key={index}
                                className={cn(
                                    'rounded-md border border-neutral-200 bg-white p-2',
                                    displayType === 'carousel'
                                        ? 'w-20 shrink-0'
                                        : '',
                                )}
                            >
                                <div className="aspect-square rounded bg-neutral-200" />
                                <div className="mt-2 h-2 rounded bg-neutral-300" />
                                <div className="mt-1 h-2 w-2/3 rounded bg-red-200" />
                            </div>
                        ),
                    )}
                </div>
            </PreviewSurface>
        );
    }

    const areas = arrayValue<string>(section.settings.interest_areas);

    return (
        <PreviewSurface settings={section.settings}>
            <div className="grid gap-4 sm:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <h4 className="text-xl font-black leading-tight">
                        {text(section.settings.title) || 'Título'}
                    </h4>
                    <p className="mt-3 line-clamp-3 text-xs text-neutral-500">
                        {text(section.settings.text) || 'Texto'}
                    </p>
                    <p className="mt-4 text-xs text-neutral-700">
                        {text(section.settings.phone) || 'Teléfono'}
                    </p>
                </div>
                <div className="rounded-md border border-red-900/15 bg-white p-3">
                    <div className="grid gap-2">
                        <div className="h-8 rounded border border-red-900/20" />
                        <div className="h-8 rounded border border-red-900/20" />
                        <div className="rounded border border-red-900/20 px-2 py-2 text-[10px] text-neutral-500">
                            {areas[0] || 'Área de interés'}
                        </div>
                        <div className="h-8 rounded bg-red-800" />
                    </div>
                </div>
            </div>
        </PreviewSurface>
    );
}

function PreviewSurface({
    settings,
    children,
    className,
}: {
    settings: SectionSettings;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'flex flex-col rounded-lg border border-neutral-200 p-4 shadow-inner',
                className,
            )}
            style={previewStyle(settings)}
        >
            {children}
        </div>
    );
}

function PreviewHeading({ settings }: { settings: SectionSettings }) {
    return (
        <div className="text-center">
            <p className="text-[10px] font-bold tracking-wide text-red-800 uppercase">
                {text(settings.eyebrow) || 'Etiqueta'}
            </p>
            <h4 className="mt-1 text-lg font-black text-neutral-950">
                {text(settings.title) || 'Título'}
            </h4>
            <div className="mx-auto mt-2 h-0.5 w-12 rounded bg-red-800" />
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
    const [uploading, setUploading] = useState(false);
    const [extraMedia, setExtraMedia] = useState<MediaOption[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);

    const allMedia = [...media, ...extraMedia];
    const selected = allMedia.find((item) => item.id === value);

    const handleUpload = async (event: ChangeEvent<HTMLInputElement>) => {
        const file = event.target.files?.[0];
        if (!file) {
            return;
        }

        setUploading(true);

        const formData = new FormData();
        formData.append('files[]', file);

        try {
            const response = await fetch(mediaRoutes.store.url(), {
                method: 'POST',
                body: formData,
                headers: { Accept: 'application/json' },
            });

            if (!response.ok) {
                throw new Error('Error al subir la imagen');
            }

            const data = await response.json();
            const newMedia: MediaOption = {
                id: data.id,
                label: data.name,
                url: data.url,
            };

            setExtraMedia((prev) => [...prev, newMedia]);
            onChange(data.id);
        } catch {
            // User can retry from same control.
        } finally {
            setUploading(false);
            if (inputRef.current) {
                inputRef.current.value = '';
            }
        }
    };

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>

            {selected ? (
                <div className="overflow-hidden rounded-lg border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900">
                    <img
                        src={selected.url}
                        alt={selected.label}
                        className="h-40 w-full object-cover"
                    />
                    <div className="flex items-center justify-between gap-2 p-2">
                        <p className="truncate text-xs text-neutral-500">
                            {selected.label}
                        </p>
                        <button
                            type="button"
                            onClick={() => onChange(null)}
                            className="text-xs font-medium text-red-700 hover:text-red-900"
                        >
                            Quitar
                        </button>
                    </div>
                </div>
            ) : (
                <div className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-4 text-sm text-neutral-500 dark:border-neutral-700 dark:bg-neutral-900">
                    Sin imagen seleccionada.
                </div>
            )}

            <div className="flex items-center gap-2">
                <Button
                    variant="outline"
                    size="sm"
                    type="button"
                    disabled={uploading}
                    onClick={() => inputRef.current?.click()}
                >
                    <Upload className="size-4" />
                    {uploading ? 'Subiendo...' : 'Subir imagen'}
                </Button>
                <input
                    ref={inputRef}
                    type="file"
                    accept="image/*"
                    className="hidden"
                    onChange={handleUpload}
                />
            </div>

            {allMedia.length > 0 && (
                <div className="grid max-h-52 grid-cols-5 gap-2 overflow-y-auto rounded-lg border border-neutral-200 bg-white p-2 dark:border-neutral-800 dark:bg-neutral-950">
                    {allMedia.map((item) => (
                        <button
                            key={item.id}
                            type="button"
                            onClick={() =>
                                onChange(item.id === value ? null : item.id)
                            }
                            className={cn(
                                'flex aspect-square items-center justify-center overflow-hidden rounded-md border bg-neutral-50 p-1 transition-colors dark:bg-neutral-900',
                                item.id === value
                                    ? 'border-red-800 ring-2 ring-red-800'
                                    : 'border-neutral-200 hover:border-neutral-400 dark:border-neutral-800',
                            )}
                            title={item.label}
                        >
                            <img
                                src={item.url}
                                alt={item.label}
                                className="size-full object-cover"
                            />
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

function TextField({
    label,
    value,
    disabled,
    onChange,
}: {
    label: string;
    value: string;
    disabled?: boolean;
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <Input
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
            />
        </div>
    );
}

function ColorField({
    label,
    value,
    onChange,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
}) {
    const pickerValue = isHexColor(value) ? value : '#ffffff';

    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <div className="flex items-center gap-2">
                <Input
                    type="color"
                    value={pickerValue}
                    onChange={(event) => onChange(event.target.value)}
                    className="h-10 w-14 shrink-0 cursor-pointer p-1"
                />
                <Input
                    value={value}
                    placeholder="#ffffff"
                    onChange={(event) => onChange(event.target.value)}
                />
            </div>
        </div>
    );
}

function SelectField({
    label,
    value,
    options,
    onChange,
}: {
    label: string;
    value: string;
    options: { value: string; label: string }[];
    onChange: (value: string) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <select
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm outline-none transition focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900"
            >
                {options.map((option) => (
                    <option key={option.value} value={option.value}>
                        {option.label}
                    </option>
                ))}
            </select>
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
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <textarea
                value={value}
                onChange={(event) => onChange(event.target.value)}
                className="min-h-28 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm outline-none transition focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900"
            />
        </div>
    );
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

    if (Array.isArray(clone.slides)) {
        clone.slides = clone.slides.slice(0, 5).map((slide) => {
            const next = { ...slide };
            delete next.media;
            next.buttons = arrayValue<BuilderButton>(next.buttons).slice(0, 2);
            return next;
        });
    }

    return clone;
}

function sectionMeta(type: string): {
    label: string;
    description: string;
    icon: LucideIcon;
} {
    return (
        SECTION_META[type] ?? {
            label: type,
            description: 'Sección del template',
            icon: Sparkles,
        }
    );
}

function sectionSummary(section: Section): string {
    const settings = section.settings;

    if (section.type === 'hero') {
        const slides = arrayValue<HeroSlide>(settings.slides);

        if (slides.length > 0) {
            return `${slides.length} slide${slides.length === 1 ? '' : 's'}`;
        }

        return text(settings.title) || 'Portada sin título';
    }

    if (section.type === 'specialty_grid') {
        return `${arrayValue<BuilderItem>(settings.items).length} especialidades`;
    }

    if (section.type === 'feature_cards') {
        return `${arrayValue<BuilderItem>(settings.items).length} cards`;
    }

    if (section.type === 'brand_strip') {
        return `${arrayValue<string>(settings.brands).length} marcas`;
    }

    if (section.type === 'inquiry_form') {
        return text(settings.email) || text(settings.phone) || 'Formulario';
    }

    if (section.type === 'image_banner') {
        return text(settings.title) || 'Banner sin titulo';
    }

    if (section.type === 'recommended_products') {
        return `${arrayValue<number>(settings.product_ids).length} productos`;
    }

    return 'Contenido editable';
}

function previewStyle(settings: SectionSettings): CSSProperties {
    const backgroundColor = text(settings.background_color) || '#ffffff';

    return { backgroundColor };
}

function text(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function contentWidthValue(value: unknown): 'container' | 'full' {
    return value === 'full' ? 'full' : 'container';
}

function contentWidthLabel(value: unknown): string {
    return contentWidthValue(value) === 'full' ? 'Ancho completo' : 'Contenedor';
}

function imagePositionValue(value: unknown): 'left' | 'right' | 'background' {
    return value === 'left' || value === 'background' ? value : 'right';
}

function columnsValue(value: unknown): '3' | '4' {
    return value === 3 || value === '3' ? '3' : '4';
}

function displayTypeValue(value: unknown): 'grid' | 'carousel' {
    return value === 'carousel' ? 'carousel' : 'grid';
}

function numberValue(value: unknown): number | null {
    return typeof value === 'number' ? value : null;
}

function arrayValue<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
}

function isHexColor(value: string): boolean {
    return /^#(?:[0-9a-fA-F]{3}){1,2}$/.test(value);
}

function isFixedSection(type: string): boolean {
    return FIXED_SECTION_TYPES.includes(type);
}

function isExtraSection(type: string): boolean {
    return EXTRA_SECTION_TYPES.includes(
        type as (typeof EXTRA_SECTION_TYPES)[number],
    );
}

function newExtraSection(type: (typeof EXTRA_SECTION_TYPES)[number]): Section {
    return {
        id: -Date.now() - Math.floor(Math.random() * 1000),
        type,
        settings: extraSectionDefaults(type),
    };
}

function extraSectionDefaults(
    type: (typeof EXTRA_SECTION_TYPES)[number],
): SectionSettings {
    if (type === 'recommended_products') {
        return {
            background_color: '#ffffff',
            content_width: 'container',
            eyebrow: 'Recomendados',
            title: 'Productos recomendados',
            subtitle: '',
            product_ids: [],
            display_type: 'grid',
            columns: 4,
        };
    }

    return {
        background_color: '#ffffff',
        content_width: 'container',
        eyebrow: 'Nuevo bloque',
        title: 'Banner imagen',
        text: '',
        media_id: null,
        button_label: 'Ver mas',
        button_url: '#',
        image_position: 'right',
    };
}

function moveItem<T>(items: T[], index: number, direction: -1 | 1): T[] {
    const nextIndex = index + direction;

    if (nextIndex < 0 || nextIndex >= items.length) {
        return items;
    }

    const next = [...items];
    [next[index], next[nextIndex]] = [next[nextIndex], next[index]];

    return next;
}

function sortSections(sections: Section[]): Section[] {
    return [...sections].sort(
        (a, b) => sectionSortOrder(a) - sectionSortOrder(b),
    );
}

function sectionSortOrder(section: Section): number {
    const displayOrder = section.settings.display_order;

    if (typeof displayOrder === 'number') {
        return displayOrder;
    }

    if (typeof displayOrder === 'string' && displayOrder.trim() !== '') {
        const parsedOrder = Number(displayOrder);

        if (Number.isFinite(parsedOrder)) {
            return parsedOrder;
        }
    }

    const templateOrder = TYPE_ORDER.indexOf(
        section.type as (typeof TYPE_ORDER)[number],
    );

    return templateOrder === -1 ? Number.MAX_SAFE_INTEGER : templateOrder;
}

function slugify(value: string): string {
    return value
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
}
