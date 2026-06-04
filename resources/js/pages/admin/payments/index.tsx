import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import payments from '@/routes/admin/payments';

type Field = { key: string; label: string; secret: boolean };
type Gateway = { code: string; label: string; fields: Field[]; supports_mode: boolean };
type Website = { id: number; name: string };
type Setting = {
    website_id: number;
    gateway: string;
    is_enabled: boolean;
    mode: string;
    values: Record<string, string>;
    secret_set: Record<string, boolean>;
};

export default function PaymentsIndex({
    websites,
    gateways,
    settings,
}: {
    websites: Website[];
    gateways: Gateway[];
    settings: Setting[];
}) {
    const [websiteId, setWebsiteId] = useState<number>(websites[0]?.id ?? 0);

    const settingFor = (gateway: string): Setting | undefined =>
        settings.find((s) => s.website_id === websiteId && s.gateway === gateway);

    return (
        <>
            <Head title="Pasarelas de pago" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Pasarelas de pago</h1>
                <p className="text-sm text-neutral-500">
                    Configura las llaves y el modo (pruebas o producción) de cada pasarela, por sitio.
                </p>
            </div>

            <div className="mb-6 grid max-w-sm gap-2">
                <Label htmlFor="website">Sitio (website)</Label>
                <select
                    id="website"
                    value={websiteId}
                    onChange={(e) => setWebsiteId(Number(e.target.value))}
                    className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    {websites.map((website) => (
                        <option key={website.id} value={website.id}>
                            {website.name}
                        </option>
                    ))}
                </select>
            </div>

            <div className="grid gap-6">
                {gateways.map((gateway) => (
                    <GatewayCard
                        key={`${gateway.code}-${websiteId}`}
                        websiteId={websiteId}
                        gateway={gateway}
                        setting={settingFor(gateway.code)}
                    />
                ))}
            </div>
        </>
    );
}

function GatewayCard({
    websiteId,
    gateway,
    setting,
}: {
    websiteId: number;
    gateway: Gateway;
    setting?: Setting;
}) {
    const form = useForm<{
        website_id: number;
        gateway: string;
        is_enabled: boolean;
        mode: string;
        credentials: Record<string, string>;
    }>({
        website_id: websiteId,
        gateway: gateway.code,
        is_enabled: setting?.is_enabled ?? false,
        mode: setting?.mode ?? 'sandbox',
        credentials: Object.fromEntries(
            gateway.fields.map((field) => [field.key, setting?.values?.[field.key] ?? '']),
        ),
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.put(payments.update().url, { preserveScroll: true });
    };

    const setCredential = (key: string, value: string) => {
        form.setData('credentials', { ...form.data.credentials, [key]: value });
    };

    return (
        <form
            onSubmit={submit}
            className="rounded-lg border border-neutral-200 p-5 dark:border-neutral-800"
        >
            <div className="mb-4 flex items-center justify-between">
                <div className="flex items-center gap-3">
                    <h2 className="text-lg font-semibold">{gateway.label}</h2>
                    <Badge variant={form.data.is_enabled ? 'default' : 'outline'}>
                        {form.data.is_enabled ? 'Activa' : 'Inactiva'}
                    </Badge>
                </div>
                <label className="flex items-center gap-2 text-sm">
                    <input
                        type="checkbox"
                        checked={form.data.is_enabled}
                        onChange={(e) => form.setData('is_enabled', e.target.checked)}
                        className="size-4 rounded"
                    />
                    Habilitada en este sitio
                </label>
            </div>

            {gateway.supports_mode && (
                <div className="mb-4 grid max-w-xs gap-2">
                    <Label htmlFor={`mode-${gateway.code}`}>Modo</Label>
                    <select
                        id={`mode-${gateway.code}`}
                        value={form.data.mode}
                        onChange={(e) => form.setData('mode', e.target.value)}
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        <option value="sandbox">Pruebas (sandbox)</option>
                        <option value="live">Producción (live)</option>
                    </select>
                </div>
            )}

            {gateway.fields.length > 0 ? (
                <div className="grid gap-4 sm:grid-cols-2">
                    {gateway.fields.map((field) => (
                        <div key={field.key} className="grid gap-2">
                            <Label htmlFor={`${gateway.code}-${field.key}`}>{field.label}</Label>
                            <Input
                                id={`${gateway.code}-${field.key}`}
                                type={field.secret ? 'password' : 'text'}
                                autoComplete="off"
                                value={form.data.credentials[field.key] ?? ''}
                                placeholder={
                                    field.secret && setting?.secret_set?.[field.key]
                                        ? '•••••••• (guardado — deja en blanco para conservarlo)'
                                        : ''
                                }
                                onChange={(e) => setCredential(field.key, e.target.value)}
                            />
                            <InputError message={form.errors[`credentials.${field.key}` as keyof typeof form.errors]} />
                        </div>
                    ))}
                </div>
            ) : (
                <p className="text-sm text-neutral-500">Esta pasarela no requiere llaves.</p>
            )}

            <div className="mt-5">
                <Button disabled={form.processing}>Guardar</Button>
            </div>
        </form>
    );
}
