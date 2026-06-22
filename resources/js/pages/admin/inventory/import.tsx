import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, CheckCircle2, FileSpreadsheet, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
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
import inventory from '@/routes/admin/inventory';
import importRoutes from '@/routes/admin/inventory/import';

type StockImportRow = {
    line: number;
    sku: string | null;
    product_name: string | null;
    source_code: string | null;
    target_source_code: string | null;
    quantity: number | null;
    current_quantity: number | null;
    status: string | null;
    action: 'create' | 'update' | 'no_change' | 'error';
    errors: string[];
    warnings: string[];
};

type StockImportResult = {
    summary: {
        total_rows: number;
        matched_skus: number;
        missing_skus: number;
        valid_rows: number;
        error_rows: number;
        creates: number;
        updates: number;
        no_changes: number;
        applied?: number;
        skipped_no_change?: number;
    };
    rows: StockImportRow[];
};

export default function StockImport({
    result,
    token,
}: {
    result: StockImportResult | null;
    token: string | null;
}) {
    const uploadForm = useForm<{ file: File | null }>({ file: null });
    const confirmForm = useForm<{ token: string }>({ token: token ?? '' });
    const canImport = Boolean(token && result && result.summary.valid_rows > 0);

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
            <Head title="Importar stock" />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="mb-2 flex items-center gap-2 text-xs text-neutral-500">
                        <Link
                            href={inventory.index()}
                            className="hover:text-neutral-900 dark:hover:text-neutral-100"
                        >
                            Inventario
                        </Link>
                        <span>/</span>
                        <span>Importar stock</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Importar stock
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Valida y fija stock físico absoluto por SKU desde CSV.
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={inventory.index()}>
                        <ArrowLeft className="size-4" />
                        Volver
                    </Link>
                </Button>
            </div>

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
                            CSV de stock
                        </CardTitle>
                        <CardDescription>
                            Columnas requeridas: sku y quantity. source_code es
                            opcional.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submitUpload}>
                            <div className="space-y-2 rounded-md border border-amber-200 bg-amber-50 p-3 text-sm text-amber-900 dark:border-amber-900/60 dark:bg-amber-950/30 dark:text-amber-200">
                                La importación fija stock físico absoluto, no
                                suma cantidades.
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="stock-csv-file">Archivo</Label>
                                <Input
                                    id="stock-csv-file"
                                    type="file"
                                    accept=".csv,text/csv,text/plain"
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
                                    !uploadForm.data.file
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
                                        <CardTitle>Preview de filas</CardTitle>
                                        <CardDescription>
                                            Resultado por línea antes de aplicar
                                            cambios.
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
                                                Confirmar importación
                                            </Button>
                                        </form>
                                    )}
                                </CardHeader>
                                <CardContent>
                                    <div className="overflow-x-auto rounded-lg border border-neutral-200 dark:border-neutral-800">
                                        <table className="w-full min-w-[900px] text-left text-sm">
                                            <thead className="bg-neutral-50 text-xs text-neutral-500 dark:bg-neutral-950/40">
                                                <tr>
                                                    <th className="px-3 py-2">
                                                        Línea
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        SKU
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Producto
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Source CSV
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Source destino
                                                    </th>
                                                    <th className="px-3 py-2 text-right">
                                                        Actual
                                                    </th>
                                                    <th className="px-3 py-2 text-right">
                                                        Nueva
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Status
                                                    </th>
                                                    <th className="px-3 py-2">
                                                        Estado
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
                                                            {row.product_name ||
                                                                '-'}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {row.source_code ||
                                                                '-'}
                                                        </td>
                                                        <td className="px-3 py-2 font-mono text-xs">
                                                            {row.target_source_code ||
                                                                '-'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right">
                                                            {row.current_quantity ??
                                                                '-'}
                                                        </td>
                                                        <td className="px-3 py-2 text-right font-medium">
                                                            {row.quantity ??
                                                                '-'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            {row.status ?? '-'}
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <ActionBadge
                                                                action={
                                                                    row.action
                                                                }
                                                            />
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
                                                            ) : (
                                                                <span className="text-emerald-700 dark:text-emerald-400">
                                                                    Válida
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

function Summary({ result }: { result: StockImportResult }) {
    const items = [
        ['Filas', result.summary.total_rows],
        ['Válidas', result.summary.valid_rows],
        ['Con error', result.summary.error_rows],
        ['SKUs encontrados', result.summary.matched_skus],
        ['SKUs no encontrados', result.summary.missing_skus],
        ['Crear stock', result.summary.creates],
        ['Actualizar stock', result.summary.updates],
        ['Sin cambio', result.summary.no_changes],
        ['Aplicadas', result.summary.applied ?? 0],
    ];

    return (
        <div className="grid gap-3 sm:grid-cols-3 xl:grid-cols-4">
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

function ActionBadge({ action }: { action: StockImportRow['action'] }) {
    const labels: Record<StockImportRow['action'], string> = {
        create: 'Crear',
        update: 'Actualizar',
        no_change: 'Sin cambio',
        error: 'Error',
    };

    return (
        <Badge variant={action === 'error' ? 'destructive' : 'outline'}>
            {labels[action]}
        </Badge>
    );
}
