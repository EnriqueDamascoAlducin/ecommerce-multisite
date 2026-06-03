import { Form, Head } from '@inertiajs/react';
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
                    Editando el scope: <span className="font-medium">{scope.label}</span>. Cambia el scope desde el
                    selector de la barra lateral. Deja un campo vacío para heredar del scope superior.
                </p>
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
