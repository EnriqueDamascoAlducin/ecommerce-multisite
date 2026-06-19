import { Head, Link, useForm, usePoll } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, FileSpreadsheet, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import importRoutes from '@/routes/admin/products/import';
import products from '@/routes/admin/products';

type ImportRow = {
    line: number;
    sku: string | null;
    name: string | null;
    action: 'create' | 'update' | 'skip';
    errors: string[];
    warnings: string[];
};

type BackgroundImport = {
    id: number;
    uuid: string;
    status: 'pending' | 'processing' | 'completed' | 'failed';
    total_products: number;
    processed_products: number;
    total_images: number;
    processed_images: number;
    progress: number;
    summary: ImportResult['summary'] | null;
    error: string | null;
    started_at: string | null;
    completed_at: string | null;
};
type ImportResult = {
    summary: {
        total_rows: number;
        valid_rows: number;
        error_rows: number;
        skipped_rows: number;
        assigned_store_views: number;
        omitted_store_views: number;
        omitted_unsupported_types: number;
        missing_categories: number;
        images_detected: number;
        images_downloaded: number;
        images_reused: number;
        images_failed: number;
        creates: number;
        updates: number;
        imported: number;
        skipped: number;
    };
    rows: ImportRow[];
};

export default function ProductImport({
    result,
    token,
    activeImport,
}: {
    result: ImportResult | null;
    token: string | null;
    activeImport: BackgroundImport | null;
}) {
    const uploadForm = useForm<{ file: File | null }>({ file: null });
    const confirmForm = useForm<{ token: string }>({ token: token ?? '' });
    const importIsRunning =
        activeImport?.status === 'pending' ||
        activeImport?.status === 'processing';
    const canImport = Boolean(
        token && result && result.summary.valid_rows > 0 && !importIsRunning,
    );
    const { start: startPolling, stop: stopPolling } = usePoll(
        2000,
        { only: ['activeImport', 'result'] },
        { autoStart: false, keepAlive: true, mode: 'rest' },
    );

    useEffect(() => {
        if (importIsRunning) {
            startPolling();
        } else {
            stopPolling();
        }

        return stopPolling;
    }, [importIsRunning, startPolling, stopPolling]);

    const submitUpload = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        uploadForm.post(importRoutes.validate().url, {
            forceFormData: true,
        });
    };

    const submitConfirm = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        confirmForm.transform(() => ({ token: token ?? '' }));
        confirmForm.post(importRoutes.confirm().url);
    };

    return (
        <>
            <Head title="Importar productos" />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="mb-2 flex items-center gap-2 text-xs text-neutral-500">
                        <Link
                            href={products.index()}
                            className="hover:text-neutral-900 dark:hover:text-neutral-100"
                        >
                            Productos
                        </Link>
                        <span>/</span>
                        <span>Importar CSV</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Importar productos
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Valida y aplica productos simples desde un CSV.
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={products.index()}>
                        <ArrowLeft className="size-4" />
                        Volver
                    </Link>
                </Button>
            </div>

            {activeImport && <ImportProgress value={activeImport} />}

            {confirmForm.errors.token && (
                <p className="mb-4 text-sm text-red-600">
                    {confirmForm.errors.token}
                </p>
            )}

            <div className="grid max-w-6xl gap-6 xl:grid-cols-[380px_1fr]">
                <Card className="rounded-lg">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileSpreadsheet className="size-5" />
                            CSV
                        </CardTitle>
                        <CardDescription>
                            Columnas minimas: sku, name y price.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submitUpload}>
                            <div className="space-y-2">
                                <Label htmlFor="csv-file">Archivo</Label>
                                <Input
                                    id="csv-file"
                                    type="file"
                                    accept=".csv,text/csv,text/plain"
                                    disabled={importIsRunning}
                                    onChange={(event) =>
                                        uploadForm.setData(
                                            'file',
                                            event.currentTarget.files?.[0] ??
                                                null,
                                        )
                                    }
                                />
                                {uploadForm.errors.file && (
                                    <p className="text-sm text-red-600">
                                        {uploadForm.errors.file}
                                    </p>
                                )}
                            </div>
                            <Button
                                type="submit"
                                disabled={
                                    uploadForm.processing ||
                                    !uploadForm.data.file ||
                                    importIsRunning
                                }
                            >
                                <Upload className="size-4" />
                                Validar archivo
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    {result && (
                        <>
                            <Summary result={result} />

                            <Card className="rounded-lg">
                                <CardHeader className="flex-row items-start justify-between gap-4">
                                    <div>
                                        <CardTitle>Filas</CardTitle>
                                        <CardDescription>
                                            Resultado por linea y accion
                                            prevista.
                                        </CardDescription>
                                    </div>
                                    {canImport && (
                                        <form onSubmit={submitConfirm}>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    confirmForm.processing
                                                }
                                            >
                                                <CheckCircle2 className="size-4" />
                                                Confirmar importacion
                                            </Button>
                                        </form>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                                        <table className="w-full min-w-[720px] text-left text-sm">
                                            <thead className="bg-neutral-50 text-xs text-neutral-500 dark:bg-neutral-950/40">
                                                <tr>
                                                    <th className="px-3 py-2">
                                                        Linea
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        SKU
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Nombre
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Accion
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Resultado
                                                    </th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                                                {result.rows.map((row) => (
                                                    <tr key={row.line}>
                                                        <td className="px-3 py-2">
                                                            {row.line}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {row.sku || '-'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {row.name || '-'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Badge variant="outline">
                                                                {row.action ===
                                                                'update'
                                                                    ? 'Actualizar'
                                                                    : row.action ===
                                                                        'skip'
                                                                      ? 'Omitir'
                                                                      : 'Crear'}
                                                            </Badge>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {row.errors.length >
                                                            0 ? (
                                                                <ul className="space-y-1 text-red-600">
                                                                    {row.errors.map(
                                                                        (
                                                                            error,
                                                                        ) => (
                                                                            <li
                                                                                key={
                                                                                    error
                                                                                }
                                                                            >
                                                                                {
                                                                                    error
                                                                                }
                                                                            </li>
                                                                        ),
                                                                    )}
                                                                </ul>
                                                            ) : row.warnings
                                                                  .length >
                                                              0 ? (
                                                                <ul className="space-y-1 text-amber-700 dark:text-amber-400">
                                                                    {row.warnings.map(
                                                                        (
                                                                            warning,
                                                                        ) => (
                                                                            <li
                                                                                key={
                                                                                    warning
                                                                                }
                                                                            >
                                                                                {
                                                                                    warning
                                                                                }
                                                                            </li>
                                                                        ),
                                                                    )}
                                                                </ul>
                                                            ) : (
                                                                <span className="text-emerald-700 dark:text-emerald-400">
                                                                    Valida
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>
                        </>
                    )}
                </div>
            </div>
        </>
    );
}

function ImportProgress({ value }: { value: BackgroundImport }) {
    const labels: Record<BackgroundImport['status'], string> = {
        pending: 'En cola',
        processing: 'Procesando',
        completed: 'Completada',
        failed: 'Fallida',
    };
    const isFailed = value.status === 'failed';

    return (
        <Card className="mb-6 max-w-6xl gap-4 rounded-lg py-5">
            <CardHeader className="flex-row items-center justify-between gap-4">
                <div>
                    <CardTitle>Importacion en segundo plano</CardTitle>
                    <CardDescription>
                        Productos e imagenes se procesan sin mantener abierta la
                        solicitud.
                    </CardDescription>
                </div>
                <Badge variant="outline">{labels[value.status]}</Badge>
            </CardHeader>
            <CardContent className="space-y-4">
                <div className="h-2 overflow-hidden rounded-full bg-neutral-200 dark:bg-neutral-800">
                    <div
                        className={`h-full transition-[width] ${isFailed ? 'bg-red-600' : 'bg-emerald-600'}`}
                        style={{ width: `${value.progress}%` }}
                    />
                </div>
                <div className="grid gap-3 text-sm sm:grid-cols-3">
                    <p>
                        <span className="text-neutral-500">Progreso:</span>{' '}
                        {value.progress}%
                    </p>
                    <p>
                        <span className="text-neutral-500">Productos:</span>{' '}
                        {value.processed_products}/{value.total_products}
                    </p>
                    <p>
                        <span className="text-neutral-500">Imagenes:</span>{' '}
                        {value.processed_images}/{value.total_images}
                    </p>
                </div>
                {value.error && (
                    <p className="text-sm text-red-600">{value.error}</p>
                )}
            </CardContent>
        </Card>
    );
}
function Summary({ result }: { result: ImportResult }) {
    const items = [
        ['Filas', result.summary.total_rows],
        ['Validas', result.summary.valid_rows],
        ['Con error', result.summary.error_rows],
        ['Omitidas', result.summary.skipped_rows],
        ['Views asignadas', result.summary.assigned_store_views],
        ['Views omitidas', result.summary.omitted_store_views],
        ['Tipos omitidos', result.summary.omitted_unsupported_types],
        ['Cat. sin asignar', result.summary.missing_categories],
        ['Imagenes detectadas', result.summary.images_detected],
        ['Imagenes descargadas', result.summary.images_downloaded],
        ['Imagenes reutilizadas', result.summary.images_reused],
        ['Imagenes fallidas', result.summary.images_failed],
        ['Nuevos', result.summary.creates],
        ['Actualizaciones', result.summary.updates],
        ['Importados', result.summary.imported],
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
            {items.map(([label, value]) => (
                <Card key={label} className="rounded-lg py-4">
                    <CardContent className="px-4">
                        <p className="text-xs font-medium text-neutral-500">
                            {label}
                        </p>
                        <p className="mt-1 text-2xl font-semibold">{value}</p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
