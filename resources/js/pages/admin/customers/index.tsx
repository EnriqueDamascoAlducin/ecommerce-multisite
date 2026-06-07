import { Form, Head, Link, router } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import { formatPrice } from '@/lib/storefront';
import customers from '@/routes/admin/customers';

type CustomerRow = {
    id: number;
    name: string;
    email: string;
    phone: string | null;
    website: string;
    group: { name: string; color: string } | null;
    orders_count: number;
    total_spent: string;
    last_order_at: string | null;
    verified: boolean;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    from: number | null;
    to: number | null;
    total: number;
};

type Filters = { search: string; website_id: number | null; group_id: number | null; verified: string };

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

function initials(name: string): string {
    return name.split(' ').map((p) => p[0]).filter(Boolean).slice(0, 2).join('').toUpperCase();
}

export default function CustomersIndex({
    customers: page,
    filters,
    websites,
    groups,
}: {
    customers: Paginated<CustomerRow>;
    filters: Filters;
    websites: { id: number; name: string }[];
    groups: { id: number; name: string; website_id: number; color: string }[];
}) {
    const { can } = usePermissions();

    const remove = (customer: CustomerRow) => {
        if (confirm(`¿Eliminar al cliente ${customer.email}? Sus pedidos se conservarán sin asociar.`)) {
            router.delete(customers.destroy(customer.id).url, { preserveScroll: true });
        }
    };

    return (
        <>
            <Head title="Clientes" />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-semibold">Clientes</h1>
                    <p className="text-sm text-neutral-500">{page.total} clientes registrados.</p>
                </div>
                {can('customers.create') && (
                    <Button asChild>
                        <Link href={customers.create()}>Nuevo cliente</Link>
                    </Button>
                )}
            </div>

            <Form {...customers.index.form()} className="mb-4 flex flex-wrap gap-2" options={{ preserveState: true }}>
                <Input name="search" defaultValue={filters.search} placeholder="Nombre, email o teléfono…" className="w-64" />
                <select name="website_id" defaultValue={filters.website_id ?? ''} className={fieldClass}>
                    <option value="">Todos los sitios</option>
                    {websites.map((w) => (
                        <option key={w.id} value={w.id}>
                            {w.name}
                        </option>
                    ))}
                </select>
                <select name="group_id" defaultValue={filters.group_id ?? ''} className={fieldClass}>
                    <option value="">Todos los grupos</option>
                    {groups.map((g) => (
                        <option key={g.id} value={g.id}>
                            {g.name}
                        </option>
                    ))}
                </select>
                <select name="verified" defaultValue={filters.verified} className={fieldClass}>
                    <option value="">Verificación</option>
                    <option value="yes">Verificados</option>
                    <option value="no">No verificados</option>
                </select>
                <Button variant="outline">Filtrar</Button>
            </Form>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Cliente</th>
                            <th className="px-4 py-3 font-medium">Teléfono</th>
                            <th className="px-4 py-3 font-medium">Sitio</th>
                            <th className="px-4 py-3 font-medium">Grupo</th>
                            <th className="px-4 py-3 font-medium">Pedidos</th>
                            <th className="px-4 py-3 font-medium">Total gastado</th>
                            <th className="px-4 py-3 font-medium">Registrado</th>
                            <th className="px-4 py-3"></th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {page.data.map((customer) => (
                            <tr key={customer.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td className="px-4 py-3">
                                    <div className="flex items-center gap-3">
                                        <div className="flex size-9 shrink-0 items-center justify-center rounded-full bg-neutral-200 text-xs font-semibold text-neutral-700 dark:bg-neutral-700 dark:text-neutral-200">
                                            {initials(customer.name)}
                                        </div>
                                        <div className="min-w-0">
                                            <p className="font-medium">{customer.name}</p>
                                            <p className="text-xs text-neutral-500">{customer.email}</p>
                                        </div>
                                    </div>
                                </td>
                                <td className="px-4 py-3 text-neutral-500">{customer.phone ?? '—'}</td>
                                <td className="px-4 py-3 text-neutral-500">{customer.website}</td>
                                <td className="px-4 py-3">
                                    {customer.group ? (
                                        <Badge className="border-transparent" style={{ backgroundColor: customer.group.color, color: '#ffffff' }}>
                                            {customer.group.name}
                                        </Badge>
                                    ) : (
                                        <span className="text-neutral-400">—</span>
                                    )}
                                </td>
                                <td className="px-4 py-3 text-neutral-500">{customer.orders_count}</td>
                                <td className="px-4 py-3 font-medium">{formatPrice(customer.total_spent)}</td>
                                <td className="px-4 py-3 text-neutral-500">{customer.created_at ?? '—'}</td>
                                <td className="px-4 py-3 text-right">
                                    {can('customers.edit') && (
                                        <Link href={customers.edit(customer.id)} className="text-sm hover:underline">
                                            Ver / Editar
                                        </Link>
                                    )}
                                    {can('customers.delete') && (
                                        <button type="button" onClick={() => remove(customer)} className="ml-3 text-sm text-red-600 hover:underline">
                                            Eliminar
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {page.data.length === 0 && (
                            <tr>
                                <td colSpan={8} className="px-4 py-8 text-center text-neutral-500">
                                    No hay clientes que coincidan.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-neutral-500">
                <span>
                    {page.from ?? 0}–{page.to ?? 0} de {page.total}
                </span>
                <div className="flex gap-2">
                    {page.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.prev_page_url} preserveScroll>
                                Anterior
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Anterior
                        </Button>
                    )}
                    {page.next_page_url ? (
                        <Button variant="outline" size="sm" asChild>
                            <Link href={page.next_page_url} preserveScroll>
                                Siguiente
                            </Link>
                        </Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>
                            Siguiente
                        </Button>
                    )}
                </div>
            </div>
        </>
    );
}
