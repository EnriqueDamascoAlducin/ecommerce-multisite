import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    Mail,
    Phone,
    Sparkles,
    Waves,
    Zap,
} from 'lucide-react';
import type { ReactNode } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProductCard, type ProductCardData } from './product-card';

type CmsMedia = { id: number; url: string; alt: string | null } | null;
type CmsSection = {
    id: number;
    type: string;
    settings: Record<string, unknown> & { media?: CmsMedia };
};
type CmsPage = { title: string; sections: CmsSection[] } | null;
type CmsItem = {
    title?: string;
    text?: string;
    icon?: string;
    link?: string;
    highlighted?: boolean;
    media?: CmsMedia;
    media_id?: number | null;
    cta_label?: string;
    cta_url?: string;
};
type CmsButton = { label?: string; url?: string };

export default function StorefrontHome({
    featured,
    contentPage,
}: {
    featured: ProductCardData[];
    contentPage?: CmsPage;
}) {
    const { store } = usePage().props;

    return (
        <>
            <Head
                title={
                    contentPage?.title ?? (store ? store.store.name : 'Inicio')
                }
            />

            {contentPage && contentPage.sections.length > 0 ? (
                <div className="-mx-4 -my-8">
                    {contentPage.sections.map((section) => (
                        <HomeSection
                            key={section.id}
                            section={section}
                            featured={featured}
                        />
                    ))}
                </div>
            ) : (
                <FallbackHome featured={featured} />
            )}
        </>
    );
}

function HomeSection({
    section,
    featured,
}: {
    section: CmsSection;
    featured: ProductCardData[];
}) {
    if (section.type === 'hero') return <HeroSection section={section} />;
    if (section.type === 'specialty_grid')
        return <SpecialtyGrid section={section} />;
    if (section.type === 'feature_cards')
        return <FeatureCards section={section} />;
    if (section.type === 'brand_strip') return <BrandStrip section={section} />;
    if (section.type === 'inquiry_form')
        return <InquirySection section={section} />;
    if (section.type === 'text_image') return <TextImage section={section} />;
    if (section.type === 'gallery') return <GallerySection section={section} />;
    if (section.type === 'cta_banner') return <CtaBanner section={section} />;
    if (section.type === 'featured_products')
        return <FeaturedProducts products={featured} />;

    return null;
}

