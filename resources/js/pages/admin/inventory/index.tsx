import { Form, Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import inventory from '@/routes/admin/inventory';

type ProductStockRow = {
    id: number;
    sku: string;
    name: string;
    physical: number;
    reserved: number;
    available: number;
    low_stock: boolean;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export default function InventoryIndex({
    products,
    filters,
}: {
    products: Paginated<ProductStockRow>;
    filters: { search: string };
}) {
    const { can } = usePermissions();

    return (
        <>
            <Head title="Inventario" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Inventario</h1>
            </div>

            <Form {...inventory.index.form()} className="mb-4 flex flex-wrap gap-2" options={{ preserveState: true }}>
                <Input name="search" defaultValue={filters.search} placeholder="Buscar por nombre o SKU" className="max-w-xs" />
                <Button variant="outline">Buscar</Button>
            </Form>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">SKU</th>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 text-right font-medium">Físico</th>
                            <th className="px-4 py-3 text-right font-medium">Reservado</th>
                            <th className="px-4 py-3 text-right font-medium">Disponible</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {products.data.map((product) => (
                            <tr key={product.id}>
                                <td className="px-4 py-3 font-mono text-xs">{product.sku}</td>
                                <td className="px-4 py-3">
                                    <span className="flex items-center gap-2">
                                        {product.name}
                                        {product.low_stock && <Badge variant="destructive">Stock bajo</Badge>}
                                    </span>
                                </td>
                                <td className="px-4 py-3 text-right">{product.physical}</td>
                                <td className="px-4 py-3 text-right text-neutral-500">{product.reserved}</td>
                                <td className="px-4 py-3 text-right font-medium">{product.available}</td>
                                <td className="px-4 py-3 text-right">
                                    {can('inventory.view') && (
                                        <Button variant="outline" size="sm" asChild>
                                            <Link href={inventory.edit(product.id)}>Gestionar</Link>
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {products.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">
                                    No hay productos.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-neutral-500">
                <span>{products.total} productos</span>
                <div className="flex gap-2">
                    {products.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={products.prev_page_url} preserveScroll>Anterior</Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Anterior</Button>
                    )}
                    {products.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={products.next_page_url} preserveScroll>Siguiente</Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Siguiente</Button>
                    )}
                </div>
            </div>
        </>
    );
}
