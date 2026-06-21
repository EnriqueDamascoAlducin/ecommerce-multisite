import { Head, router, useForm } from '@inertiajs/react';
import { ExternalLink, RefreshCw, Save, SearchCheck } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import seo from '@/routes/admin/seo';

type StoreOption = { id: number; label: string };
type Counts = { pages: number; categories: number; products: number };

export default function SeoIndex({
    stores,
    currentStoreId,
    indexingEnabled,
    additionalRules,
    customRobots,
    sitemapUrl,
    robotsUrl,
    counts,
    sitemapPreview,
    robotsPreview,
}: {
    stores: StoreOption[];
    currentStoreId: number;
    indexingEnabled: boolean;
    additionalRules: string;
    customRobots: string;
    sitemapUrl: string;
    robotsUrl: string;
    counts: Counts;
    sitemapPreview: string;
    robotsPreview: string;
}) {
    const form = useForm({
        store_id: currentStoreId,
        indexing_enabled: indexingEnabled,
        additional_rules: additionalRules,
        custom_robots: customRobots,
    });

    const changeStore = (event: ChangeEvent<HTMLSelectElement>) => {
        router.get(
            seo.index.url({ query: { store_id: event.target.value } }),
            {},
            { preserveState: false },
        );
    };

    const save = () => {
        form.put(seo.update.url(), { preserveScroll: true });
    };

    const regenerate = () => {
        router.post(
            seo.regenerate.url(),
            { store_id: currentStoreId },
            { preserveScroll: true },
        );
    };

    return (
        <>
            <Head title="SEO y rastreo" />

            <div className="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 className="text-2xl font-semibold">SEO y rastreo</h1>
                    <p className="text-sm text-neutral-500">
                        Sitemap y reglas de indexación de cada tienda.
                    </p>
                </div>
                <select
                    value={currentStoreId}
                    onChange={changeStore}
                    className="h-10 rounded-md border border-neutral-300 bg-white px-3 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    {stores.map((store) => (
                        <option key={store.id} value={store.id}>
                            {store.label}
                        </option>
                    ))}
                </select>
            </div>

            <div className="mt-6 grid gap-4 sm:grid-cols-3">
                {[
                    ['Páginas', counts.pages],
                    ['Categorías', counts.categories],
                    ['Productos', counts.products],
                ].map(([label, count]) => (
                    <div
                        key={label}
                        className="rounded-md border border-neutral-200 bg-white p-4 dark:border-neutral-800 dark:bg-neutral-900"
                    >
                        <p className="text-xs font-medium text-neutral-500 uppercase">
                            {label}
                        </p>
                        <p className="mt-1 text-2xl font-semibold">{count}</p>
                    </div>
                ))}
            </div>

            <section className="mt-6 border-y border-neutral-200 py-6 dark:border-neutral-800">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h2 className="font-semibold">Robots por tienda</h2>
                        <p className="text-sm text-neutral-500">
                            Las reglas se fusionan en el robots efectivo del dominio.
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={regenerate}
                        >
                            <RefreshCw className="size-4" />
                            Regenerar
                        </Button>
                        <Button
                            type="button"
                            disabled={form.processing}
                            onClick={save}
                        >
                            <Save className="size-4" />
                            Guardar
                        </Button>
                    </div>
                </div>

                <label className="mt-5 flex items-center gap-3 text-sm">
                    <Checkbox
                        checked={form.data.indexing_enabled}
                        onCheckedChange={(checked) =>
                            form.setData('indexing_enabled', checked === true)
                        }
                    />
                    Permitir indexación de esta tienda
                </label>

                <div className="mt-5 grid gap-2">
                    <Label>Robots completo</Label>
                    <textarea
                        value={form.data.custom_robots}
                        onChange={(event) =>
                            form.setData('custom_robots', event.target.value)
                        }
                        placeholder={'User-agent: *\nDisallow: /admin\nSitemap: https://example.com/sitemap.xml'}
                        className="min-h-64 rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-sm dark:border-neutral-700 dark:bg-neutral-900"
                    />
                    <p className="text-xs text-neutral-500">
                        Si este campo tiene contenido, se publicará exactamente como robots.txt del dominio efectivo. Déjalo vacío para usar el robots generado automáticamente.
                    </p>
                    {form.errors.custom_robots && (
                        <p className="text-xs text-red-600">
                            {form.errors.custom_robots}
                        </p>
                    )}
                </div>

                <details className="mt-5 rounded-md border border-neutral-200 p-4 dark:border-neutral-800">
                    <summary className="cursor-pointer text-sm font-semibold">
                        Reglas adicionales del generador automático
                    </summary>
                    <div className="mt-4 grid gap-2">
                        <textarea
                            value={form.data.additional_rules}
                            onChange={(event) =>
                                form.setData('additional_rules', event.target.value)
                            }
                            placeholder={'Disallow: /ruta-privada\nAllow: /ruta-publica'}
                            className="min-h-32 rounded-md border border-neutral-300 bg-white px-3 py-2 font-mono text-sm dark:border-neutral-700 dark:bg-neutral-900"
                        />
                        <p className="text-xs text-neutral-500">
                            Sólo aplica cuando el robots completo está vacío. Una regla por línea; se aceptan Allow: / y Disallow: /.
                        </p>
                        {form.errors.additional_rules && (
                            <p className="text-xs text-red-600">
                                {form.errors.additional_rules}
                            </p>
                        )}
                    </div>
                </details>
            </section>

            <div className="grid gap-8 py-6 xl:grid-cols-2">
                <Preview
                    title="Sitemap XML"
                    url={sitemapUrl}
                    content={sitemapPreview}
                />
                <Preview
                    title="Robots efectivo"
                    url={robotsUrl}
                    content={robotsPreview}
                />
            </div>
        </>
    );
}

function Preview({
    title,
    url,
    content,
}: {
    title: string;
    url: string;
    content: string;
}) {
    return (
        <section className="min-w-0">
            <div className="flex items-center justify-between gap-3">
                <div className="min-w-0">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <SearchCheck className="size-4" />
                        {title}
                    </h2>
                    <p className="truncate text-xs text-neutral-500">{url}</p>
                </div>
                <Button asChild variant="outline" size="icon">
                    <a
                        href={url}
                        target="_blank"
                        rel="noreferrer"
                        title={'Abrir '+title}
                    >
                        <ExternalLink className="size-4" />
                    </a>
                </Button>
            </div>
            <pre className="mt-3 max-h-[32rem] overflow-auto rounded-md border border-neutral-200 bg-neutral-950 p-4 text-xs leading-5 text-neutral-100 dark:border-neutral-800">
                {content}
            </pre>
        </section>
    );
}
