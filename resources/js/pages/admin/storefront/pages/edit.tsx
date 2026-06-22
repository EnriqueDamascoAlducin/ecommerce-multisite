import type { FormDataConvertible } from '@inertiajs/core';
import { Head, Link, router, useForm } from '@inertiajs/react';
import {
    Activity,
    ArrowDown,
    ArrowLeft,
    ArrowRight,
    ArrowUp,
    BadgeCheck,
    ChevronDown,
    Eye,
    Grid3X3,
    Heading,
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
    Type as TextIcon,
    Upload,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { lazy, Suspense, useRef, useState } from 'react';
import type { ChangeEvent, CSSProperties } from 'react';
import { ConfirmDialog } from '@/components/confirm-dialog';
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
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import mediaRoutes from '@/routes/admin/media';
import storefrontPages from '@/routes/admin/storefront/pages';

const RichTextEditor = lazy(() =>
    import('@/components/admin/rich-text-editor').then((module) => ({
        default: module.RichTextEditor,
    })),
);

type MediaOption = { id: number; label: string; url: string };
type ProductOption = { id: number; label: string; sku: string };
type CmsMedia = { id: number; url: string; alt: string | null } | null;
type SectionSettings = Record<string, FormDataConvertible> & {
    media?: CmsMedia;
    slides?: HeroSlide[];
    items?: BuilderItem[];
    buttons?: BuilderButton[];
    brands?: BrandValue[];
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
    overlay_enabled?: boolean;
    overlay_color?: string;
    overlay_opacity?: number;
    buttons?: BuilderButton[];
};
type Section = {
    id: number;
    type: string;
    settings: SectionSettings;
};
type SeoSettings = {
    meta_title: string | null;
    meta_description: string | null;
    meta_keywords: string | null;
    robots_index: boolean;
    robots_follow: boolean;
    canonical_url: string | null;
    og_title: string | null;
    og_description: string | null;
    og_media_id: number | null;
    og_media?: CmsMedia | null;
};
type Page = {
    id: number;
    store_id: number;
    store_ids: number[];
    title: string;
    slug: string;
    template: string;
    is_published: boolean;
    seo: SeoSettings;
    sections: Section[];
};
type TemplateInfo = {
    key: string | null;
    label: string | null;
    fixedTypes: string[];
    extraTypes: string[];
};
type TemplateOption = { key: string; label: string; description: string };
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
type BuilderBrand = {
    name?: string;
    media_id?: number | null;
    media?: CmsMedia;
};
type BrandValue = string | BuilderBrand;

const TYPE_ORDER = [
    'hero',
    'specialty_grid',
    'feature_cards',
    'brand_strip',
    'inquiry_form',
] as const;

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
    page_header: {
        label: 'Encabezado de página',
        description: 'Título y subtítulo para páginas interiores',
        icon: Heading,
    },
    rich_text: {
        label: 'Texto enriquecido',
        description: 'Contenido con formato (negritas, listas, enlaces)',
        icon: TextIcon,
    },
    contact_info: {
        label: 'Datos de contacto',
        description: 'Teléfono, email, dirección, horario y mapa',
        icon: Mail,
    },
};

