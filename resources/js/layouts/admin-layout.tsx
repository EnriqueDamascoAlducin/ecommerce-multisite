import { Link, router, usePage } from '@inertiajs/react';
import {
    BarChart3,
    Boxes,
    ChevronRight,
    CreditCard,
    FileText,
    FolderTree,
    Globe,
    Image,
    KeyRound,
    LayoutDashboard,
    Menu,
    Package,
    PackageCheck,
    Percent,
    Receipt,
    ScrollText,
    Settings,
    ShieldCheck,
    Store,
    Tags,
    Ticket,
    Truck,
    Users,
    Warehouse,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';
import { dashboard } from '@/routes/admin';
import attributes from '@/routes/admin/attributes';
import audit from '@/routes/admin/audit';
import catalogRules from '@/routes/admin/catalog-rules';
import categories from '@/routes/admin/categories';
import configuration from '@/routes/admin/configuration';
import headerMenu from '@/routes/admin/header-menu';
import inventory from '@/routes/admin/inventory';
import inventorySources from '@/routes/admin/inventory-sources';
import invoices from '@/routes/admin/invoices';
import media from '@/routes/admin/media';
import adminOrders from '@/routes/admin/orders';
import payments from '@/routes/admin/payments';
import permissions from '@/routes/admin/permissions';
import products from '@/routes/admin/products';
import promotions from '@/routes/admin/promotions';
import reports from '@/routes/admin/reports';
import roles from '@/routes/admin/roles';
import { update as updateScope } from '@/routes/admin/scope';
import shipments from '@/routes/admin/shipments';
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

type AdminNavGroup = {
    title: string;
    icon: LucideIcon;
    items: AdminNavItem[];
};

const navGroups: AdminNavGroup[] = [
    {
        title: 'Dashboard',
        icon: LayoutDashboard,
        items: [
            { title: 'Dashboard', href: dashboard(), icon: LayoutDashboard },
        ],
    },
    {
        title: 'Ventas',
        icon: Receipt,
        items: [
            {
                title: 'Órdenes',
                href: adminOrders.index(),
                icon: Receipt,
                permission: 'sales.orders.view',
            },
            {
                title: 'Facturas',
                href: invoices.index(),
                icon: FileText,
                permission: 'sales.invoices.view',
            },
            {
                title: 'Envíos',
                href: shipments.index(),
                icon: PackageCheck,
                permission: 'sales.shipments.view',
            },
        ],
    },
    {
        title: 'Catálogo',
        icon: Package,
        items: [
            {
                title: 'Productos',
                href: products.index(),
                icon: Package,
                permission: 'catalog.products.view',
            },
            {
                title: 'Categorías',
                href: categories.index(),
                icon: FolderTree,
                permission: 'catalog.categories.view',
            },
            {
                title: 'Atributos',
                href: attributes.index(),
                icon: Tags,
                permission: 'catalog.attributes.view',
            },
            {
                title: 'Inventario',
                href: inventory.index(),
                icon: Boxes,
                permission: 'inventory.view',
            },
            {
                title: 'Almacenes',
                href: inventorySources.index(),
                icon: Warehouse,
                permission: 'inventory.view',
            },
        ],
    },
    {
        title: 'Marketing',
        icon: Ticket,
        items: [
            {
                title: 'Promociones',
                href: promotions.index(),
                icon: Ticket,
                permission: 'promotions.view',
            },
            {
                title: 'Reglas de catálogo',
                href: catalogRules.index(),
                icon: Percent,
                permission: 'promotions.view',
            },
        ],
    },
    {
        title: 'Contenido',
        icon: Image,
        items: [
            {
                title: 'Medios',
                href: media.index(),
                icon: Image,
                permission: 'media.view',
            },
        ],
    },
    {
        title: 'Tiendas',
        icon: Store,
        items: [
            {
                title: 'Websites',
                href: websites.index(),
                icon: Globe,
                permission: 'settings.stores',
            },
            {
                title: 'Tiendas',
                href: stores.index(),
                icon: Store,
                permission: 'settings.stores',
            },
            {
                title: 'Configuración',
                href: configuration.index(),
                icon: Settings,
                permission: 'settings.stores',
            },
            {
                title: 'Métodos de envío',
                href: shipping.index(),
                icon: Truck,
                permission: 'settings.shipping',
            },
            {
                title: 'Pasarelas de pago',
                href: payments.index(),
                icon: CreditCard,
                permission: 'settings.payments',
            },
            {
                title: 'Menú del header',
                href: headerMenu.index(),
                icon: Menu,
                permission: 'settings.storefront',
            },
        ],
    },
    {
        title: 'Sistema',
        icon: ShieldCheck,
        items: [
            {
                title: 'Usuarios',
                href: users.index(),
                icon: Users,
                permission: 'admin.users.view',
            },
            {
                title: 'Roles',
                href: roles.index(),
                icon: ShieldCheck,
                permission: 'admin.roles.view',
            },
            {
                title: 'Permisos',
                href: permissions.index(),
                icon: KeyRound,
                permission: 'admin.roles.view',
            },
            {
                title: 'Auditoría',
                href: audit.index(),
                icon: ScrollText,
                permission: 'audit.view',
            },
        ],
    },
    {
        title: 'Reportes',
        icon: BarChart3,
        items: [
            {
                title: 'Reportes',
                href: reports.index(),
                icon: BarChart3,
                permission: 'reports.view',
            },
        ],
    },
];

function AdminNavGroup({
    group,
    isCurrentOrParentUrl,
}: {
    group: AdminNavGroup;
    isCurrentOrParentUrl: (href: AdminNavItem['href']) => boolean;
}) {
    const hasActiveItem = group.items.some((item) =>
        isCurrentOrParentUrl(item.href),
    );
    const [isOpen, setIsOpen] = useState(hasActiveItem);

    return (
        <Collapsible open={isOpen} onOpenChange={setIsOpen}>
            <CollapsibleTrigger
                className={cn(
                    'flex w-full items-center gap-2 rounded-md px-3 py-2 text-left text-neutral-700 transition-colors hover:bg-neutral-100 dark:text-neutral-300 dark:hover:bg-neutral-800',
                    hasActiveItem &&
                        'bg-neutral-100 font-medium text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100',
                )}
            >
                <group.icon className="size-4 shrink-0" />
                <span className="min-w-0 flex-1 truncate">{group.title}</span>
                <ChevronRight
                    className={cn(
                        'size-4 shrink-0 transition-transform',
                        isOpen && 'rotate-90',
                    )}
                />
            </CollapsibleTrigger>

            <CollapsibleContent className="mt-1 space-y-1">
                {group.items.map((item) => (
                    <Link
                        key={item.title}
                        href={item.href}
                        prefetch
                        className={cn(
                            'ml-6 flex items-center gap-2 rounded-md px-3 py-2 text-neutral-600 transition-colors hover:bg-neutral-100 dark:text-neutral-400 dark:hover:bg-neutral-800',
                            isCurrentOrParentUrl(item.href) &&
                                'bg-neutral-100 font-medium text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100',
                        )}
                    >
                        <item.icon className="size-4 shrink-0" />
                        <span className="min-w-0 truncate">{item.title}</span>
                    </Link>
                ))}
            </CollapsibleContent>
        </Collapsible>
    );
}

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

    const visibleGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);

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
                        <label className="text-xs font-medium text-neutral-500">
                            Scope
                        </label>
                        <select
                            value={`${adminScope.current.type}:${adminScope.current.id}`}
                            onChange={onScopeChange}
                            className="mt-1 w-full rounded-md border border-neutral-300 bg-white px-2 py-1.5 text-sm dark:border-neutral-700 dark:bg-neutral-800"
                        >
                            {adminScope.options.map((option) => (
                                <option
                                    key={`${option.type}:${option.id}`}
                                    value={`${option.type}:${option.id}`}
                                >
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <nav className="mt-6 flex flex-col gap-1 text-sm">
                    {visibleGroups.map((group) => (
                        <AdminNavGroup
                            key={`${group.title}:${group.items.some((item) => isCurrentOrParentUrl(item.href))}`}
                            group={group}
                            isCurrentOrParentUrl={isCurrentOrParentUrl}
                        />
                    ))}
                </nav>
            </aside>

            <main className="flex-1 p-6">{children}</main>
        </div>
    );
}