function HeroSection({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const buttons = arrayValue<CmsButton>(settings.buttons);
    const image = settings.media;

    return (
        <section className="relative min-h-[32rem] overflow-hidden bg-neutral-950 text-white">
            {image && (
                <img
                    src={image.url}
                    alt={image.alt ?? stringValue(settings.title)}
                    className="absolute inset-0 h-full w-full object-cover"
                />
            )}
            <div className="absolute inset-0 bg-linear-to-r from-red-950/95 via-red-950/75 to-slate-950/85" />
            <div className="relative mx-auto flex min-h-[32rem] max-w-6xl items-center px-4 py-20">
                <div className="max-w-2xl">
                    {stringValue(settings.eyebrow) && (
                        <p className="mb-4 w-fit rounded bg-white/15 px-3 py-1 text-xs font-bold tracking-wide uppercase">
                            {stringValue(settings.eyebrow)}
                        </p>
                    )}
                    <h1 className="text-4xl font-black tracking-normal md:text-6xl">
                        {stringValue(settings.title)}
                    </h1>
                    <p className="mt-5 max-w-xl text-base leading-7 text-white/85">
                        {stringValue(settings.subtitle)}
                    </p>
                    {buttons.length > 0 && (
                        <div className="mt-8 flex flex-wrap gap-3">
                            {buttons.map((button, index) => (
                                <Button
                                    key={`${button.label}-${index}`}
                                    asChild
                                    variant={
                                        index === 0 ? 'secondary' : 'outline'
                                    }
                                    className={
                                        index === 0
                                            ? 'bg-white text-red-950 hover:bg-white/90'
                                            : 'border-white text-white hover:bg-white hover:text-red-950'
                                    }
                                >
                                    <Link href={button.url ?? '#'}>
                                        {button.label ?? 'Ver mas'}
                                    </Link>
                                </Button>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </section>
    );
}

function SpecialtyGrid({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const items = arrayValue<CmsItem>(settings.items);

    return (
        <section className="bg-neutral-50 px-4 py-20 dark:bg-neutral-950">
            <div className="mx-auto max-w-6xl">
                <SectionHeading
                    eyebrow={stringValue(settings.eyebrow)}
                    title={stringValue(settings.title)}
                />
                <div className="mt-10 grid auto-rows-[12rem] grid-cols-1 gap-5 md:grid-cols-4">
                    {items.map((item, index) => (
                        <Link
                            key={`${item.title}-${index}`}
                            href={item.link ?? '#'}
                            className={[
                                'group relative overflow-hidden rounded-lg border p-7 shadow-sm transition hover:-translate-y-0.5 hover:shadow-md',
                                item.highlighted
                                    ? 'bg-red-800 text-white'
                                    : 'bg-white text-neutral-950 dark:bg-neutral-900 dark:text-neutral-100',
                                item.media
                                    ? 'md:col-span-2'
                                    : index === 0
                                      ? 'md:col-span-2'
                                      : '',
                            ].join(' ')}
                        >
                            {item.media && (
                                <>
                                    <img
                                        src={item.media.url}
                                        alt={item.media.alt ?? item.title ?? ''}
                                        className="absolute inset-0 h-full w-full object-cover"
                                    />
                                    <div className="absolute inset-0 bg-linear-to-t from-slate-950/85 to-slate-950/15" />
                                </>
                            )}
                            <div className="relative flex h-full flex-col justify-between">
                                <IconBadge
                                    icon={item.icon}
                                    dark={
                                        item.highlighted || Boolean(item.media)
                                    }
                                />
                                <div>
                                    <h3 className="font-bold">{item.title}</h3>
                                    {item.text && (
                                        <p className="mt-2 max-w-sm text-sm opacity-75">
                                            {item.text}
                                        </p>
                                    )}
                                </div>
                                <ArrowRight className="absolute top-0 right-0 size-4 opacity-60 transition group-hover:translate-x-1" />
                            </div>
                        </Link>
                    ))}
                </div>
            </div>
        </section>
    );
}

function FeatureCards({ section }: { section: CmsSection }) {
    const items = arrayValue<CmsItem>(section.settings.items);

    return (
        <section className="bg-neutral-100 px-4 py-20 dark:bg-neutral-900">
            <div className="mx-auto grid max-w-6xl gap-6 md:grid-cols-2">
                {items.map((item, index) => (
                    <article
                        key={`${item.title}-${index}`}
                        className="overflow-hidden rounded-lg bg-white shadow-lg dark:bg-neutral-950"
                    >
                        {item.media && (
                            <img
                                src={item.media.url}
                                alt={item.media.alt ?? item.title ?? ''}
                                className="h-64 w-full object-cover"
                            />
                        )}
                        <div className="p-8">
                            <h3 className="text-2xl font-bold text-slate-900 dark:text-white">
                                {item.title}
                            </h3>
                            <p className="mt-3 text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                                {item.text}
                            </p>
                            {item.cta_label && (
                                <Button
                                    asChild
                                    variant="link"
                                    className="mt-4 px-0 text-red-800"
                                >
                                    <Link href={item.cta_url ?? '#'}>
                                        {item.cta_label}{' '}
                                        <ArrowRight className="size-4" />
                                    </Link>
                                </Button>
                            )}
                        </div>
                    </article>
                ))}
            </div>
        </section>
    );
}

function BrandStrip({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const brands = arrayValue<string>(settings.brands);

    return (
        <section className="bg-neutral-50 px-4 py-18 dark:bg-neutral-950">
            <div className="mx-auto max-w-6xl">
                <SectionHeading
                    eyebrow={stringValue(settings.eyebrow)}
                    title={stringValue(settings.title)}
                />
                <div className="mt-10 flex flex-wrap justify-center gap-5">
                    {brands.map((brand) => (
                        <div
                            key={brand}
                            className="min-w-36 rounded bg-neutral-200 px-6 py-3 text-center text-xs font-semibold tracking-wide text-neutral-600 dark:bg-neutral-800 dark:text-neutral-300"
                        >
                            {brand}
                        </div>
                    ))}
                </div>
            </div>
        </section>
    );
}

function InquirySection({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const areas = arrayValue<string>(settings.interest_areas);
    const { store } = usePage().props;
    const action = `${store?.pathPrefix ? `/${store.pathPrefix}` : ''}/consulta`;

    return (
        <section className="bg-white px-4 py-20 dark:bg-neutral-950">
            <div className="mx-auto grid max-w-6xl gap-10 md:grid-cols-[0.8fr_1.2fr] md:items-start">
                <div>
                    <h2 className="max-w-md text-4xl font-black tracking-normal">
                        {stringValue(settings.title)}
                    </h2>
                    <p className="mt-5 max-w-md text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                        {stringValue(settings.text)}
                    </p>
                    <div className="mt-8 space-y-4 text-sm">
                        {stringValue(settings.phone) && (
                            <ContactLine
                                icon={<Phone className="size-4" />}
                                label="Call our experts"
                                value={stringValue(settings.phone)}
                            />
                        )}
                        {stringValue(settings.email) && (
                            <ContactLine
                                icon={<Mail className="size-4" />}
                                label="Email support"
                                value={stringValue(settings.email)}
                            />
                        )}
                    </div>
                </div>
                <Form
                    action={action}
                    method="post"
                    className="rounded-lg border border-neutral-200 p-7 shadow-sm dark:border-neutral-800"
                >
                    {({ processing, errors }) => (
                        <div className="grid gap-4">
                            <div className="grid gap-4 md:grid-cols-2">
                                <Field
                                    name="name"
                                    label="Full name"
                                    error={errors.name}
                                />
                                <Field
                                    name="email"
                                    label="Email address"
                                    type="email"
                                    error={errors.email}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="interest_area">
                                    Interest area
                                </Label>
                                <select
                                    id="interest_area"
                                    name="interest_area"
                                    className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                >
                                    {areas.map((area) => (
                                        <option key={area} value={area}>
                                            {area}
                                        </option>
                                    ))}
                                </select>
                                <InputError message={errors.interest_area} />
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="message">Message</Label>
                                <textarea
                                    id="message"
                                    name="message"
                                    className="min-h-32 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-900"
                                    placeholder="How can we help you?"
                                />
                                <InputError message={errors.message} />
                            </div>
                            <Button
                                disabled={processing}
                                className="bg-red-800 hover:bg-red-900"
                            >
                                Send inquiry
                            </Button>
                        </div>
                    )}
                </Form>
            </div>
        </section>
    );
}

function TextImage({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const image = settings.media;

    return (
        <section className="bg-white px-4 py-20 dark:bg-neutral-950">
            <div className="mx-auto grid max-w-6xl gap-10 md:grid-cols-2 md:items-center">
                <div>
                    <SectionHeading
                        eyebrow={stringValue(settings.eyebrow)}
                        title={stringValue(settings.title)}
                    />
                    <p className="mt-6 text-sm leading-7 text-neutral-600 dark:text-neutral-400">
                        {stringValue(settings.text)}
                    </p>
                </div>
                {image && (
                    <img
                        src={image.url}
                        alt={image.alt ?? stringValue(settings.title)}
                        className="aspect-[4/3] w-full rounded-lg object-cover shadow-lg"
                    />
                )}
            </div>
        </section>
    );
}

function GallerySection({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const items = arrayValue<CmsItem>(settings.items);

    return (
        <section className="bg-neutral-50 px-4 py-20 dark:bg-neutral-950">
            <div className="mx-auto max-w-6xl">
                <SectionHeading
                    eyebrow={stringValue(settings.eyebrow)}
                    title={stringValue(settings.title)}
                />
                <div className="mt-10 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {items.map((item, index) => (
                        <figure
                            key={`${item.title}-${index}`}
                            className="overflow-hidden rounded-lg bg-white shadow-sm dark:bg-neutral-900"
                        >
                            {item.media && (
                                <img
                                    src={item.media.url}
                                    alt={item.media.alt ?? item.title ?? ''}
                                    className="aspect-video w-full object-cover"
                                />
                            )}
                            {item.title && (
                                <figcaption className="p-4 text-sm font-medium">
                                    {item.title}
                                </figcaption>
                            )}
                        </figure>
                    ))}
                </div>
            </div>
        </section>
    );
}

function CtaBanner({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const buttons = arrayValue<CmsButton>(settings.buttons);

    return (
        <section className="bg-red-900 px-4 py-16 text-white">
            <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-6">
                <div>
                    <p className="text-xs font-bold tracking-wide text-white/70 uppercase">
                        {stringValue(settings.eyebrow)}
                    </p>
                    <h2 className="mt-2 text-3xl font-black tracking-normal">
                        {stringValue(settings.title)}
                    </h2>
                    <p className="mt-3 max-w-2xl text-sm leading-6 text-white/80">
                        {stringValue(settings.text)}
                    </p>
                </div>
                <div className="flex flex-wrap gap-3">
                    {buttons.map((button, index) => (
                        <Button
                            key={`${button.label}-${index}`}
                            asChild
                            variant="secondary"
                        >
                            <Link href={button.url ?? '#'}>
                                {button.label ?? 'Ver mas'}
                            </Link>
                        </Button>
                    ))}
                </div>
            </div>
        </section>
    );
}

function FeaturedProducts({ products }: { products: ProductCardData[] }) {
    return (
        <section className="bg-neutral-50 px-4 py-16 dark:bg-neutral-950">
            <div className="mx-auto max-w-6xl">
                <h2 className="mb-6 text-2xl font-bold">
                    Productos destacados
                </h2>
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                    {products.map((product) => (
                        <ProductCard key={product.sku} product={product} />
                    ))}
                </div>
            </div>
        </section>
    );
}

function FallbackHome({ featured }: { featured: ProductCardData[] }) {
    const { store } = usePage().props;

    return (
        <>
            <section className="mb-10 rounded-xl bg-neutral-100 px-6 py-16 text-center dark:bg-neutral-900">
                <h1 className="text-3xl font-bold">
                    {store ? `Bienvenido a ${store.store.name}` : 'Bienvenido'}
                </h1>
                <p className="mx-auto mt-2 max-w-md text-neutral-500 dark:text-neutral-400">
                    Descubre nuestros productos.
                </p>
            </section>
            <section>
                <h2 className="mb-4 text-xl font-semibold">Destacados</h2>
                {featured.length === 0 ? (
                    <p className="py-8 text-center text-neutral-500">
                        Aun no hay productos en esta tienda.
                    </p>
                ) : (
                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {featured.map((product) => (
                            <ProductCard key={product.sku} product={product} />
                        ))}
                    </div>
                )}
            </section>
        </>
    );
}

function SectionHeading({
    eyebrow,
    title,
}: {
    eyebrow: string;
    title: string;
}) {
    return (
        <div className="text-center">
            {eyebrow && (
                <p className="mb-2 text-xs font-bold tracking-[0.2em] text-red-800 uppercase">
                    {eyebrow}
                </p>
            )}
            <h2 className="text-3xl font-black tracking-normal">{title}</h2>
            <div className="mx-auto mt-5 h-1 w-24 rounded bg-red-800" />
        </div>
    );
}

function IconBadge({ icon, dark }: { icon?: string; dark?: boolean }) {
    const className = dark
        ? 'bg-white/15 text-white'
        : 'bg-red-50 text-red-800';
    const Icon =
        icon === 'zap'
            ? Zap
            : icon === 'waves'
              ? Waves
              : icon === 'sparkles'
                ? Sparkles
                : Activity;

    return (
        <span
            className={`flex size-11 items-center justify-center rounded-md ${className}`}
        >
            <Icon className="size-5" />
        </span>
    );
}

function ContactLine({
    icon,
    label,
    value,
}: {
    icon: ReactNode;
    label: string;
    value: string;
}) {
    return (
        <div className="flex items-center gap-3">
            <span className="flex size-10 items-center justify-center rounded-full bg-red-50 text-red-800">
                {icon}
            </span>
            <div>
                <p className="text-xs text-neutral-500">{label}</p>
                <p>{value}</p>
            </div>
        </div>
    );
}

function Field({
    name,
    label,
    type = 'text',
    error,
}: {
    name: string;
    label: string;
    type?: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} name={name} type={type} />
            <InputError message={error} />
        </div>
    );
}

function stringValue(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function arrayValue<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
}
