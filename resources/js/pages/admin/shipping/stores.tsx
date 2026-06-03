import { Form, Head, Link, router } from '@inertiajs/react';
import StoreShippingController from '@/actions/App/Http/Controllers/Admin/StoreShippingController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import shipping from '@/routes/admin/shipping';
import shippingStores from '@/routes/admin/shipping-stores';

type MethodConfig = {
    shipping_method_id: number;
    code: string;
    name: string;
    type: string;
    enabled: boolean;
    label: string | null;
    amount: string | null;
    free_over: string | null;
    min_subtotal: string | null;
    max_subtotal: string | null;
    countries: string | null;
};

type StoreOption = { id: number; label: string };

const TYPE_LABELS: Record<string, string> = {
    flat_rate: 'Tarifa fija',
    free_shipping: 'Envío gratis',
    pickup: 'Recoger en tienda',
};

export default function StoreShipping({
    stores,
    currentStoreId,
    methods,
}: {
    stores: StoreOption[];
    currentStoreId: number | null;
    methods: MethodConfig[];
}) {
    const onStoreChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        router.get(shippingStores.edit().url, { store_id: event.target.value }, { preserveState: false });
    };

    return (
        <>
            <Head title="Envíos por tienda" />

            <div className="mb-6 flex flex-wrap items-center justify-between gap-3">
                <h1 className="text-2xl font-semibold">Envíos por tienda</h1>
                <div className="flex items-center gap-2">
                    <select
                        value={currentStoreId ?? ''}
                        onChange={onStoreChange}
                        className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                    >
                        {stores.map((store) => (
                            <option key={store.id} value={store.id}>
                                {store.label}
                            </option>
                        ))}
                    </select>
                    <Button variant="outline" asChild>
                        <Link href={shipping.index()}>Métodos</Link>
                    </Button>
                </div>
            </div>

            {methods.length === 0 ? (
                <p className="rounded-lg border border-neutral-200 py-12 text-center text-neutral-500 dark:border-neutral-800">
                    Primero crea métodos de envío globales.
                </p>
            ) : (
                <Form {...StoreShippingController.update.form()} className="space-y-4">
                    {({ processing }) => (
                        <>
                            <input type="hidden" name="store_id" value={currentStoreId ?? ''} />
                            {methods.map((method, index) => (
                                <div key={method.shipping_method_id} className="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800">
                                    <input type="hidden" name={`methods[${index}][shipping_method_id]`} value={method.shipping_method_id} />
                                    <div className="mb-3 flex items-center justify-between">
                                        <div>
                                            <span className="font-medium">{method.name}</span>
                                            <span className="ml-2 text-xs text-neutral-400">{TYPE_LABELS[method.type] ?? method.type}</span>
                                        </div>
                                        <label className="flex items-center gap-2 text-sm">
                                            <input type="hidden" name={`methods[${index}][enabled]`} value="0" />
                                            <input type="checkbox" name={`methods[${index}][enabled]`} value="1" defaultChecked={method.enabled} className="size-4 rounded" />
                                            Habilitado en esta tienda
                                        </label>
                                    </div>
                                    <div className="grid gap-3 sm:grid-cols-3">
                                        <div className="grid gap-1.5">
                                            <Label>Etiqueta</Label>
                                            <Input name={`methods[${index}][label]`} defaultValue={method.label ?? ''} placeholder={method.name} />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Costo {method.type !== 'flat_rate' && '(gratis)'}</Label>
                                            <Input name={`methods[${index}][amount]`} type="number" step="0.01" min={0} defaultValue={method.amount ?? ''} disabled={method.type !== 'flat_rate'} />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Gratis a partir de</Label>
                                            <Input name={`methods[${index}][free_over]`} type="number" step="0.01" min={0} defaultValue={method.free_over ?? ''} />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Subtotal mínimo</Label>
                                            <Input name={`methods[${index}][min_subtotal]`} type="number" step="0.01" min={0} defaultValue={method.min_subtotal ?? ''} />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Subtotal máximo</Label>
                                            <Input name={`methods[${index}][max_subtotal]`} type="number" step="0.01" min={0} defaultValue={method.max_subtotal ?? ''} />
                                        </div>
                                        <div className="grid gap-1.5">
                                            <Label>Países (ISO2, coma)</Label>
                                            <Input name={`methods[${index}][countries]`} defaultValue={method.countries ?? ''} placeholder="MX, US" />
                                        </div>
                                    </div>
                                </div>
                            ))}
                            <Button disabled={processing}>Guardar configuración</Button>
                        </>
                    )}
                </Form>
            )}
        </>
    );
}
