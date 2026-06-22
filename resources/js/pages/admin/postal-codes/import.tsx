import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, FileSpreadsheet, Upload } from 'lucide-react';
import type { FormEvent } from 'react';
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
import configuration from '@/routes/admin/configuration';
import importRoutes from '@/routes/admin/postal-codes/import';

type PostalCodeImportResult = {
    summary: {
        total_rows: number;
        valid_rows: number;
        skipped_rows: number;
        imported_rows: number;
        postal_codes: number;
        settlements: number;
    };
};

export default function PostalCodeImport({
    result,
}: {
    result: PostalCodeImportResult | null;
}) {
    const form = useForm<{ file: File | null }>({ file: null });

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        form.post(importRoutes.store().url, {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <>
            <Head title="Importar códigos postales" />

            <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div className="mb-2 flex items-center gap-2 text-xs text-neutral-500">
                        <Link
                            href={configuration.index()}
                            className="hover:text-neutral-900 dark:hover:text-neutral-100"
                        >
                            Configuración
                        </Link>
                        <span>/</span>
                        <span>Códigos postales</span>
                    </div>
                    <h1 className="text-2xl font-semibold tracking-tight">
                        Importar códigos postales
                    </h1>
                    <p className="mt-1 text-sm text-neutral-500 dark:text-neutral-400">
                        Catálogo SEPOMEX para autollenar colonias en checkout.
                    </p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={configuration.index()}>
                        <ArrowLeft className="size-4" />
                        Volver
                    </Link>
                </Button>
            </div>

            <div className="grid max-w-6xl gap-6 xl:grid-cols-[380px_1fr]">
                <Card className="rounded-lg">
                    <CardHeader>
                        <CardTitle className="flex items-center gap-2">
                            <FileSpreadsheet className="size-5" />
                            CSV SEPOMEX
                        </CardTitle>
                        <CardDescription>
                            Columnas requeridas: d_codigo, d_asenta, D_mnpio y
                            d_estado.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <form className="space-y-4" onSubmit={submit}>
                            <div className="space-y-2">
                                <Label htmlFor="postal-code-csv-file">
                                    Archivo
                                </Label>
                                <Input
                                    id="postal-code-csv-file"
                                    type="file"
                                    accept=".csv,text/csv,text/plain"
                                    onChange={(event) =>
                                        form.setData(
                                            'file',
                                            event.currentTarget.files?.[0] ??
                                                null,
                                        )
                                    }
                                />
                                {form.errors.file && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.file}
                                    </p>
                                )}
                            </div>

                            {form.progress && (
                                <div className="space-y-1">
                                    <progress
                                        value={form.progress.percentage}
                                        max="100"
                                        className="h-2 w-full"
                                    >
                                        {form.progress.percentage}%
                                    </progress>
                                    <p className="text-xs text-neutral-500">
                                        {form.progress.percentage}%
                                    </p>
                                </div>
                            )}

                            <Button
                                type="submit"
                                disabled={form.processing || !form.data.file}
                            >
                                <Upload className="size-4" />
                                Importar archivo
                            </Button>
                        </form>
                    </CardContent>
                </Card>

                <div className="space-y-6">
                    {result ? (
                        <Summary result={result} />
                    ) : (
                        <Card className="rounded-lg">
                            <CardHeader>
                                <CardTitle>Sin importación reciente</CardTitle>
                                <CardDescription>
                                    Sube un CSV SEPOMEX normalizado en UTF-8.
                                </CardDescription>
                            </CardHeader>
                        </Card>
                    )}
                </div>
            </div>
        </>
    );
}

function Summary({ result }: { result: PostalCodeImportResult }) {
    const summary = result.summary;

    return (
        <Card className="rounded-lg">
            <CardHeader>
                <CardTitle>Resultado</CardTitle>
                <CardDescription>
                    Filas procesadas y colonias listas para checkout.
                </CardDescription>
            </CardHeader>
            <CardContent>
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Metric label="Filas CSV" value={summary.total_rows} />
                    <Metric label="Filas válidas" value={summary.valid_rows} />
                    <Metric
                        label="Filas omitidas"
                        value={summary.skipped_rows}
                    />
                    <Metric
                        label="Registros aplicados"
                        value={summary.imported_rows}
                    />
                    <Metric
                        label="Códigos postales"
                        value={summary.postal_codes}
                    />
                    <Metric label="Colonias" value={summary.settlements} />
                </div>
            </CardContent>
        </Card>
    );
}

function Metric({ label, value }: { label: string; value: number }) {
    return (
        <div className="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
            <p className="text-xs text-neutral-500">{label}</p>
            <p className="mt-1 text-2xl font-semibold">
                {value.toLocaleString()}
            </p>
        </div>
    );
}
