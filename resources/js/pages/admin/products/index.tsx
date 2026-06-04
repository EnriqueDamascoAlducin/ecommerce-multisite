import { Form, Head, Link, router } from '@inertiajs/react';
import { ImageIcon } from 'lucide-react';
import { ProductLabels, type ProductLabelData } from '@/components/product-labels';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import products from '@/routes/admin/products';

type ProductRow = {
    id: number;
    sku: string;
    name: string;
    status: string;
    price: string | null;
    thumbnail: string | null;
    labels: ProductLabelData[];
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

export default function ProductsIndex({
    products: page,
    filters,
}: {
    products: Paginated<ProductRow>;
    filters: { search: string; status: string };
}) {
    const { can } = usePermissions();

    const destroy = (product: ProductRow) => {
        if (confirm(`¿Eliminar el producto ${product.sku}?`)) {
            router.delete(products.destroy(product.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Productos" />

            <div className="mb-6 flex items-center justify-between">
                <h1 className="text-2xl font-semibold">Productos</h1>
                {can('catalog.products.create') && (
                    <Button asChild>
                        <Link href={products.create()}>Nuevo producto</Link>
                    </Button>
                )}
            </div>

            <Form {...products.index.form()} className="mb-4 flex flex-wrap gap-2" options={{ preserveState: true }}>
                <Input name="search" defaultValue={filters.search} placeholder="Buscar por nombre o SKU" className="max-w-xs" />
                <select name="status" defaultValue={filters.status} className="rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800">
                    <option value="">Todos los estados</option>
                    <option value="active">Activo</option>
                    <option value="inactive">Inactivo</option>
                </select>
                <Button variant="outline">Filtrar</Button>
            </Form>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Imagen</th>
                            <th className="px-4 py-3 font-medium">SKU</th>
                            <th className="px-4 py-3 font-medium">Nombre</th>
                            <th className="px-4 py-3 font-medium">Precio</th>
                            <th className="px-4 py-3 font-medium">Estado</th>
                            <th className="px-4 py-3 text-right font-medium">Acciones</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {page.data.map((product) => (
                            <tr key={product.id}>
                                <td className="px-4 py-3">
                                    <div className="flex size-10 items-center justify-center overflow-hidden rounded bg-neutral-100 dark:bg-neutral-800">
                                        {product.thumbnail ? (
                                            <img src={product.thumbnail} alt={product.name} className="h-full w-full object-cover" />
                                        ) : (
                                            <ImageIcon className="size-4 text-neutral-400" />
                                        )}
                                    </div>
                                </td>
                                <td className="px-4 py-3 font-mono text-xs">{product.sku}</td>
                                <td className="px-4 py-3">
                                    <div className="flex flex-col gap-1">
                                        <span>{product.name}</span>
                                        <ProductLabels labels={product.labels} />
                                    </div>
                                </td>
                                <td className="px-4 py-3">{product.price ?? '—'}</td>
                                <td className="px-4 py-3">
                                    <Badge variant={product.status === 'active' ? 'default' : 'outline'}>
                                        {product.status === 'active' ? 'Activo' : 'Inactivo'}
                                    </Badge>
                                </td>
                                <td className="px-4 py-3">
                                    <div className="flex justify-end gap-2">
                                        {can('catalog.products.edit') && (
                                            <Button variant="outline" size="sm" asChild>
                                                <Link href={products.edit(product.id)}>Editar</Link>
                                            </Button>
                                        )}
                                        {can('catalog.products.delete') && (
                                            <Button variant="destructive" size="sm" onClick={() => destroy(product)}>
                                                Eliminar
                                            </Button>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                        {page.data.length === 0 && (
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
                <span>{page.total} productos</span>
                <div className="flex gap-2">
                    {page.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.prev_page_url} preserveScroll>Anterior</Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Anterior</Button>
                    )}
                    {page.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.next_page_url} preserveScroll>Siguiente</Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Siguiente</Button>
                    )}
                </div>
            </div>
        </>
    );
}
