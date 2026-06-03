import { Form, Head, Link } from '@inertiajs/react';
import InventoryController from '@/actions/App/Http/Controllers/Admin/InventoryController';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import inventory from '@/routes/admin/inventory';

type SourceStock = {
    source_id: number;
    source_name: string;
    physical_qty: number;
    reserved_qty: number;
    available_qty: number;
    manage_stock: boolean;
    allow_backorders: boolean;
    low_stock_threshold: number | null;
};

type Movement = {
    id: number;
    type: string;
    quantity: number;
    balance_after: number;
    reason: string | null;
    source: string | null;
    created_at: string | null;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function InventoryEdit({
    product,
    stockBySource,
    movements,
}: {
    product: { id: number; sku: string; name: string };
    stockBySource: SourceStock[];
    movements: Movement[];
}) {
    return (
        <>
            <Head title={`Inventario · ${product.name}`} />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">{product.name}</h1>
                    <p className="font-mono text-xs text-neutral-500">{product.sku}</p>
                </div>
                <Button variant="outline" asChild>
                    <Link href={inventory.index()}>Volver</Link>
                </Button>
            </div>

            <div className="space-y-4">
                {stockBySource.map((stock) => (
                    <Form
                        key={stock.source_id}
                        {...InventoryController.update.form(product.id)}
                        className="rounded-lg border border-neutral-200 p-4 dark:border-neutral-800"
                    >
                        {({ processing }) => (
                            <>
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="text-sm font-semibold">{stock.source_name}</h2>
                                    <span className="text-xs text-neutral-500">
                                        Reservado: {stock.reserved_qty} · Disponible: {stock.available_qty}
                                    </span>
                                </div>
                                <input type="hidden" name="inventory_source_id" value={stock.source_id} />
                                <div className="grid items-end gap-3 sm:grid-cols-4">
                                    <div className="grid gap-2">
                                        <Label>Stock físico</Label>
                                        <Input name="physical_qty" type="number" min={0} defaultValue={stock.physical_qty} />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label>Umbral stock bajo</Label>
                                        <Input name="low_stock_threshold" type="number" min={0} defaultValue={stock.low_stock_threshold ?? ''} />
                                    </div>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input type="hidden" name="manage_stock" value="0" />
                                        <input type="checkbox" name="manage_stock" value="1" defaultChecked={stock.manage_stock} className="size-4 rounded" />
                                        Gestionar stock
                                    </label>
                                    <label className="flex items-center gap-2 text-sm">
                                        <input type="hidden" name="allow_backorders" value="0" />
                                        <input type="checkbox" name="allow_backorders" value="1" defaultChecked={stock.allow_backorders} className="size-4 rounded" />
                                        Permitir backorders
                                    </label>
                                    <div className="grid gap-2 sm:col-span-3">
                                        <Label>Motivo (opcional)</Label>
                                        <Input name="reason" placeholder="Ajuste manual, recepción, merma…" className={fieldClass} />
                                    </div>
                                    <div className="flex items-end">
                                        <Button disabled={processing}>Guardar</Button>
                                    </div>
                                </div>
                            </>
                        )}
                    </Form>
                ))}
                {stockBySource.length === 0 && (
                    <p className="text-sm text-neutral-500">No hay almacenes. Crea uno en la sección Almacenes.</p>
                )}
            </div>

            <section className="mt-8">
                <h2 className="mb-3 text-sm font-semibold">Movimientos recientes</h2>
                <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                    <table className="w-full text-left text-sm">
                        <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                            <tr>
                                <th className="px-4 py-3 font-medium">Fecha</th>
                                <th className="px-4 py-3 font-medium">Almacén</th>
                                <th className="px-4 py-3 font-medium">Tipo</th>
                                <th className="px-4 py-3 text-right font-medium">Cantidad</th>
                                <th className="px-4 py-3 text-right font-medium">Saldo</th>
                                <th className="px-4 py-3 font-medium">Motivo</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                            {movements.map((movement) => (
                                <tr key={movement.id}>
                                    <td className="px-4 py-3 text-xs text-neutral-500">{movement.created_at}</td>
                                    <td className="px-4 py-3">{movement.source ?? '—'}</td>
                                    <td className="px-4 py-3">{movement.type}</td>
                                    <td className={`px-4 py-3 text-right ${movement.quantity < 0 ? 'text-red-500' : 'text-green-600'}`}>
                                        {movement.quantity > 0 ? `+${movement.quantity}` : movement.quantity}
                                    </td>
                                    <td className="px-4 py-3 text-right">{movement.balance_after}</td>
                                    <td className="px-4 py-3 text-neutral-500">{movement.reason ?? '—'}</td>
                                </tr>
                            ))}
                            {movements.length === 0 && (
                                <tr>
                                    <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                        Sin movimientos todavía.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </>
    );
}
