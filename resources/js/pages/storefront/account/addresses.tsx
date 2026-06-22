import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { AccountNav } from './account-nav';

type Address = {
    id: number;
    label: string | null;
    first_name: string;
    last_name: string;
    company: string | null;
    phone: string | null;
    line1: string;
    line2: string | null;
    neighborhood: string | null;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    is_default_shipping: boolean;
    is_default_billing: boolean;
};

export default function CustomerAddresses({ addresses }: { addresses: Address[] }) {
    const [editing, setEditing] = useState<Address | null>(null);
    const [creating, setCreating] = useState(false);

    const remove = (address: Address) => {
        if (confirm('¿Eliminar esta dirección?')) {
            router.delete(`/cuenta/direcciones/${address.id}`, { preserveScroll: true });
        }
    };

    const closeForms = () => {
        setEditing(null);
        setCreating(false);
    };

    return (
        <div className="mx-auto max-w-2xl">
            <Head title="Mis direcciones" />
            <h1 className="mb-6 text-2xl font-semibold">Mi cuenta</h1>
            <AccountNav />

            <div className="mb-4 flex items-center justify-between">
                <h2 className="text-lg font-medium">Direcciones</h2>
                {!creating && !editing && (
                    <Button size="sm" onClick={() => setCreating(true)}>Agregar dirección</Button>
                )}
            </div>

            {(creating || editing) && (
                <div className="mb-6 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                    <Form
                        key={editing?.id ?? 'new'}
                        action={editing ? `/cuenta/direcciones/${editing.id}` : '/cuenta/direcciones'}
                        method={editing ? 'put' : 'post'}
                        options={{ preserveScroll: true }}
                        onSuccess={closeForms}
                        className="space-y-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <AddressFields address={editing} errors={errors} />
                                <div className="flex gap-2">
                                    <Button disabled={processing}>{editing ? 'Guardar' : 'Agregar'}</Button>
                                    <Button type="button" variant="outline" onClick={closeForms}>Cancelar</Button>
                                </div>
                            </>
                        )}
                    </Form>
                </div>
            )}

            <div className="space-y-3">
                {addresses.map((address) => (
                    <div key={address.id} className="flex items-start justify-between rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                        <div className="text-sm">
                            <div className="mb-1 flex items-center gap-2">
                                <span className="font-medium">{address.label ?? `${address.first_name} ${address.last_name}`}</span>
                                {address.is_default_shipping && <Badge variant="outline">Envío</Badge>}
                                {address.is_default_billing && <Badge variant="outline">Facturación</Badge>}
                            </div>
                            <p className="text-neutral-500">
                                {address.first_name} {address.last_name}<br />
                                {address.line1}{address.line2 ? `, ${address.line2}` : ''}<br />
                                {address.neighborhood && <>{address.neighborhood}<br /></>}
                                {address.city}, {address.state}, {address.postal_code} ({address.country})
                            </p>
                        </div>
                        <div className="flex gap-2">
                            <Button variant="outline" size="sm" onClick={() => { setCreating(false); setEditing(address); }}>Editar</Button>
                            <Button variant="destructive" size="sm" onClick={() => remove(address)}>Eliminar</Button>
                        </div>
                    </div>
                ))}
                {addresses.length === 0 && (
                    <p className="py-6 text-center text-sm text-neutral-500">Aún no tienes direcciones guardadas.</p>
                )}
            </div>
        </div>
    );
}

function AddressFields({ address, errors }: { address: Address | null; errors: Record<string, string> }) {
    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <Field name="label" label="Etiqueta" defaultValue={address?.label ?? ''} error={errors.label} />
            <Field name="phone" label="Teléfono" defaultValue={address?.phone ?? ''} error={errors.phone} />
            <Field name="first_name" label="Nombre" defaultValue={address?.first_name ?? ''} error={errors.first_name} required />
            <Field name="last_name" label="Apellidos" defaultValue={address?.last_name ?? ''} error={errors.last_name} required />
            <Field name="company" label="Empresa" defaultValue={address?.company ?? ''} error={errors.company} />
            <Field name="line1" label="Calle y número" defaultValue={address?.line1 ?? ''} error={errors.line1} required />
            <Field name="line2" label="Interior / referencia" defaultValue={address?.line2 ?? ''} error={errors.line2} />
            <Field name="neighborhood" label="Colonia" defaultValue={address?.neighborhood ?? ''} error={errors.neighborhood} />
            <Field name="city" label="Ciudad" defaultValue={address?.city ?? ''} error={errors.city} required />
            <Field name="state" label="Estado" defaultValue={address?.state ?? ''} error={errors.state} required />
            <Field name="postal_code" label="Código postal" defaultValue={address?.postal_code ?? ''} error={errors.postal_code} required />
            <Field name="country" label="País (2 letras)" defaultValue={address?.country ?? 'MX'} error={errors.country} required />
            <div className="flex flex-col justify-end gap-2 sm:col-span-2">
                <label className="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_default_shipping" value="0" />
                    <input type="checkbox" name="is_default_shipping" value="1" defaultChecked={address?.is_default_shipping ?? false} className="size-4 rounded" />
                    Predeterminada para envío
                </label>
                <label className="flex items-center gap-2 text-sm">
                    <input type="hidden" name="is_default_billing" value="0" />
                    <input type="checkbox" name="is_default_billing" value="1" defaultChecked={address?.is_default_billing ?? false} className="size-4 rounded" />
                    Predeterminada para facturación
                </label>
            </div>
        </div>
    );
}

function Field({
    name,
    label,
    defaultValue,
    error,
    required = false,
}: {
    name: string;
    label: string;
    defaultValue: string;
    error?: string;
    required?: boolean;
}) {
    return (
        <div className="grid gap-1.5">
            <Label htmlFor={name}>{label}</Label>
            <Input id={name} name={name} defaultValue={defaultValue} required={required} />
            <InputError message={error} />
        </div>
    );
}
