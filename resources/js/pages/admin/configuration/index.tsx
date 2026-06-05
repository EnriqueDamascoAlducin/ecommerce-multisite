import { Form, Head, router } from '@inertiajs/react';
import { update as updateScope } from '@/routes/admin/scope';
import StoreConfigurationController from '@/actions/App/Http/Controllers/Admin/StoreConfigurationController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ConfigField = {
    key: string;
    label: string;
    value: string;
    inherited: string | null;
};

type Scope = { type: string; id: number; label: string };

export default function ConfigurationIndex({
    scope,
    options,
    fields,
}: {
    scope: Scope;
    options: Scope[];
    fields: ConfigField[];
}) {
    return (
        <>
            <Head title="Configuración" />

            <div className="mb-6">
                <h1 className="text-2xl font-semibold">Configuración</h1>
                <p className="mt-1 text-sm text-neutral-500">
                    Selecciona el ámbito para configurar. Deja un campo vacío para heredar del ámbito superior.
                </p>
            </div>

            <div className="mb-6 max-w-xl">
                <label className="text-xs font-medium text-neutral-500">Ámbito</label>
                <select
                    value={`${scope.type}:${scope.id}`}
                    onChange={(e) => {
                        const [type, id] = e.target.value.split(':');
                        router.post(updateScope().url, { type, id: Number(id) }, {
                            preserveScroll: true,
                            preserveState: false,
                        });
                    }}
                    className="mt-1 w-full rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                >
                    {options.map((option) => (
                        <option
                            key={`${option.type}:${option.id}`}
                            value={`${option.type}:${option.id}`}
                        >
                            {option.label}
                        </option>
                    ))}
                </select>
            </div>

            <Form {...StoreConfigurationController.update.form()} className="max-w-xl">
                {({ processing, errors }) => (
                    <div className="space-y-6">
                        {fields.map((field) => (
                            <div key={field.key} className="grid gap-2">
                                <Label htmlFor={field.key}>{field.label}</Label>
                                <Input
                                    id={field.key}
                                    name={`values[${field.key}]`}
                                    defaultValue={field.value}
                                    placeholder={
                                        field.inherited ? `Heredado: ${field.inherited}` : 'Sin valor'
                                    }
                                />
                                {errors[`values.${field.key}`] && (
                                    <p className="text-sm text-red-600">{errors[`values.${field.key}`]}</p>
                                )}
                            </div>
                        ))}
                        <Button disabled={processing}>Guardar configuración</Button>
                    </div>
                )}
            </Form>
        </>
    );
}
