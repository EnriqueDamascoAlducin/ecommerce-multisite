import { Head, Link, useForm } from '@inertiajs/react';
import { BadgeCheck, Mail, ShoppingBag } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatPrice } from '@/lib/storefront';
import customers from '@/routes/admin/customers';
import CustomerFields, { type AddressForm, type CustomerData } from './customer-fields';

type WebsiteOption = { id: number; name: string };
type GroupOption = { id: number; name: string; website_id: number; color: string };

type Customer = {
    id: number;
    website_id: number;
    website: string;
    group_id: number | null;
    name: string;
    email: string;
    phone: string | null;
    verified: boolean;
    created_at: string | null;
    addresses: AddressForm[];
};

type Stats = { orders_count: number; total_spent: string; last_order_at: string | null };
type RecentOrder = { number: string; total: string; status: string; placed_at: string | null; url: string };

function initials(name: string): string {
    return name
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
}

export default function CustomerEdit({
    customer,
    stats,
    recentOrders,
    groups,
    websites,
}: {
    customer: Customer;
    stats: Stats;
    recentOrders: RecentOrder[];
    groups: GroupOption[];
    websites: WebsiteOption[];
}) {
    const { data, setData, put, processing, errors } = useForm<CustomerData>({
        website_id: customer.website_id,
        group_id: customer.group_id ?? '',
        name: customer.name,
        email: customer.email,
        phone: customer.phone ?? '',
        password: '',
        addresses: customer.addresses,
    });

    const group = groups.find((g) => g.id === customer.group_id);
    const submit = () => put(customers.update(customer.id).url);

    return (
        <>
            <Head title={customer.name} />

            <div className="mb-6 flex items-center justify-between">
                <Button variant="outline" asChild>
                    <Link href={customers.index()}>← Clientes</Link>
                </Button>
            </div>

            {/* Encabezado-resumen */}
            <div className="mb-6 rounded-xl border border-neutral-200 p-6 dark:border-neutral-800">
                <div className="flex flex-wrap items-center gap-4">
                    <div className="flex size-14 items-center justify-center rounded-full bg-neutral-900 text-lg font-semibold text-white dark:bg-neutral-100 dark:text-neutral-900">
                        {initials(customer.name)}
                    </div>
                    <div className="min-w-0">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="text-2xl font-semibold">{customer.name}</h1>
                            {group && (
                                <Badge className="border-transparent" style={{ backgroundColor: group.color, color: '#ffffff' }}>
                                    {group.name}
                                </Badge>
                            )}
                            {customer.verified ? (
                                <span className="inline-flex items-center gap-1 text-xs text-green-600">
                                    <BadgeCheck className="size-4" /> Verificado
                                </span>
                            ) : (
                                <span className="text-xs text-neutral-400">No verificado</span>
                            )}
                        </div>
                        <p className="flex items-center gap-1 text-sm text-neutral-500">
                            <Mail className="size-3.5" /> {customer.email} · {customer.website}
                        </p>
                    </div>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-4">
                    <Stat label="Pedidos" value={String(stats.orders_count)} />
                    <Stat label="Total gastado" value={formatPrice(stats.total_spent)} />
                    <Stat label="Última compra" value={stats.last_order_at ?? '—'} />
                    <Stat label="Registrado" value={customer.created_at ?? '—'} />
                </div>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                <div className="lg:col-span-2">
                    <Card>
                        <CardContent className="pt-6">
                            <CustomerFields
                                data={data}
                                setData={setData}
                                errors={errors}
                                websites={websites}
                                groups={groups}
                                isCreate={false}
                            />
                            <div className="mt-6 flex justify-end">
                                <Button onClick={submit} disabled={processing}>
                                    Guardar cambios
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div>
                    <Card>
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2 text-base">
                                <ShoppingBag className="size-4" /> Pedidos recientes
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3">
                            {recentOrders.length === 0 && (
                                <p className="text-sm text-neutral-500">Sin pedidos todavía.</p>
                            )}
                            {recentOrders.map((order) => (
                                <Link
                                    key={order.number}
                                    href={order.url}
                                    className="flex items-center justify-between rounded-md border border-neutral-100 px-3 py-2 text-sm transition-colors hover:bg-neutral-50 dark:border-neutral-800 dark:hover:bg-neutral-800/50"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium">{order.number}</p>
                                        <p className="text-xs text-neutral-500">
                                            {order.placed_at ?? '—'} · {order.status}
                                        </p>
                                    </div>
                                    <span className="font-semibold">{formatPrice(order.total)}</span>
                                </Link>
                            ))}
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

function Stat({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-lg bg-neutral-50 p-4 dark:bg-neutral-900/50">
            <p className="text-xs text-neutral-500">{label}</p>
            <p className="mt-1 text-lg font-semibold">{value}</p>
        </div>
    );
}