export default function StorefrontPageEdit({
    stores,
    currentStoreId,
    page,
    media,
    products,
    publicUrl,
    isHome,
    template,
    availableTemplates,
}: {
    stores: { id: number; label: string }[];
    currentStoreId: number;
    page: Page;
    media: MediaOption[];
    products: ProductOption[];
    publicUrl: string;
    isHome: boolean;
    template: TemplateInfo;
    availableTemplates: TemplateOption[];
}) {
    const pageForm = useForm({
        store_id: currentStoreId,
        store_ids: page.store_ids,
        title: page.title,
        slug: page.slug,
        is_published: page.is_published,
    });
    const [seo, setSeo] = useState<SeoSettings>(page.seo);
    const [templateKey, setTemplateKey] = useState(page.template);
    const fixedTypes = template.fixedTypes;
    const extraTypes =
        pageForm.data.store_ids.length > 1
            ? template.extraTypes.filter(
                  (type) => type !== 'recommended_products',
              )
            : template.extraTypes;
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

    const addSection = (type: string) => {
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

    const toggleStore = (storeId: number, checked: boolean) => {
        if (isHome) {
            return;
        }

        if (
            !checked &&
            (storeId === currentStoreId || pageForm.data.store_ids.length === 1)
        ) {
            return;
        }

        pageForm.setData(
            'store_ids',
            checked
                ? [...new Set([...pageForm.data.store_ids, storeId])]
                : pageForm.data.store_ids.filter((id) => id !== storeId),
        );
    };

    const savePage = () => {
        setSaving(true);
        const templateChanged = !isHome && templateKey !== page.template;
        const payload = {
            store_id: pageForm.data.store_id,
            store_ids: pageForm.data.store_ids,
            title: pageForm.data.title,
            seo_store_id: currentStoreId,
            seo: {
                ...seo,
                og_media: undefined,
            },
            is_published: pageForm.data.is_published,
            ...(isHome
                ? {}
                : { slug: pageForm.data.slug, template: templateKey }),
            sections: sections.map((section) => ({
                ...(section.id > 0
                    ? { id: section.id }
                    : { type: section.type }),
                settings: stripResolvedMedia(section.settings),
            })),
        };

        router.put(storefrontPages.update.url(page.id), payload, {
            preserveScroll: true,
            // Switching template re-seeds the layout server-side; reload to
            // pick up the new section structure with real ids.
            onError: (errors) => pageForm.setError(errors),
            onSuccess: () => {
                if (templateChanged) {
                    window.location.reload();
                }
            },
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
                stores={stores}
                storeIds={pageForm.data.store_ids}
                storeError={pageForm.errors.store_ids}
                hasRecommendedProducts={sections.some(
                    (section) => section.type === 'recommended_products',
                )}
                title={pageForm.data.title}
                slug={pageForm.data.slug}
                isPublished={pageForm.data.is_published}
                saving={saving}
                templateKey={templateKey}
                templateChanged={!isHome && templateKey !== page.template}
                availableTemplates={availableTemplates}
                onTemplateChange={setTemplateKey}
                onTitleChange={(title) => pageForm.setData('title', title)}
                onSlugChange={(slug) => pageForm.setData('slug', slugify(slug))}
                onPublishedChange={(published) =>
                    pageForm.setData('is_published', published)
                }
                onStoreToggle={toggleStore}
                onSave={savePage}
            >
                <SeoPanel
                    pageId={page.id}
                    stores={stores}
                    storeIds={pageForm.data.store_ids}
                    currentStoreId={currentStoreId}
                    publicUrl={publicUrl}
                    media={media}
                    value={seo}
                    onChange={setSeo}
                />
                {sections.length > 0 && activeSection ? (
                    <div className="grid gap-5 lg:grid-cols-[20rem_minmax(0,1fr)]">
                        <SectionSidebar
                            sections={sections}
                            activeSectionId={activeSection.id}
                            fixedTypes={fixedTypes}
                            extraTypes={extraTypes}
                            onSelect={setActiveSectionId}
                            onMoveSection={moveSection}
                            onAddSection={addSection}
                            onRemoveSection={removeSection}
                        />
                        <SectionPanel
                            key={activeSection.id}
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
                                {extraTypes.length > 0
                                    ? 'Esta página aún no tiene secciones. Agrega la primera:'
                                    : 'Esta página no tiene secciones de template.'}
                            </p>
                            {extraTypes.length > 0 && (
                                <div className="mx-auto mt-4 max-w-xs text-left">
                                    <AddSectionControl
                                        extraTypes={extraTypes}
                                        onAdd={addSection}
                                    />
                                </div>
                            )}
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
    stores,
    storeIds,
    storeError,
    hasRecommendedProducts,
    title,
    slug,
    isPublished,
    saving,
    templateKey,
    templateChanged,
    availableTemplates,
    children,
    onTemplateChange,
    onTitleChange,
    onSlugChange,
    onPublishedChange,
    onStoreToggle,
    onSave,
}: {
    pageTitle: string;
    publicUrl: string;
    backUrl: string;
    isHome: boolean;
    stores: { id: number; label: string }[];
    storeIds: number[];
    storeError?: string;
    hasRecommendedProducts: boolean;
    title: string;
    slug: string;
    isPublished: boolean;
    saving: boolean;
    templateKey: string;
    templateChanged: boolean;
    availableTemplates: TemplateOption[];
    children: React.ReactNode;
    onTemplateChange: (template: string) => void;
    onTitleChange: (title: string) => void;
    onSlugChange: (slug: string) => void;
    onPublishedChange: (published: boolean) => void;
    onStoreToggle: (storeId: number, checked: boolean) => void;
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
                                    variant={
                                        isPublished ? 'default' : 'outline'
                                    }
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
                            <a
                                href={publicUrl}
                                target="_blank"
                                rel="noreferrer"
                            >
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

                    <div className="mt-4">
                        <Label>Disponible en tiendas</Label>
                        <div className="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            {stores.map((store) => (
                                <label
                                    key={store.id}
                                    className="flex min-w-0 items-center gap-2 rounded-md border border-neutral-200 px-3 py-2 text-sm dark:border-neutral-800"
                                >
                                    <Checkbox
                                        checked={storeIds.includes(store.id)}
                                        disabled={isHome}
                                        onCheckedChange={(value) =>
                                            onStoreToggle(
                                                store.id,
                                                value === true,
                                            )
                                        }
                                    />
                                    <span className="truncate">
                                        {store.label}
                                    </span>
                                </label>
                            ))}
                        </div>
                        {storeError && (
                            <p className="mt-1 text-xs text-red-600">
                                {storeError}
                            </p>
                        )}
                        {storeIds.length > 1 && hasRecommendedProducts && (
                            <p className="mt-2 text-xs text-amber-600 dark:text-amber-400">
                                Retira la seccion de productos recomendados
                                antes de compartir esta pagina.
                            </p>
                        )}
                    </div>

                    {!isHome && (
                        <div className="mt-4 grid gap-2 md:max-w-sm">
                            <SelectField
                                label="Plantilla"
                                value={templateKey}
                                options={availableTemplates.map((option) => ({
                                    value: option.key,
                                    label: option.label,
                                }))}
                                onChange={onTemplateChange}
                            />
                            {templateChanged && (
                                <p className="text-xs text-amber-600 dark:text-amber-400">
                                    Al guardar se aplicará la nueva plantilla y
                                    se añadirán sus secciones fijas.
                                </p>
                            )}
                        </div>
                    )}
                </CardContent>
            </Card>

            {children}
        </div>
    );
}

function SeoPanel({
    pageId,
    stores,
    storeIds,
    currentStoreId,
    publicUrl,
    media,
    value,
    onChange,
}: {
    pageId: number;
    stores: { id: number; label: string }[];
    storeIds: number[];
    currentStoreId: number;
    publicUrl: string;
    media: MediaOption[];
    value: SeoSettings;
    onChange: (value: SeoSettings) => void;
}) {
    const setField = <K extends keyof SeoSettings>(
        field: K,
        fieldValue: SeoSettings[K],
    ) => onChange({ ...value, [field]: fieldValue });

    return (
        <Card className="rounded-lg">
            <CardHeader className="gap-1">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <CardTitle>SEO de la página</CardTitle>
                        <CardDescription>
                            Metadatos independientes para cada tienda asignada.
                        </CardDescription>
                    </div>
                    <select
                        value={currentStoreId}
                        onChange={(event) =>
                            router.get(
                                storefrontPages.edit.url(pageId, {
                                    query: {
                                        store_id: Number(event.target.value),
                                    },
                                }),
                                {},
                                { preserveState: false },
                            )
                        }
                        className="h-9 rounded-md border border-neutral-300 bg-white px-3 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        {stores
                            .filter((store) => storeIds.includes(store.id))
                            .map((store) => (
                                <option key={store.id} value={store.id}>
                                    {store.label}
                                </option>
                            ))}
                    </select>
                </div>
            </CardHeader>
            <CardContent className="grid gap-5">
                <div className="grid gap-4 md:grid-cols-2">
                    <TextField
                        label="Título SEO"
                        value={value.meta_title ?? ''}
                        onChange={(next) => setField('meta_title', next)}
                    />
                    <TextField
                        label="Palabras clave"
                        value={value.meta_keywords ?? ''}
                        onChange={(next) => setField('meta_keywords', next)}
                    />
                </div>
                <TextArea
                    label="Meta descripción"
                    value={value.meta_description ?? ''}
                    onChange={(next) => setField('meta_description', next)}
                />
                <TextField
                    label="Canonical personalizado"
                    value={value.canonical_url ?? ''}
                    placeholder={publicUrl}
                    onChange={(next) => setField('canonical_url', next)}
                />
                <div className="flex flex-wrap gap-4">
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={value.robots_index}
                            onCheckedChange={(checked) =>
                                setField('robots_index', checked === true)
                            }
                        />
                        Indexar página
                    </label>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox
                            checked={value.robots_follow}
                            onCheckedChange={(checked) =>
                                setField('robots_follow', checked === true)
                            }
                        />
                        Seguir enlaces
                    </label>
                </div>
                <div className="grid gap-4 border-t border-neutral-200 pt-5 md:grid-cols-2 dark:border-neutral-800">
                    <div className="grid content-start gap-4">
                        <TextField
                            label="Título Open Graph"
                            value={value.og_title ?? ''}
                            onChange={(next) => setField('og_title', next)}
                        />
                        <TextArea
                            label="Descripción Open Graph"
                            value={value.og_description ?? ''}
                            onChange={(next) =>
                                setField('og_description', next)
                            }
                        />
                    </div>
                    <MediaPicker
                        label="Imagen Open Graph"
                        media={media}
                        compactLibrary
                        value={value.og_media_id}
                        onChange={(mediaId, selectedMedia) =>
                            onChange({
                                ...value,
                                og_media_id: mediaId,
                                og_media: selectedMedia
                                    ? {
                                          id: selectedMedia.id,
                                          url: selectedMedia.url,
                                          alt: selectedMedia.label,
                                      }
                                    : null,
                            })
                        }
                    />
                </div>
            </CardContent>
        </Card>
    );
}

function AddSectionControl({
    extraTypes,
    onAdd,
}: {
    extraTypes: string[];
    onAdd: (type: string) => void;
}) {
    const [newSectionType, setNewSectionType] = useState<string>(
        extraTypes[0] ?? '',
    );

    if (extraTypes.length === 0) {
        return null;
    }

    return (
        <div className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 p-2 dark:border-neutral-700 dark:bg-neutral-950">
            <Label className="text-xs">Agregar bloque</Label>
            <div className="mt-2 flex gap-2">
                <select
                    value={newSectionType}
                    onChange={(event) => setNewSectionType(event.target.value)}
                    className="h-9 min-w-0 flex-1 rounded-md border border-neutral-300 bg-white px-2 text-xs transition outline-none focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900"
                >
                    {extraTypes.map((type) => (
                        <option key={type} value={type}>
                            {sectionMeta(type).label}
                        </option>
                    ))}
                </select>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => newSectionType && onAdd(newSectionType)}
                    title="Agregar bloque"
                >
                    <Plus className="size-4" />
                </Button>
            </div>
        </div>
    );
}

