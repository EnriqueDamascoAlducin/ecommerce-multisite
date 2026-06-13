import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    Activity,
    ArrowRight,
    ChevronLeft,
    ChevronRight,
    Clock,
    Mail,
    MapPin,
    Phone,
    Sparkles,
    Waves,
    Zap,
} from 'lucide-react';
import { useEffect, useState, type CSSProperties, type ReactNode } from 'react';
import InputError from '@/components/input-error';
import { ProductCarousel } from '@/components/storefront/product-carousel';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ProductCard, type ProductCardData } from '@/pages/storefront/product-card';

export type CmsMedia = { id: number; url: string; alt: string | null } | null;
export type CmsSection = {
    id: number;
    type: string;
    settings: Record<string, unknown> & {
        media?: CmsMedia;
        products?: ProductCardData[];
    };
};
export type CmsPage = { title: string; sections: CmsSection[] } | null;
export type CmsItem = {
    title?: string;
    text?: string;
    icon?: string;
    link?: string;
    highlighted?: boolean;
    wide?: boolean;
    media?: CmsMedia;
    media_id?: number | null;
    cta_label?: string;
    cta_url?: string;
};
export type CmsButton = { label?: string; url?: string };
export type CmsHeroSlide = {
    media?: CmsMedia;
    media_id?: number | null;
    eyebrow?: string;
    title?: string;
    subtitle?: string;
    buttons?: CmsButton[];
};

export function SectionRenderer({
    section,
}: {
    section: CmsSection;
}) {
    if (section.type === 'hero') return <HeroSection section={section} />;
    if (section.type === 'specialty_grid')
        return <SpecialtyGrid section={section} />;
    if (section.type === 'feature_cards')
        return <FeatureCards section={section} />;
    if (section.type === 'brand_strip') return <BrandStrip section={section} />;
    if (section.type === 'inquiry_form')
        return <InquirySection section={section} />;
    if (section.type === 'image_banner')
        return <ImageBanner section={section} />;
    if (section.type === 'recommended_products')
        return <RecommendedProducts section={section} />;
    if (section.type === 'page_header')
        return <PageHeaderSection section={section} />;
    if (section.type === 'rich_text')
        return <RichTextSection section={section} />;
    if (section.type === 'contact_info')
        return <ContactInfoSection section={section} />;

    return null;
}

