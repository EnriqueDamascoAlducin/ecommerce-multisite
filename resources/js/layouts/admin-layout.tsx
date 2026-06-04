import { Link, router, usePage } from '@inertiajs/react';
import {
    FileText,
    FolderTree,
    Globe,
    Image,
    KeyRound,
    LayoutDashboard,
    Package,
    PackageCheck,
    Receipt,
    Settings,
    ShieldCheck,
    Store,
    Tags,
    Truck,
    Users,
    Warehouse,
    Boxes,
    type LucideIcon,
} from 'lucide-react';
import { useEffect } from 'react';
import { toast } from 'sonner';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import attributes from '@/routes/admin/attributes';
import categories from '@/routes/admin/categories';
import configuration from '@/routes/admin/configuration';
import inventory from '@/routes/admin/inventory';
import inventorySources from '@/routes/admin/inventory-sources';
import invoices from '@/routes/admin/invoices';
import media from '@/routes/admin/media';
import adminOrders from '@/routes/admin/orders';
import shipments from '@/routes/admin/shipments';
import permissions from '@/routes/admin/permissions';
import products from '@/routes/admin/products';
import roles from '@/routes/admin/roles';
import { update as updateScope } from '@/routes/admin/scope';
import shipping from '@/routes/admin/shipping';
import stores from '@/routes/admin/stores';
import users from '@/routes/admin/users';
import websites from '@/routes/admin/websites';

type AdminNavItem = {
    title: string;
    href: ReturnType<typeof dashboard>;
    icon: LucideIcon;
    permission?: string;
};

const navItems: AdminNavItem[] = [
    { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
    { title: 'Usuarios', href: users.index(), icon: Users, permission: 'admin.users.view' },
    { title: 'Roles', href: roles.index(), icon: ShieldCheck, permission: 'admin.roles.view' },
    { title: 'Permisos', href: permissions.index(), icon: KeyRound, permission: 'admin.roles.view' },
    { title: 'Productos', href: products.index(), icon: Package, permission: 'catalog.products.view' },
    { title: 'Categorías', href: categories.index(), icon: FolderTree, permission: 'catalog.categories.view' },
    { title: 'Atributos', href: attributes.index(), icon: Tags, permission: 'catalog.attributes.view' },
    { title: 'Inventario', href: inventory.index(), icon: Boxes, permission: 'inventory.view' },
    { title: 'Almacenes', href: inventorySources.index(), icon: Warehouse, permission: 'inventory.view' },
    { title: 'Órdenes', href: adminOrders.index(), icon: Receipt, permission: 'sales.orders.view' },
    { title: 'Facturas', href: invoices.index(), icon: FileText, permission: 'sales.invoices.view' },
    { title: 'Envíos', href: shipments.index(), icon: PackageCheck, permission: 'sales.shipments.view' },
    { title: 'Métodos de envío', href: shipping.index(), icon: Truck, permission: 'settings.shipping' },
    { title: 'Medios', href: media.index(), icon: Image, permission: 'media.view' },
    { title: 'Websites', href: websites.index(), icon: Globe, permission: 'settings.stores' },
    { title: 'Tiendas', href: stores.index(), icon: Store, permission: 'settings.stores' },
    { title: 'Configuración', href: configuration.index(), icon: Settings, permission: 'settings.stores' },
];

export default function AdminLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { can } = usePermissions();
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { flash, adminScope } = usePage().props;

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }
        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    const visibleItems = navItems.filter(
        (item) => !item.permission || can(item.permission),
    );

    const onScopeChange = (event: React.ChangeEvent<HTMLSelectElement>) => {
        const [type, id] = event.target.value.split(':');
        router.post(
            updateScope().url,
            { type, id: Number(id) },
            { preserveScroll: true, preserveState: false },
        );
    };

    return (
        <div className="flex min-h-screen bg-neutral-100 text-neutral-900 dark:bg-neutral-950 dark:text-neutral-100">
            <aside className="hidden w-64 shrink-0 border-r border-neutral-200 bg-white p-4 md:block dark:border-neutral-800 dark:bg-neutral-900">
                <Link href={dashboard()} className="text-lg font-semibold">
                    Admin
                </Link>

                {adminScope && can('settings.stores') && (
                    <div className="mt-4">
                        <label className="text-xs font-medium text-neutral-500">Scope</label>
                        <select
                            value={`${adminScope.current.type}:${adminScope.current.id}`}
                            onChange={onScopeChange}
                            className="mt-1 w-full rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            {adminScope.options.map((option) => (
                                <option key={`${option.type}:${option.id}`} value={`${option.type}:${option.id}`}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <nav className="mt-6 flex flex-col gap-1 text-sm">
                    {visibleItems.map((item) => (
                        <Link
                            key={item.title}
                            href={item.href}
                            className={cn(
                                'flex items-center gap-2 rounded-md px-3 py-2 text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800',
                                isCurrentOrParentUrl(item.href) &&
                                    'bg-neutral-100 font-medium text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100',
                            )}
                        >
                            <item.icon className="size-4" />
                            <span>{item.title}</span>
                        </Link>
                    ))}
                </nav>
            </aside>

            <main className="flex-1 p-6">{children}</main>
        </div>
    );
}