function SectionSidebar({
    sections,
    activeSectionId,
    fixedTypes,
    extraTypes,
    onSelect,
    onMoveSection,
    onAddSection,
    onRemoveSection,
}: {
    sections: Section[];
    activeSectionId: number;
    fixedTypes: string[];
    extraTypes: string[];
    onSelect: (id: number) => void;
    onMoveSection: (id: number, direction: -1 | 1) => void;
    onAddSection: (type: string) => void;
    onRemoveSection: (id: number) => void;
}) {
    return (
        <aside className="lg:sticky lg:top-32 lg:self-start">
            <Card className="rounded-lg border-neutral-200 bg-white py-4 shadow-sm dark:border-neutral-800 dark:bg-neutral-900">
                <CardHeader className="px-4">
                    <CardTitle className="flex items-center gap-2 text-sm">
                        <PanelLeft className="size-4 text-red-700 dark:text-red-400" />
                        Secciones de la página
                    </CardTitle>
                    <CardDescription>
                        Selecciona una sección para editar su contenido.
                    </CardDescription>
                </CardHeader>
                <CardContent className="px-3">
                    <div className="mb-3">
                        <AddSectionControl
                            extraTypes={extraTypes}
                            onAdd={onAddSection}
                        />
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
                                    isFixed={fixedTypes.includes(section.type)}
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
                                    {!fixedTypes.includes(section.type) && (
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
    isFixed,
    onSelect,
}: {
    section: Section;
    index: number;
    active: boolean;
    isFixed: boolean;
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
                                variant={isFixed ? 'secondary' : 'outline'}
                                className="text-[10px]"
                            >
                                {isFixed ? 'Fijo' : 'Extra'}
                            </Badge>
                        </span>
                    </span>
                    <span className="mt-1 line-clamp-2 block text-xs text-neutral-500 dark:text-neutral-400">
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
    const [activeHeroSlideIndex, setActiveHeroSlideIndex] = useState(0);

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
                                <ColorField
                                    label="Color del título"
                                    value={text(section.settings.title_color)}
                                    onChange={(value) =>
                                        setSetting('title_color', value)
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
                                activeSlideIndex={activeHeroSlideIndex}
                                onActiveSlideChange={setActiveHeroSlideIndex}
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
                                media={media}
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
                        {section.type === 'page_header' && (
                            <PageHeaderFields
                                settings={section.settings}
                                setSetting={setSetting}
                            />
                        )}
                        {section.type === 'rich_text' && (
                            <RichTextFields
                                settings={section.settings}
                                setSetting={setSetting}
                            />
                        )}
                        {section.type === 'contact_info' && (
                            <ContactInfoFields
                                settings={section.settings}
                                setSetting={setSetting}
                            />
                        )}
                    </div>

                    <MiniPreview
                        section={section}
                        media={media}
                        heroSlideIndex={activeHeroSlideIndex}
                    />
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
    activeSlideIndex,
    onActiveSlideChange,
    setSetting,
}: {
    settings: SectionSettings;
    media: MediaOption[];
    activeSlideIndex: number;
    onActiveSlideChange: (index: number) => void;
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    const slides = arrayValue<HeroSlide>(settings.slides);
    const hasSlides = slides.length > 0;

    return (
        <>
            <FieldGroup
                title="Slides del hero"
                description="Hasta 5 campañas. Selecciona una miniatura para editar únicamente ese slide."
                icon={Layers3}
            >
                <HeroSlidesList
                    media={media}
                    fallbackSettings={settings}
                    value={slides}
                    activeIndex={activeSlideIndex}
                    onActiveChange={onActiveSlideChange}
                    onChange={(value) => setSetting('slides', value)}
                />
            </FieldGroup>

            <Collapsible
                key={hasSlides ? 'slides-configured' : 'fallback-active'}
                defaultOpen={!hasSlides}
                className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-950"
            >
                <CollapsibleTrigger asChild>
                    <button
                        type="button"
                        className="group flex w-full items-center gap-3 p-4 text-left transition hover:bg-neutral-50 dark:hover:bg-neutral-900"
                    >
                        <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-neutral-100 text-neutral-600 dark:bg-neutral-900 dark:text-neutral-300">
                            <Upload className="size-4" />
                        </span>
                        <span className="min-w-0 flex-1">
                            <span className="block text-sm font-semibold">
                                Configuración heredada
                            </span>
                            <span className="mt-1 block text-xs leading-5 text-neutral-500">
                                Contenido fallback usado sólo cuando no existen
                                slides.
                            </span>
                        </span>
                        <Badge variant="outline">
                            {hasSlides ? 'No visible' : 'En uso'}
                        </Badge>
                        <ChevronDown className="size-4 shrink-0 transition-transform group-data-[state=open]:rotate-180" />
                    </button>
                </CollapsibleTrigger>
                <CollapsibleContent className="border-t border-neutral-200 p-4 dark:border-neutral-800">
                    <div className="grid gap-5">
                        <section>
                            <div className="mb-3">
                                <h4 className="text-sm font-semibold">
                                    Contenido principal
                                </h4>
                                <p className="mt-1 text-xs text-neutral-500">
                                    Texto mostrado únicamente si el hero no
                                    tiene slides.
                                </p>
                            </div>
                            <div className="grid gap-4 md:grid-cols-2">
                                <TextField
                                    label="Etiqueta"
                                    value={text(settings.eyebrow)}
                                    onChange={(value) =>
                                        setSetting('eyebrow', value)
                                    }
                                />
                                <TextField
                                    label="Título"
                                    value={text(settings.title)}
                                    onChange={(value) =>
                                        setSetting('title', value)
                                    }
                                />
                                <div className="md:col-span-2">
                                    <TextArea
                                        label="Subtítulo"
                                        value={text(settings.subtitle)}
                                        onChange={(value) =>
                                            setSetting('subtitle', value)
                                        }
                                    />
                                </div>
                            </div>
                        </section>

                        <section className="border-t border-neutral-200 pt-5 dark:border-neutral-800">
                            <div className="mb-3">
                                <h4 className="text-sm font-semibold">
                                    Imagen y acciones
                                </h4>
                                <p className="mt-1 text-xs text-neutral-500">
                                    Imagen y botones usados por el fallback
                                    heredado.
                                </p>
                            </div>
                            <div className="space-y-4">
                                <MediaPicker
                                    label="Imagen de fondo fallback"
                                    media={media}
                                    compactLibrary
                                    value={numberValue(settings.media_id)}
                                    onChange={(value) =>
                                        setSetting('media_id', value)
                                    }
                                />
                                <ButtonList
                                    value={arrayValue<BuilderButton>(
                                        settings.buttons,
                                    )}
                                    onChange={(value) =>
                                        setSetting('buttons', value)
                                    }
                                />
                            </div>
                        </section>
                    </div>
                </CollapsibleContent>
            </Collapsible>
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
                description="Nombre de respaldo e imagen opcional por marca."
                icon={Sparkles}
            >
                <BrandList
                    media={media}
                    value={arrayValue<BrandValue>(settings.brands)}
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

function PageHeaderFields({
    settings,
    setSetting,
}: {
    settings: SectionSettings;
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <FieldGroup
            title="Encabezado"
            description="Título y subtítulo del encabezado de la página."
            icon={Heading}
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
    );
}

function RichTextFields({
    settings,
    setSetting,
}: {
    settings: SectionSettings;
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <FieldGroup
            title="Contenido"
            description="Texto con formato: negritas, listas, encabezados y enlaces."
            icon={TextIcon}
        >
            <Suspense fallback={<RichTextEditorFallback />}>
                <RichTextEditor
                    value={text(settings.html)}
                    onChange={(html) => setSetting('html', html)}
                />
            </Suspense>
        </FieldGroup>
    );
}

function RichTextEditorFallback() {
    return (
        <div className="min-h-[14.8rem] animate-pulse rounded-md border border-neutral-200 bg-neutral-50 dark:border-neutral-800 dark:bg-neutral-900" />
    );
}

function ContactInfoFields({
    settings,
    setSetting,
}: {
    settings: SectionSettings;
    setSetting: (key: string, value: FormDataConvertible) => void;
}) {
    return (
        <>
            <FieldGroup
                title="Datos de contacto"
                description="Información visible para los clientes."
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
                    <TextField
                        label="Horario"
                        value={text(settings.hours)}
                        onChange={(value) => setSetting('hours', value)}
                    />
                    <div className="md:col-span-2">
                        <TextArea
                            label="Dirección"
                            value={text(settings.address)}
                            onChange={(value) => setSetting('address', value)}
                        />
                    </div>
                </div>
            </FieldGroup>
            <FieldGroup
                title="Mapa"
                description="URL de inserción (embed) de Google Maps. Opcional."
                icon={Sparkles}
            >
                <TextField
                    label="URL del mapa"
                    value={text(settings.map_url)}
                    onChange={(value) => setSetting('map_url', value)}
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
                        onChange={(value) =>
                            setSetting('image_position', value)
                        }
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
                    <Label>
                        {type === 'feature_cards' ? 'Cards' : 'Items'}
                    </Label>
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
    activeIndex,
    onActiveChange,
    onChange,
}: {
    media: MediaOption[];
    fallbackSettings: SectionSettings;
    value: HeroSlide[];
    activeIndex: number;
    onActiveChange: (index: number) => void;
    onChange: (value: HeroSlide[]) => void;
}) {
    const slides = value.slice(0, 5);
    const selectedIndex = Math.min(activeIndex, Math.max(slides.length - 1, 0));
    const activeSlide = slides[selectedIndex];
    const [pendingDeleteIndex, setPendingDeleteIndex] = useState<number | null>(
        null,
    );

    const add = () => {
        if (slides.length >= 5) {
            return;
        }

        const next = [
            ...slides,
            {
                media_id: null,
                eyebrow: text(fallbackSettings.eyebrow),
                title: text(fallbackSettings.title) || 'Nuevo slide',
                subtitle: text(fallbackSettings.subtitle),
                buttons: arrayValue<BuilderButton>(
                    fallbackSettings.buttons,
                ).slice(0, 2),
                overlay_enabled: true,
                overlay_color: '#7f1d1d',
                overlay_opacity: 75,
            },
        ];

        onChange(next);
        onActiveChange(next.length - 1);
    };

    const update = (slide: HeroSlide) =>
        onChange(
            slides.map((row, index) => (index === selectedIndex ? slide : row)),
        );

    const move = (direction: -1 | 1) => {
        const nextIndex = selectedIndex + direction;

        if (nextIndex < 0 || nextIndex >= slides.length) {
            return;
        }

        onChange(moveItem(slides, selectedIndex, direction));
        onActiveChange(nextIndex);
    };

    const confirmDelete = () => {
        if (pendingDeleteIndex === null) {
            return;
        }

        const next = slides.filter((_, index) => index !== pendingDeleteIndex);
        onChange(next);
        onActiveChange(
            next.length === 0
                ? 0
                : Math.min(pendingDeleteIndex, next.length - 1),
        );
        setPendingDeleteIndex(null);
    };

    return (
        <>
            <div className="space-y-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <Label>Slides</Label>
                        <p className="mt-1 text-xs text-neutral-500">
                            {slides.length === 0
                                ? 'Sin slides: se usa la configuración heredada.'
                                : slides.length === 1
                                  ? '1 slide: se mostrará como hero fijo.'
                                  : `${slides.length} slides: se mostrarán como carrusel.`}
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

                {slides.length === 0 ? (
                    <div className="rounded-lg border border-dashed border-neutral-300 bg-neutral-50 px-4 py-8 text-center dark:border-neutral-700 dark:bg-neutral-900">
                        <ImageIcon className="mx-auto size-8 text-neutral-400" />
                        <p className="mt-3 text-sm font-medium">
                            Aún no hay slides configurados
                        </p>
                        <p className="mx-auto mt-1 max-w-sm text-xs leading-5 text-neutral-500">
                            El primer slide copiará el contenido y los botones
                            de la configuración heredada.
                        </p>
                        <Button
                            type="button"
                            size="sm"
                            className="mt-4"
                            onClick={add}
                        >
                            <Plus className="size-4" />
                            Crear primer slide
                        </Button>
                    </div>
                ) : (
                    <>
                        <div
                            role="tablist"
                            aria-label="Slides del hero"
                            className="flex max-w-full gap-2 overflow-x-auto pb-2"
                        >
                            {slides.map((slide, index) => {
                                const selectedMedia =
                                    slide.media ??
                                    media.find(
                                        (item) => item.id === slide.media_id,
                                    );

                                return (
                                    <button
                                        key={index}
                                        type="button"
                                        role="tab"
                                        aria-selected={index === selectedIndex}
                                        onClick={() => onActiveChange(index)}
                                        className={cn(
                                            'w-36 shrink-0 overflow-hidden rounded-md border bg-white text-left transition dark:bg-neutral-950',
                                            index === selectedIndex
                                                ? 'border-red-800 ring-2 ring-red-800/20'
                                                : 'border-neutral-200 hover:border-neutral-400 dark:border-neutral-800',
                                        )}
                                    >
                                        <span className="relative flex aspect-[16/9] items-center justify-center overflow-hidden bg-neutral-100 dark:bg-neutral-900">
                                            {selectedMedia?.url ? (
                                                <img
                                                    src={selectedMedia.url}
                                                    alt=""
                                                    className="size-full object-cover"
                                                />
                                            ) : (
                                                <ImageIcon className="size-6 text-neutral-400" />
                                            )}
                                            <Badge className="absolute top-1 left-1 h-5 min-w-5 justify-center px-1 text-[10px]">
                                                {index + 1}
                                            </Badge>
                                        </span>
                                        <span className="block p-2">
                                            <span className="block truncate text-xs font-semibold">
                                                {slide.title ||
                                                    `Slide ${index + 1}`}
                                            </span>
                                            <span className="mt-0.5 block text-[10px] text-neutral-500">
                                                {index === 0
                                                    ? 'Inicial'
                                                    : 'Carrusel'}
                                            </span>
                                        </span>
                                    </button>
                                );
                            })}
                        </div>

                        {activeSlide && (
                            <div className="border-t border-neutral-200 pt-4 dark:border-neutral-800">
                                <div className="mb-4 flex flex-wrap items-center justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="text-xs font-medium text-red-800 dark:text-red-300">
                                            Editando slide {selectedIndex + 1}{' '}
                                            de {slides.length}
                                        </p>
                                        <h4 className="mt-1 truncate text-base font-semibold">
                                            {activeSlide.title ||
                                                `Slide ${selectedIndex + 1}`}
                                        </h4>
                                    </div>
                                    <div className="flex items-center gap-1">
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => move(-1)}
                                            disabled={selectedIndex === 0}
                                            title="Mover a la izquierda"
                                        >
                                            <ArrowLeft className="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="icon"
                                            onClick={() => move(1)}
                                            disabled={
                                                selectedIndex ===
                                                slides.length - 1
                                            }
                                            title="Mover a la derecha"
                                        >
                                            <ArrowRight className="size-4" />
                                        </Button>
                                        <Button
                                            type="button"
                                            variant="destructive"
                                            size="icon"
                                            onClick={() =>
                                                setPendingDeleteIndex(
                                                    selectedIndex,
                                                )
                                            }
                                            title="Eliminar slide"
                                        >
                                            <Trash2 className="size-4" />
                                        </Button>
                                    </div>
                                </div>

                                <div className="grid gap-5">
                                    <section>
                                        <h5 className="mb-3 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                                            Imagen
                                        </h5>
                                        <MediaPicker
                                            label="Imagen del slide"
                                            media={media}
                                            compactLibrary
                                            value={activeSlide.media_id ?? null}
                                            onChange={(
                                                media_id,
                                                selectedMedia,
                                            ) =>
                                                update({
                                                    ...activeSlide,
                                                    media_id,
                                                    media: selectedMedia
                                                        ? {
                                                              id: selectedMedia.id,
                                                              url: selectedMedia.url,
                                                              alt: selectedMedia.label,
                                                          }
                                                        : null,
                                                })
                                            }
                                        />
                                    </section>

                                    <section className="border-t border-neutral-200 pt-5 dark:border-neutral-800">
                                        <h5 className="mb-3 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                                            Contenido
                                        </h5>
                                        <div className="grid gap-4 md:grid-cols-2">
                                            <TextField
                                                label="Etiqueta"
                                                value={
                                                    activeSlide.eyebrow ?? ''
                                                }
                                                onChange={(eyebrow) =>
                                                    update({
                                                        ...activeSlide,
                                                        eyebrow,
                                                    })
                                                }
                                            />
                                            <TextField
                                                label="Título"
                                                value={activeSlide.title ?? ''}
                                                onChange={(title) =>
                                                    update({
                                                        ...activeSlide,
                                                        title,
                                                    })
                                                }
                                            />
                                            <div className="md:col-span-2">
                                                <TextArea
                                                    label="Subtítulo"
                                                    value={
                                                        activeSlide.subtitle ??
                                                        ''
                                                    }
                                                    onChange={(subtitle) =>
                                                        update({
                                                            ...activeSlide,
                                                            subtitle,
                                                        })
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </section>

                                    <section className="border-t border-neutral-200 pt-5 dark:border-neutral-800">
                                        <h5 className="mb-3 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                                            Opacidad
                                        </h5>
                                        <div className="grid gap-3">
                                            <label className="flex items-center gap-2 text-sm">
                                                <Checkbox
                                                    checked={
                                                        activeSlide.overlay_enabled !==
                                                        false
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        update({
                                                            ...activeSlide,
                                                            overlay_enabled:
                                                                checked ===
                                                                true,
                                                        })
                                                    }
                                                />
                                                Aplicar opacidad sobre la imagen
                                            </label>
                                            <div className="grid gap-3 md:grid-cols-[1fr_12rem]">
                                                <ColorField
                                                    label="Color de opacidad"
                                                    value={
                                                        activeSlide.overlay_color ??
                                                        '#7f1d1d'
                                                    }
                                                    onChange={(overlay_color) =>
                                                        update({
                                                            ...activeSlide,
                                                            overlay_color,
                                                        })
                                                    }
                                                />
                                                <RangeField
                                                    label="Opacidad"
                                                    value={
                                                        activeSlide.overlay_opacity ??
                                                        75
                                                    }
                                                    min={0}
                                                    max={100}
                                                    disabled={
                                                        activeSlide.overlay_enabled ===
                                                        false
                                                    }
                                                    onChange={(
                                                        overlay_opacity,
                                                    ) =>
                                                        update({
                                                            ...activeSlide,
                                                            overlay_opacity,
                                                        })
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </section>

                                    <section className="border-t border-neutral-200 pt-5 dark:border-neutral-800">
                                        <h5 className="mb-3 text-xs font-semibold tracking-wide text-neutral-500 uppercase">
                                            Acciones
                                        </h5>
                                        <ButtonList
                                            value={arrayValue<BuilderButton>(
                                                activeSlide.buttons,
                                            )}
                                            maxItems={2}
                                            description="Hasta 2 acciones para este slide."
                                            onChange={(buttons) =>
                                                update({
                                                    ...activeSlide,
                                                    buttons,
                                                })
                                            }
                                        />
                                    </section>
                                </div>
                            </div>
                        )}
                    </>
                )}
            </div>

            <ConfirmDialog
                open={pendingDeleteIndex !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setPendingDeleteIndex(null);
                    }
                }}
                onConfirm={confirmDelete}
                title="Eliminar slide"
                description="El slide y su configuración se quitarán del hero al guardar la página."
                confirmLabel="Eliminar"
            />
        </>
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

function BrandList({
    media,
    value,
    onChange,
}: {
    media: MediaOption[];
    value: BrandValue[];
    onChange: (value: BuilderBrand[]) => void;
}) {
    const brands = value.map(normalizeBrandValue);
    const update = (index: number, brand: BuilderBrand) =>
        onChange(brands.map((row, i) => (i === index ? brand : row)));
    const remove = (index: number) =>
        onChange(brands.filter((_, i) => i !== index));
    const move = (index: number, direction: -1 | 1) =>
        onChange(moveItem(brands, index, direction));

    return (
        <div className="space-y-3">
            <div className="flex items-center justify-between gap-3">
                <div>
                    <Label>Marcas</Label>
                    <p className="mt-1 text-xs text-neutral-500">
                        {brands.length} marca{brands.length === 1 ? '' : 's'}
                    </p>
                </div>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() =>
                        onChange([
                            ...brands,
                            { name: 'Nueva marca', media_id: null },
                        ])
                    }
                >
                    <Plus className="size-4" />
                    Agregar
                </Button>
            </div>
            {brands.map((brand, index) => (
                <ListCard
                    key={index}
                    title={brand.name || `Marca ${index + 1}`}
                    index={index}
                    total={brands.length}
                    badges={[brand.media_id ? 'Imagen' : null]}
                    onMoveUp={() => move(index, -1)}
                    onMoveDown={() => move(index, 1)}
                    onRemove={() => remove(index)}
                >
                    <div className="grid gap-4 md:grid-cols-2">
                        <TextField
                            label="Nombre"
                            value={brand.name ?? ''}
                            onChange={(name) =>
                                update(index, { ...brand, name })
                            }
                        />
                        <MediaPicker
                            label="Imagen"
                            media={media}
                            value={brand.media_id ?? null}
                            showInlineLibrary={false}
                            libraryDescription="Selecciona una imagen para esta marca."
                            onChange={(media_id) =>
                                update(index, { ...brand, media_id })
                            }
                        />
                    </div>
                </ListCard>
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
                        className="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm transition outline-none focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900"
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

function MiniPreview({
    section,
    media,
    heroSlideIndex,
}: {
    section: Section;
    media: MediaOption[];
    heroSlideIndex: number;
}) {
    const meta = sectionMeta(section.type);
    const heroSlides = arrayValue<HeroSlide>(section.settings.slides);
    const selectedHeroSlide = Math.min(
        heroSlideIndex,
        Math.max(heroSlides.length - 1, 0),
    );

    return (
        <aside className="xl:sticky xl:top-32 xl:self-start">
            <div className="rounded-lg border border-neutral-200 bg-neutral-50 p-4 dark:border-neutral-800 dark:bg-neutral-900/40">
                <div className="mb-3 flex items-center justify-between gap-2">
                    <div>
                        <h3 className="text-sm font-semibold">Mini preview</h3>
                        <p className="text-xs text-neutral-500">
                            {section.type === 'hero' && heroSlides.length > 0
                                ? `Slide ${selectedHeroSlide + 1} de ${heroSlides.length}`
                                : `Vista ligera de ${meta.label.toLowerCase()}.`}
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
                <PreviewBody
                    section={section}
                    media={media}
                    heroSlideIndex={selectedHeroSlide}
                />
            </div>
        </aside>
    );
}

function HeroMiniPreview({
    settings,
    slide,
    media,
}: {
    settings: SectionSettings;
    slide?: HeroSlide;
    media: MediaOption[];
}) {
    const previewSettings = slide
        ? ({ ...settings, ...slide } as SectionSettings)
        : settings;
    const selectedMedia =
        slide?.media ??
        media.find((item) => item.id === numberValue(previewSettings.media_id));
    const buttons = arrayValue<BuilderButton>(previewSettings.buttons);
    const overlayEnabled = previewSettings.overlay_enabled !== false;
    const overlayColor = isHexColor(text(previewSettings.overlay_color))
        ? text(previewSettings.overlay_color)
        : '#450a0a';
    const overlayOpacity = Math.min(
        Math.max(Number(previewSettings.overlay_opacity ?? 75), 0),
        100,
    );
    const titleColor = text(settings.title_color);

    return (
        <div
            className="relative flex min-h-56 flex-col justify-center overflow-hidden rounded-lg border border-neutral-200 bg-neutral-950 p-4 text-white shadow-inner"
            style={{
                backgroundColor: text(settings.background_color) || '#171717',
            }}
        >
            {selectedMedia?.url && (
                <img
                    src={selectedMedia.url}
                    alt=""
                    className="absolute inset-0 size-full object-cover"
                />
            )}
            {overlayEnabled && (
                <div
                    className="absolute inset-0"
                    style={{
                        backgroundColor: overlayColor,
                        opacity: overlayOpacity / 100,
                    }}
                />
            )}
            <div className="relative z-10">
                <p className="w-fit rounded bg-white/15 px-2 py-1 text-[10px] font-bold uppercase">
                    {text(previewSettings.eyebrow) || 'Etiqueta'}
                </p>
                <h4
                    className="mt-3 text-2xl font-black"
                    style={
                        isHexColor(titleColor)
                            ? { color: titleColor }
                            : undefined
                    }
                >
                    {text(previewSettings.title) || 'Título del hero'}
                </h4>
                <p className="mt-2 line-clamp-3 text-xs text-white/80">
                    {text(previewSettings.subtitle) || 'Subtítulo del hero'}
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
            </div>
        </div>
    );
}

function PreviewBody({
    section,
    media,
    heroSlideIndex,
}: {
    section: Section;
    media: MediaOption[];
    heroSlideIndex: number;
}) {
    if (section.type === 'hero') {
        const slides = arrayValue<HeroSlide>(section.settings.slides);
        const slide = slides[heroSlideIndex] ?? slides[0];

        return (
            <HeroMiniPreview
                settings={section.settings}
                slide={slide}
                media={media}
            />
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
        const brands = arrayValue<BrandValue>(section.settings.brands).map(
            normalizeBrandValue,
        );

        return (
            <PreviewSurface settings={section.settings}>
                <PreviewHeading settings={section.settings} />
                <div className="mt-4 flex flex-wrap justify-center gap-2">
                    {brands.slice(0, 6).map((brand) => (
                        <div
                            key={`${brand.name}-${brand.media_id ?? 'text'}`}
                            className="flex min-h-10 min-w-20 items-center justify-center rounded bg-neutral-200 px-3 py-2 text-[10px] font-semibold text-neutral-600 uppercase"
                        >
                            {brand.media?.url ? (
                                <img
                                    src={brand.media.url}
                                    alt={brand.media.alt ?? brand.name ?? ''}
                                    className="h-7 max-w-24 object-contain"
                                />
                            ) : (
                                brand.name
                            )}
                        </div>
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
                            imagePositionValue(
                                section.settings.image_position,
                            ) === 'left'
                                ? 'sm:order-first'
                                : 'sm:order-last',
                            imagePositionValue(
                                section.settings.image_position,
                            ) === 'background'
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
                    {Array.from({
                        length: Math.min(productIds.length || 4, 8),
                    }).map((_, index) => (
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
                    ))}
                </div>
            </PreviewSurface>
        );
    }

    const areas = arrayValue<string>(section.settings.interest_areas);

    return (
        <PreviewSurface settings={section.settings}>
            <div className="grid gap-4 sm:grid-cols-[0.8fr_1.2fr]">
                <div>
                    <h4 className="text-xl leading-tight font-black">
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
    const titleColor = text(settings.title_color);

    return (
        <div className="text-center">
            <p className="text-[10px] font-bold tracking-wide text-red-800 uppercase">
                {text(settings.eyebrow) || 'Etiqueta'}
            </p>
            <h4
                className="mt-1 text-lg font-black text-neutral-950"
                style={
                    isHexColor(titleColor) ? { color: titleColor } : undefined
                }
            >
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
    compactLibrary = false,
    showInlineLibrary = true,
    selectExistingLabel = 'Seleccionar existente',
    libraryDescription = 'Selecciona una imagen para este hero.',
    onChange,
}: {
    label: string;
    media: MediaOption[];
    value: number | null;
    compactLibrary?: boolean;
    showInlineLibrary?: boolean;
    selectExistingLabel?: string;
    libraryDescription?: string;
    onChange: (value: number | null, media?: MediaOption) => void;
}) {
    const [uploading, setUploading] = useState(false);
    const [libraryOpen, setLibraryOpen] = useState(false);
    const [extraMedia, setExtraMedia] = useState<MediaOption[]>([]);
    const inputRef = useRef<HTMLInputElement>(null);

    const allMedia = [...extraMedia, ...media];
    const recentMedia = compactLibrary ? allMedia.slice(0, 5) : allMedia;
    const selected = allMedia.find((item) => item.id === value);

    const selectMedia = (item: MediaOption, closeLibrary = false) => {
        const next = item.id === value ? undefined : item;
        onChange(next?.id ?? null, next);

        if (closeLibrary) {
            setLibraryOpen(false);
        }
    };

    const mediaButton = (item: MediaOption, closeLibrary = false) => (
        <button
            key={item.id}
            type="button"
            onClick={() => selectMedia(item, closeLibrary)}
            className={cn(
                'flex aspect-square min-w-0 items-center justify-center overflow-hidden rounded-md border bg-neutral-50 p-1 transition-colors dark:bg-neutral-900',
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
    );

    const libraryMediaButton = (item: MediaOption) => (
        <button
            key={item.id}
            type="button"
            onClick={() => selectMedia(item, true)}
            className={cn(
                'group grid h-full min-w-0 grid-rows-[7rem_auto] overflow-hidden rounded-md border bg-white text-left transition-colors dark:bg-neutral-950',
                item.id === value
                    ? 'border-red-800 ring-2 ring-red-800'
                    : 'border-neutral-200 hover:border-neutral-400 dark:border-neutral-800 dark:hover:border-neutral-600',
            )}
            title={item.label}
        >
            <div className="flex h-28 items-center justify-center overflow-hidden bg-neutral-100 p-2 dark:bg-neutral-900">
                <img
                    src={item.url}
                    alt={item.label}
                    className="max-h-full max-w-full object-contain transition-transform duration-200 group-hover:scale-[1.02]"
                />
            </div>
            <div className="flex min-w-0 items-center justify-between gap-2 border-t border-neutral-200 px-3 py-2 dark:border-neutral-800">
                <span className="truncate text-xs font-medium text-neutral-700 dark:text-neutral-300">
                    {item.label}
                </span>
                {item.id === value && (
                    <span className="shrink-0 text-xs font-medium text-red-800 dark:text-red-400">
                        Seleccionada
                    </span>
                )}
            </div>
        </button>
    );

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

            setExtraMedia((previous) => [newMedia, ...previous]);
            onChange(data.id, newMedia);
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
                            onClick={() => onChange(null, undefined)}
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
                {!showInlineLibrary && allMedia.length > 0 && (
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        onClick={() => setLibraryOpen(true)}
                    >
                        <ImageIcon className="size-4" />
                        {selectExistingLabel}
                    </Button>
                )}
            </div>

            {showInlineLibrary && allMedia.length > 0 && (
                <div className="grid gap-2">
                    <div
                        className={cn(
                            'grid grid-cols-5 gap-2 rounded-lg border border-neutral-200 bg-white p-2 dark:border-neutral-800 dark:bg-neutral-950',
                            !compactLibrary && 'max-h-52 overflow-y-auto',
                        )}
                    >
                        {recentMedia.map((item) => mediaButton(item))}
                    </div>

                    {compactLibrary && allMedia.length > 5 && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={() => setLibraryOpen(true)}
                        >
                            <ImageIcon className="size-4" />
                            Ver mas
                        </Button>
                    )}
                </div>
            )}

            <Dialog open={libraryOpen} onOpenChange={setLibraryOpen}>
                <DialogContent className="flex max-h-[90vh] min-h-0 flex-col overflow-hidden sm:max-w-6xl">
                    <DialogHeader>
                        <DialogTitle>Biblioteca de imágenes</DialogTitle>
                        <DialogDescription>
                            {libraryDescription}
                        </DialogDescription>
                    </DialogHeader>
                    <div className="grid min-h-0 flex-1 grid-cols-2 gap-3 overflow-y-auto pr-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        {allMedia.map(libraryMediaButton)}
                    </div>
                </DialogContent>
            </Dialog>
        </div>
    );
}

function TextField({
    label,
    value,
    disabled,
    placeholder,
    onChange,
}: {
    label: string;
    value: string;
    disabled?: boolean;
    onChange: (value: string) => void;
    placeholder?: string;
}) {
    return (
        <div className="grid gap-1.5">
            <Label>{label}</Label>
            <Input
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                placeholder={placeholder}
            />
        </div>
    );
}

function RangeField({
    label,
    value,
    min,
    max,
    disabled,
    onChange,
}: {
    label: string;
    value: number;
    min: number;
    max: number;
    disabled?: boolean;
    onChange: (value: number) => void;
}) {
    return (
        <div className="grid gap-1.5">
            <div className="flex items-center justify-between gap-2">
                <Label>{label}</Label>
                <span className="text-xs font-medium text-neutral-500">
                    {value}%
                </span>
            </div>
            <Input
                type="range"
                min={min}
                max={max}
                value={value}
                disabled={disabled}
                onChange={(event) => onChange(Number(event.target.value))}
                className="cursor-pointer disabled:cursor-not-allowed disabled:opacity-50"
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
                className="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm transition outline-none focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900"
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
                className="min-h-28 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm transition outline-none focus:border-red-700 focus:ring-2 focus:ring-red-700/10 dark:border-neutral-700 dark:bg-neutral-900"
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
        return `${arrayValue<BrandValue>(settings.brands).length} marcas`;
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

    if (section.type === 'page_header') {
        return text(settings.title) || 'Encabezado de página';
    }

    if (section.type === 'rich_text') {
        const plain = text(settings.html)
            .replace(/<[^>]+>/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        return plain ? plain.slice(0, 60) : 'Texto enriquecido';
    }

    if (section.type === 'contact_info') {
        return (
            text(settings.email) || text(settings.phone) || 'Datos de contacto'
        );
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
    return contentWidthValue(value) === 'full'
        ? 'Ancho completo'
        : 'Contenedor';
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

function normalizeBrandValue(value: BrandValue): BuilderBrand {
    if (typeof value === 'string') {
        return { name: value, media_id: null };
    }

    return {
        name: value.name ?? '',
        media_id: value.media_id ?? null,
        media: value.media,
    };
}

function isHexColor(value: string): boolean {
    return /^#(?:[0-9a-fA-F]{3}){1,2}$/.test(value);
}

function newExtraSection(type: string): Section {
    return {
        id: -Date.now() - Math.floor(Math.random() * 1000),
        type,
        settings: sectionDefaults(type),
    };
}

function sectionDefaults(type: string): SectionSettings {
    const base = {
        background_color: '#ffffff',
        content_width: 'container',
    };

    switch (type) {
        case 'hero':
            return {
                ...base,
                background_color: '#111827',
                slides: [],
                eyebrow: '',
                title: 'Nueva portada',
                subtitle: '',
                buttons: [],
            };
        case 'specialty_grid':
            return { ...base, title: 'Especialidades', items: [] };
        case 'feature_cards':
            return { ...base, items: [] };
        case 'brand_strip':
            return { ...base, eyebrow: '', title: 'Marcas', brands: [] };
        case 'inquiry_form':
            return {
                ...base,
                title: 'Escríbenos',
                text: '',
                phone: '',
                email: '',
                interest_areas: [],
                media_id: null,
            };
        case 'recommended_products':
            return {
                ...base,
                eyebrow: 'Recomendados',
                title: 'Productos recomendados',
                subtitle: '',
                product_ids: [],
                display_type: 'grid',
                columns: 4,
            };
        case 'page_header':
            return {
                ...base,
                background_color: '#0f172a',
                eyebrow: '',
                title: 'Título de la página',
                subtitle: '',
            };
        case 'rich_text':
            return { ...base, html: '<p></p>' };
        case 'contact_info':
            return {
                ...base,
                title: 'Información de contacto',
                phone: '',
                email: '',
                address: '',
                hours: '',
                map_url: '',
            };
        default:
            return {
                ...base,
                eyebrow: 'Nuevo bloque',
                title: 'Banner imagen',
                text: '',
                media_id: null,
                button_label: 'Ver mas',
                button_url: '#',
                image_position: 'right',
            };
    }
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