function HeroSection({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const slides = heroSlides(settings);
    const [activeSlide, setActiveSlide] = useState(0);
    const hasCarousel = slides.length > 1;
    const slide = slides[activeSlide] ?? slides[0];
    const buttons = arrayValue<CmsButton>(slide?.buttons);
    const image = slide?.media ?? null;

    useEffect(() => {
        if (!hasCarousel) {
            return;
        }

        const timeout = window.setTimeout(() => {
            setActiveSlide((current) => (current + 1) % slides.length);
        }, 6500);

        return () => window.clearTimeout(timeout);
    }, [hasCarousel, slides.length, activeSlide]);

    useEffect(() => {
        setActiveSlide((current) => Math.min(current, Math.max(slides.length - 1, 0)));
    }, [slides.length]);

    const goToSlide = (index: number) => {
        setActiveSlide((index + slides.length) % slides.length);
    };

    return (
        <section
            className="relative min-h-[32rem] overflow-hidden bg-neutral-950 text-white"
            style={sectionBackgroundStyle(settings)}
        >
            {image && (
                <img
                    src={image.url}
                    alt={image.alt ?? stringValue(slide?.title)}
                    className="absolute inset-0 h-full w-full object-cover transition-opacity duration-500"
                />
            )}
            <div className="absolute inset-0 bg-linear-to-r from-red-950/95 via-red-950/75 to-slate-950/85" />
            <div
                className={`${sectionContentWidthClass(settings)} relative flex min-h-[32rem] items-center px-6 py-20 sm:px-8 lg:px-16 xl:px-24`}
            >
                <div className="max-w-2xl">
                    {stringValue(slide?.eyebrow) && (
                        <p className="mb-4 w-fit rounded bg-white/15 px-3 py-1 text-xs font-bold tracking-wide uppercase">
                            {stringValue(slide?.eyebrow)}
                        </p>
                    )}
                    <h1 className="text-4xl font-black tracking-normal md:text-6xl">
                        {stringValue(slide?.title)}
                    </h1>
                    <p className="mt-5 max-w-xl text-base leading-7 text-white/85">
                        {stringValue(slide?.subtitle)}
                    </p>
                    {buttons.length > 0 && (
                        <div className="mt-8 flex flex-wrap gap-3">
                            {buttons.slice(0, 2).map((button, index) => (
                                <Link
                                    key={`${button.label}-${index}`}
                                    className={
                                        index === 0
                                            ? 'inline-flex h-12 items-center justify-center rounded-md bg-white px-7 text-xs font-black tracking-wide text-red-950 uppercase shadow-sm transition hover:bg-white/90'
                                            : 'inline-flex h-12 items-center justify-center rounded-md border-2 border-white bg-transparent px-7 text-xs font-black tracking-wide text-white uppercase transition hover:bg-white hover:text-red-950'
                                    }
                                    href={button.url ?? '#'}
                                >
                                    {button.label ?? 'Ver mas'}
                                </Link>
                            ))}
                        </div>
                    )}
                </div>
            </div>
            {hasCarousel && (
                <>
                    <div className="absolute right-6 bottom-6 left-6 z-10 flex items-center justify-between gap-4">
                        <div className="flex items-center gap-2">
                            {slides.map((item, index) => (
                                <button
                                    key={`${item.title}-${index}`}
                                    type="button"
                                    aria-label={`Ir al slide ${index + 1}`}
                                    onClick={() => goToSlide(index)}
                                    className={`h-2 rounded-full transition-all ${
                                        index === activeSlide
                                            ? 'w-9 bg-white'
                                            : 'w-2 bg-white/45 hover:bg-white/75'
                                    }`}
                                />
                            ))}
                        </div>
                        <div className="flex items-center gap-2">
                            <button
                                type="button"
                                aria-label="Slide anterior"
                                onClick={() => goToSlide(activeSlide - 1)}
                                className="flex size-10 items-center justify-center rounded-full border border-white/30 bg-white/10 text-white backdrop-blur transition hover:bg-white hover:text-red-950"
                            >
                                <ChevronLeft className="size-5" />
                            </button>
                            <button
                                type="button"
                                aria-label="Slide siguiente"
                                onClick={() => goToSlide(activeSlide + 1)}
                                className="flex size-10 items-center justify-center rounded-full border border-white/30 bg-white/10 text-white backdrop-blur transition hover:bg-white hover:text-red-950"
                            >
                                <ChevronRight className="size-5" />
                            </button>
                        </div>
                    </div>
                </>
            )}
        </section>
    );
}

function SpecialtyGrid({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const items = arrayValue<CmsItem>(settings.items);

    return (
        <section
            className="bg-white px-4 py-20 dark:bg-neutral-950"
            style={sectionBackgroundStyle(settings)}
        >
            <div className={sectionContentWidthClass(settings)}>
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
                                isWideSpecialtyCard(item, index)
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
    const settings = section.settings;
    const items = arrayValue<CmsItem>(settings.items);

    return (
        <section
            className="bg-neutral-100 px-4 py-20 dark:bg-neutral-900"
            style={sectionBackgroundStyle(settings)}
        >
            <div
                className={`${sectionContentWidthClass(settings)} grid gap-6 md:grid-cols-2`}
            >
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
        <section
            className="bg-white px-4 py-18 dark:bg-neutral-950"
            style={sectionBackgroundStyle(settings)}
        >
            <div className={sectionContentWidthClass(settings)}>
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
        <section
            className="bg-white px-4 py-20 text-neutral-950 dark:bg-neutral-950 dark:text-neutral-50 md:py-24"
            style={sectionBackgroundStyle(settings)}
        >
            <div
                className={`${sectionContentWidthClass(settings)} grid gap-12 lg:grid-cols-[0.75fr_1.25fr] lg:items-start`}
            >
                <div className="lg:pt-2">
                    <h2 className="max-w-sm text-4xl leading-[1.05] font-black tracking-normal md:text-5xl">
                        {stringValue(settings.title)}
                    </h2>
                    <p className="mt-8 max-w-md text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                        {stringValue(settings.text)}
                    </p>
                    <div className="mt-10 space-y-5 text-sm">
                        {stringValue(settings.phone) && (
                            <ContactLine
                                icon={<Phone className="size-4" />}
                                label="Call our Experts"
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
                    className="rounded-xl border border-red-900/15 bg-white p-7 shadow-none dark:border-red-200/15 dark:bg-neutral-900 md:p-9"
                >
                    {({ processing, errors }) => (
                        <div className="grid gap-5">
                            <div className="grid gap-5 md:grid-cols-2">
                                <Field
                                    name="name"
                                    label="Full Name"
                                    placeholder="John Doe"
                                    error={errors.name}
                                />
                                <Field
                                    name="email"
                                    label="Email Address"
                                    type="email"
                                    placeholder="john@clinic.com"
                                    error={errors.email}
                                />
                            </div>
                            <div className="grid gap-2">
                                <Label
                                    htmlFor="interest_area"
                                    className="text-xs font-medium text-neutral-900 dark:text-neutral-100"
                                >
                                    Interest area
                                </Label>
                                <select
                                    id="interest_area"
                                    name="interest_area"
                                    className="h-12 rounded-md border border-red-900/20 bg-white px-3 text-sm text-neutral-800 outline-none transition focus:border-red-800 focus:ring-2 focus:ring-red-800/10 dark:border-red-200/20 dark:bg-neutral-950 dark:text-neutral-100"
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
                                <Label
                                    htmlFor="message"
                                    className="text-xs font-medium text-neutral-900 dark:text-neutral-100"
                                >
                                    Message
                                </Label>
                                <textarea
                                    id="message"
                                    name="message"
                                    className="min-h-36 rounded-md border border-red-900/20 bg-white px-3 py-3 text-sm text-neutral-800 outline-none transition placeholder:text-neutral-400 focus:border-red-800 focus:ring-2 focus:ring-red-800/10 dark:border-red-200/20 dark:bg-neutral-950 dark:text-neutral-100"
                                    placeholder="How can we help you?"
                                />
                                <InputError message={errors.message} />
                            </div>
                            <Button
                                disabled={processing}
                                className="mt-3 h-12 rounded-md bg-red-800 text-xs font-black tracking-wide uppercase text-white hover:bg-red-900"
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

function ImageBanner({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const image = settings.media;
    const imagePosition = imagePositionValue(settings.image_position);
    const buttonLabel = stringValue(settings.button_label);
    const buttonUrl = stringValue(settings.button_url) || '#';
    const usesBackground = imagePosition === 'background';

    return (
        <section
            className={`relative overflow-hidden px-4 py-16 ${
                usesBackground
                    ? 'bg-neutral-950 text-white'
                    : 'bg-white text-neutral-950 dark:bg-neutral-950 dark:text-neutral-50'
            }`}
            style={sectionBackgroundStyle(settings)}
        >
            {usesBackground && image && (
                <>
                    <img
                        src={image.url}
                        alt={image.alt ?? stringValue(settings.title)}
                        className="absolute inset-0 h-full w-full object-cover"
                    />
                    <div className="absolute inset-0 bg-linear-to-r from-red-950/90 via-red-950/70 to-slate-950/80" />
                </>
            )}
            <div
                className={`${sectionContentWidthClass(settings)} relative grid gap-8 md:grid-cols-2 md:items-center`}
            >
                {!usesBackground && image && (
                    <img
                        src={image.url}
                        alt={image.alt ?? stringValue(settings.title)}
                        className={`h-80 w-full rounded-lg object-cover shadow-lg ${
                            imagePosition === 'left'
                                ? 'md:order-first'
                                : 'md:order-last'
                        }`}
                    />
                )}
                <div className={usesBackground ? 'max-w-2xl py-16' : ''}>
                    {stringValue(settings.eyebrow) && (
                        <p className="mb-3 text-xs font-bold tracking-[0.2em] text-red-800 uppercase dark:text-red-300">
                            {stringValue(settings.eyebrow)}
                        </p>
                    )}
                    <h2 className="text-3xl font-black tracking-normal md:text-5xl">
                        {stringValue(settings.title)}
                    </h2>
                    <p
                        className={`mt-5 max-w-xl text-sm leading-7 ${
                            usesBackground
                                ? 'text-white/85'
                                : 'text-neutral-600 dark:text-neutral-400'
                        }`}
                    >
                        {stringValue(settings.text)}
                    </p>
                    {buttonLabel && (
                        <Button
                            asChild
                            className="mt-7 rounded-md bg-red-800 px-7 text-xs font-black tracking-wide uppercase text-white hover:bg-red-900"
                        >
                            <Link href={buttonUrl}>
                                {buttonLabel}{' '}
                                <ArrowRight className="size-4" />
                            </Link>
                        </Button>
                    )}
                </div>
            </div>
        </section>
    );
}

function RecommendedProducts({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const products = arrayValue<ProductCardData>(settings.products);
    const columns = columnsValue(settings.columns);
    const displayType = displayTypeValue(settings.display_type);

    if (products.length === 0) {
        return null;
    }

    return (
        <section
            className="bg-white px-4 py-20 text-neutral-950 dark:bg-neutral-950 dark:text-neutral-50"
            style={sectionBackgroundStyle(settings)}
        >
            <div className={sectionContentWidthClass(settings)}>
                <SectionHeading
                    eyebrow={stringValue(settings.eyebrow)}
                    title={stringValue(settings.title)}
                />
                {stringValue(settings.subtitle) && (
                    <p className="mx-auto mt-5 max-w-2xl text-center text-sm leading-6 text-neutral-600 dark:text-neutral-400">
                        {stringValue(settings.subtitle)}
                    </p>
                )}
                {displayType === 'carousel' ? (
                    <ProductCarousel
                        products={products}
                        eyebrow={
                            stringValue(settings.eyebrow) ||
                            'Selección destacada'
                        }
                        title=""
                        compactHeading
                        showHeading={false}
                        className="mt-10"
                    />
                ) : (
                    <div
                        className={`mt-10 grid gap-5 sm:grid-cols-2 ${
                            columns === '3'
                                ? 'lg:grid-cols-3'
                                : 'lg:grid-cols-4'
                        }`}
                    >
                        {products.map((product) => (
                            <ProductCard key={product.sku} product={product} />
                        ))}
                    </div>
                )}
            </div>
        </section>
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
        <div className="flex items-center gap-4">
            <span className="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-200">
                {icon}
            </span>
            <div>
                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                    {label}
                </p>
                <p className="mt-0.5 text-sm text-neutral-800 dark:text-neutral-100">
                    {value}
                </p>
            </div>
        </div>
    );
}

function Field({
    name,
    label,
    type = 'text',
    placeholder,
    error,
}: {
    name: string;
    label: string;
    type?: string;
    placeholder?: string;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label
                htmlFor={name}
                className="text-xs font-medium text-neutral-900 dark:text-neutral-100"
            >
                {label}
            </Label>
            <Input
                id={name}
                name={name}
                type={type}
                placeholder={placeholder}
                className="h-12 border-red-900/20 bg-white text-sm focus-visible:border-red-800 focus-visible:ring-red-800/10 dark:border-red-200/20 dark:bg-neutral-950"
            />
            <InputError message={error} />
        </div>
    );
}

function PageHeaderSection({ section }: { section: CmsSection }) {
    const settings = section.settings;

    return (
        <section
            className="bg-neutral-950 px-4 py-20 text-white"
            style={sectionBackgroundStyle(settings)}
        >
            <div className={`${sectionContentWidthClass(settings)} text-center`}>
                {stringValue(settings.eyebrow) && (
                    <p className="mb-3 text-xs font-bold tracking-wide text-white/70 uppercase">
                        {stringValue(settings.eyebrow)}
                    </p>
                )}
                <h1 className="text-4xl font-black tracking-tight md:text-5xl">
                    {stringValue(settings.title)}
                </h1>
                {stringValue(settings.subtitle) && (
                    <p className="mx-auto mt-4 max-w-2xl text-base leading-7 text-white/80">
                        {stringValue(settings.subtitle)}
                    </p>
                )}
            </div>
        </section>
    );
}

function RichTextSection({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const html = stringValue(settings.html);

    if (!html) {
        return null;
    }

    return (
        <section
            className="bg-white px-4 py-14 dark:bg-neutral-950"
            style={sectionBackgroundStyle(settings)}
        >
            <div className={sectionContentWidthClass(settings)}>
                {/* HTML is sanitized server-side by App\Domain\Storefront\HtmlSanitizer. */}
                <div
                    className="rich-text mx-auto max-w-3xl text-neutral-800 dark:text-neutral-200"
                    dangerouslySetInnerHTML={{ __html: html }}
                />
            </div>
        </section>
    );
}

function ContactInfoSection({ section }: { section: CmsSection }) {
    const settings = section.settings;
    const phone = stringValue(settings.phone);
    const email = stringValue(settings.email);
    const address = stringValue(settings.address);
    const hours = stringValue(settings.hours);
    const mapUrl = stringValue(settings.map_url);

    return (
        <section
            className="bg-white px-4 py-16 dark:bg-neutral-950"
            style={sectionBackgroundStyle(settings)}
        >
            <div
                className={`${sectionContentWidthClass(settings)} grid gap-8 md:grid-cols-2`}
            >
                <div>
                    {stringValue(settings.title) && (
                        <h2 className="text-2xl font-bold text-neutral-950 dark:text-white">
                            {stringValue(settings.title)}
                        </h2>
                    )}
                    <dl className="mt-6 space-y-4 text-sm">
                        {phone && (
                            <div className="flex items-center gap-3">
                                <Phone className="size-5 text-red-700 dark:text-red-400" />
                                <a
                                    href={`tel:${phone}`}
                                    className="text-neutral-700 dark:text-neutral-300"
                                >
                                    {phone}
                                </a>
                            </div>
                        )}
                        {email && (
                            <div className="flex items-center gap-3">
                                <Mail className="size-5 text-red-700 dark:text-red-400" />
                                <a
                                    href={`mailto:${email}`}
                                    className="text-neutral-700 dark:text-neutral-300"
                                >
                                    {email}
                                </a>
                            </div>
                        )}
                        {address && (
                            <div className="flex items-start gap-3">
                                <MapPin className="mt-0.5 size-5 text-red-700 dark:text-red-400" />
                                <span className="whitespace-pre-line text-neutral-700 dark:text-neutral-300">
                                    {address}
                                </span>
                            </div>
                        )}
                        {hours && (
                            <div className="flex items-start gap-3">
                                <Clock className="mt-0.5 size-5 text-red-700 dark:text-red-400" />
                                <span className="whitespace-pre-line text-neutral-700 dark:text-neutral-300">
                                    {hours}
                                </span>
                            </div>
                        )}
                    </dl>
                </div>
                {mapUrl && (
                    <div className="overflow-hidden rounded-lg border border-neutral-200 dark:border-neutral-800">
                        <iframe
                            src={mapUrl}
                            title="Mapa"
                            loading="lazy"
                            referrerPolicy="no-referrer-when-downgrade"
                            className="h-full min-h-[18rem] w-full"
                        />
                    </div>
                )}
            </div>
        </section>
    );
}

function stringValue(value: unknown): string {
    return typeof value === 'string' ? value : '';
}

function arrayValue<T>(value: unknown): T[] {
    return Array.isArray(value) ? (value as T[]) : [];
}

function heroSlideHasContent(slide: CmsHeroSlide): boolean {
    return Boolean(
        slide.media ||
            stringValue(slide.title) ||
            stringValue(slide.subtitle) ||
            stringValue(slide.eyebrow) ||
            arrayValue<CmsButton>(slide.buttons).length > 0,
    );
}

/**
 * Build the list of hero slides, always returning at least one slide.
 * Falls back to the legacy hero fields (top-level media/eyebrow/title/...)
 * for homes saved before the carousel existed.
 */
function heroSlides(settings: CmsSection['settings']): CmsHeroSlide[] {
    const slides = arrayValue<CmsHeroSlide>(settings.slides)
        .slice(0, 5)
        .filter(heroSlideHasContent);

    if (slides.length > 0) {
        return slides;
    }

    return [
        {
            media: settings.media ?? null,
            eyebrow: stringValue(settings.eyebrow),
            title: stringValue(settings.title),
            subtitle: stringValue(settings.subtitle),
            buttons: arrayValue<CmsButton>(settings.buttons),
        },
    ];
}

function sectionContentWidthClass(settings: Record<string, unknown>): string {
    return stringValue(settings.content_width) === 'full'
        ? 'mx-auto w-full max-w-none'
        : 'mx-auto w-full max-w-6xl';
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

function isWideSpecialtyCard(item: CmsItem, index: number): boolean {
    if (typeof item.wide === 'boolean') {
        return item.wide;
    }

    return Boolean(item.media) || index === 0;
}

function sectionBackgroundStyle(
    settings: Record<string, unknown>,
): CSSProperties | undefined {
    const backgroundColor = stringValue(settings.background_color);

    return backgroundColor ? { backgroundColor } : undefined;
}
