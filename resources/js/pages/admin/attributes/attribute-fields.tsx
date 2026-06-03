import { useState } from 'react';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type OptionDraft = { label: string; value: string };

export type AttributeDefaults = {
    code: string;
    name: string;
    type: string;
    is_required: boolean;
    is_filterable: boolean;
    is_visible: boolean;
    is_configurable: boolean;
    sort_order: number;
    options: OptionDraft[];
};

const TYPE_LABELS: Record<string, string> = {
    text: 'Texto',
    textarea: 'Texto largo',
    number: 'Número',
    select: 'Selección',
    multiselect: 'Selección múltiple',
    boolean: 'Sí / No',
    date: 'Fecha',
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export function AttributeFields({
    errors,
    types,
    defaults,
    lockCode = false,
}: {
    errors: Record<string, string>;
    types: string[];
    defaults?: AttributeDefaults;
    lockCode?: boolean;
}) {
    const [type, setType] = useState<string>(defaults?.type ?? 'text');
    const [options, setOptions] = useState<OptionDraft[]>(defaults?.options ?? []);

    const hasOptions = type === 'select' || type === 'multiselect';

    const addOption = () => setOptions((current) => [...current, { label: '', value: '' }]);
    const removeOption = (index: number) => setOptions((current) => current.filter((_, i) => i !== index));
    const updateOption = (index: number, field: keyof OptionDraft, value: string) =>
        setOptions((current) => current.map((option, i) => (i === index ? { ...option, [field]: value } : option)));

    return (
        <div className="space-y-6">
            <section className="grid gap-4 sm:grid-cols-2">
                <div className="grid gap-2">
                    <Label htmlFor="code">Código</Label>
                    <Input id="code" name="code" defaultValue={defaults?.code} readOnly={lockCode} required placeholder="ej. color, talla" />
                    <InputError message={errors.code} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="name">Nombre</Label>
                    <Input id="name" name="name" defaultValue={defaults?.name} required />
                    <InputError message={errors.name} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="type">Tipo</Label>
                    <select id="type" name="type" value={type} onChange={(e) => setType(e.target.value)} className={fieldClass}>
                        {types.map((t) => (
                            <option key={t} value={t}>
                                {TYPE_LABELS[t] ?? t}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.type} />
                </div>
                <div className="grid gap-2">
                    <Label htmlFor="sort_order">Orden</Label>
                    <Input id="sort_order" name="sort_order" type="number" min={0} defaultValue={defaults?.sort_order ?? 0} />
                </div>
            </section>

            <section className="flex flex-wrap gap-4">
                <Flag name="is_required" label="Requerido" defaultChecked={defaults?.is_required ?? false} />
                <Flag name="is_filterable" label="Filtrable" defaultChecked={defaults?.is_filterable ?? false} />
                <Flag name="is_visible" label="Visible" defaultChecked={defaults?.is_visible ?? true} />
                <Flag name="is_configurable" label="Configurable" defaultChecked={defaults?.is_configurable ?? false} />
            </section>

            {hasOptions && (
                <section>
                    <div className="mb-2 flex items-center justify-between">
                        <h2 className="text-sm font-semibold">Opciones</h2>
                        <Button type="button" variant="outline" size="sm" onClick={addOption}>
                            Añadir opción
                        </Button>
                    </div>
                    <div className="space-y-2">
                        {options.map((option, index) => (
                            <div key={index} className="flex gap-2">
                                <Input
                                    name={`options[${index}][label]`}
                                    placeholder="Etiqueta (ej. Rojo)"
                                    value={option.label}
                                    onChange={(e) => updateOption(index, 'label', e.target.value)}
                                />
                                <Input
                                    name={`options[${index}][value]`}
                                    placeholder="valor (opcional)"
                                    value={option.value}
                                    onChange={(e) => updateOption(index, 'value', e.target.value)}
                                />
                                <Button type="button" variant="destructive" size="sm" onClick={() => removeOption(index)}>
                                    ✕
                                </Button>
                            </div>
                        ))}
                        {options.length === 0 && (
                            <p className="text-sm text-neutral-500">Añade al menos una opción para este tipo de atributo.</p>
                        )}
                    </div>
                </section>
            )}
        </div>
    );
}

function Flag({ name, label, defaultChecked }: { name: string; label: string; defaultChecked: boolean }) {
    return (
        <label className="flex items-center gap-2 text-sm">
            <input type="hidden" name={name} value="0" />
            <input type="checkbox" name={name} value="1" defaultChecked={defaultChecked} className="size-4 rounded" />
            {label}
        </label>
    );
}
