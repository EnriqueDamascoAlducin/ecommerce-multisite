import { Plus, Trash2 } from 'lucide-react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type AddressForm = {
    id?: number;
    label: string;
    first_name: string;
    last_name: string;
    company: string;
    phone: string;
    line1: string;
    line2: string;
    city: string;
    state: string;
    postal_code: string;
    country: string;
    is_default_shipping: boolean;
    is_default_billing: boolean;
};

export type CustomerData = {
    website_id: number | string;
    group_id: number | string;
    name: string;
    email: string;
    phone: string;
    password: string;
    addresses: AddressForm[];
};

type WebsiteOption = { id: number; name: string };
type GroupOption = { id: number; name: string; website_id: number };

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export function blankAddress(): AddressForm {
    return {
        label: '',
        first_name: '',
        last_name: '',
        company: '',
        phone: '',
        line1: '',
        line2: '',
        city: '',
        state: '',
        postal_code: '',
        country: 'MX',
        is_default_shipping: false,
        is_default_billing: false,
    };
}

export default function CustomerFields({
    data,
    setData,
    errors,
    websites,
    groups,
    isCreate,
}: {
    data: CustomerData;
    setData: <K extends keyof CustomerData>(key: K, value: CustomerData[K]) => void;
    errors: Record<string, string>;
    websites: WebsiteOption[];
    groups: GroupOption[];
    isCreate: boolean;
}) {
    const err = errors as Record<string, string>;
    const websiteGroups = groups.filter((g) => g.website_id === Number(data.website_id));

    const patchAddress = (index: number, patch: Partial<AddressForm>) =>
        setData(
            'addresses',
            data.addresses.map((a, i) => (i === index ? { ...a, ...patch } : a)),
        );

    const addAddress = () => setData('addresses', [...data.addresses, blankAddress()]);
    const removeAddress = (index: number) =>
        setData('addresses', data.addresses.filter((_, i) => i !== index));

    return (
        <div className="space-y-8">
            {/* Datos generales */}
            <section className="space-y-5">
                <h2 className="text-sm font-semibold text-neutral-500 uppercase tracking-wide">Datos generales</h2>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="website_id">Sitio (website)</Label>
                        <select
                            id="website_id"
                            value={data.website_id}
                            onChange={(e) => {
                                setData('website_id', e.target.value);
                                setData('group_id', '');
                            }}
                            disabled={!isCreate}
                            className={`${fieldClass} disabled:opacity-60`}
                        >
                            <option value="">Selecciona un sitio…</option>
                            {websites.map((website) => (
                                <option key={website.id} value={website.id}>
                                    {website.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.website_id} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="group_id">Grupo</Label>
                        <select
                            id="group_id"
                            value={data.group_id}
                            onChange={(e) => setData('group_id', e.target.value)}
                            className={fieldClass}
                        >
                            <option value="">Sin grupo</option>
                            {websiteGroups.map((group) => (
                                <option key={group.id} value={group.id}>
                                    {group.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.group_id} />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="name">Nombre</Label>
                        <Input id="name" value={data.name} onChange={(e) => setData('name', e.target.value)} />
                        <InputError message={errors.name} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="phone">Teléfono</Label>
                        <Input id="phone" value={data.phone} onChange={(e) => setData('phone', e.target.value)} />
                        <InputError message={errors.phone} />
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2">
                    <div className="grid gap-2">
                        <Label htmlFor="email">Email</Label>
                        <Input id="email" type="email" value={data.email} onChange={(e) => setData('email', e.target.value)} />
                        <InputError message={errors.email} />
                    </div>
                    <div className="grid gap-2">
                        <Label htmlFor="password">Contraseña</Label>
                        <Input
                            id="password"
                            type="password"
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            placeholder={isCreate ? '' : 'Dejar en blanco para no cambiar'}
                        />
                        <InputError message={errors.password} />
                    </div>
                </div>
            </section>

            {/* Direcciones */}
            <section className="space-y-3">
                <div className="flex items-center justify-between">
                    <h2 className="text-sm font-semibold text-neutral-500 uppercase tracking-wide">Direcciones</h2>
                    <Button type="button" variant="outline" size="sm" onClick={addAddress}>
                        <Plus className="size-4" /> Agregar dirección
                    </Button>
                </div>

                {data.addresses.length === 0 && (
                    <p className="text-sm text-neutral-500">Este cliente no tiene direcciones.</p>
                )}

                {data.addresses.map((address, index) => (
                    <div key={index} className="grid gap-4 rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                        <div className="flex items-center justify-between">
                            <span className="text-xs font-medium text-neutral-500">
                                {address.label || `Dirección ${index + 1}`}
                            </span>
                            <Button type="button" variant="ghost" size="icon" onClick={() => removeAddress(index)}>
                                <Trash2 className="size-4" />
                            </Button>
                        </div>

                        <div className="grid gap-4 sm:grid-cols-2">
                            <AddressField label="Etiqueta" value={address.label} onChange={(v) => patchAddress(index, { label: v })} error={err[`addresses.${index}.label`]} />
                            <AddressField label="Empresa" value={address.company} onChange={(v) => patchAddress(index, { company: v })} error={err[`addresses.${index}.company`]} />
                            <AddressField label="Nombre" value={address.first_name} onChange={(v) => patchAddress(index, { first_name: v })} error={err[`addresses.${index}.first_name`]} />
                            <AddressField label="Apellidos" value={address.last_name} onChange={(v) => patchAddress(index, { last_name: v })} error={err[`addresses.${index}.last_name`]} />
                            <AddressField label="Teléfono" value={address.phone} onChange={(v) => patchAddress(index, { phone: v })} error={err[`addresses.${index}.phone`]} />
                            <AddressField label="País (2 letras)" value={address.country} onChange={(v) => patchAddress(index, { country: v.toUpperCase() })} error={err[`addresses.${index}.country`]} />
                        </div>

                        <AddressField label="Dirección" value={address.line1} onChange={(v) => patchAddress(index, { line1: v })} error={err[`addresses.${index}.line1`]} />
                        <AddressField label="Dirección (línea 2)" value={address.line2} onChange={(v) => patchAddress(index, { line2: v })} error={err[`addresses.${index}.line2`]} />

                        <div className="grid gap-4 sm:grid-cols-3">
                            <AddressField label="Ciudad" value={address.city} onChange={(v) => patchAddress(index, { city: v })} error={err[`addresses.${index}.city`]} />
                            <AddressField label="Estado" value={address.state} onChange={(v) => patchAddress(index, { state: v })} error={err[`addresses.${index}.state`]} />
                            <AddressField label="C.P." value={address.postal_code} onChange={(v) => patchAddress(index, { postal_code: v })} error={err[`addresses.${index}.postal_code`]} />
                        </div>

                        <div className="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={address.is_default_shipping}
                                    onChange={(e) => patchAddress(index, { is_default_shipping: e.target.checked })}
                                    className="size-4 rounded"
                                />
                                Envío por defecto
                            </label>
                            <label className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    checked={address.is_default_billing}
                                    onChange={(e) => patchAddress(index, { is_default_billing: e.target.checked })}
                                    className="size-4 rounded"
                                />
                                Facturación por defecto
                            </label>
                        </div>
                    </div>
                ))}
            </section>
        </div>
    );
}

function AddressField({
    label,
    value,
    onChange,
    error,
}: {
    label: string;
    value: string;
    onChange: (value: string) => void;
    error?: string;
}) {
    return (
        <div className="grid gap-2">
            <Label className="text-xs text-neutral-500">{label}</Label>
            <Input value={value} onChange={(e) => onChange(e.target.value)} />
            <InputError message={error} />
        </div>
    );
}
