import { Link, usePage } from '@inertiajs/react';
import {
    Boxes,
    ChevronRight,
    CreditCard,
    FileText,
    FolderTree,
    Globe,
    Image,
    KeyRound,
    LayoutDashboard,
    Megaphone,
    Menu,
    Package,
    PackageCheck,
    PanelTop,
    Percent,
    Receipt,
    ScrollText,
    Settings,
    ShieldCheck,
    Store,
    Tag,
    Tags,
    Ticket,
    Truck,
    Users,
    Warehouse,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { AppShell } from '@/components/app-shell';
import { NavUser } from '@/components/nav-user';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarGroup,
    SidebarGroupContent,
    SidebarGroupLabel,
    SidebarHeader,
    SidebarInset,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarTrigger,
    useSidebar,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { usePermissions } from '@/hooks/use-permissions';
import { dashboard } from '@/routes/admin';
import attributes from '@/routes/admin/attributes';
import audit from '@/routes/admin/audit';
import catalogRules from '@/routes/admin/catalog-rules';
import categories from '@/routes/admin/categories';
import configuration from '@/routes/admin/configuration';
import customerGroups from '@/routes/admin/customer-groups';
import customers from '@/routes/admin/customers';
import headerMenu from '@/routes/admin/header-menu';
import headerSettings from '@/routes/admin/header-settings';
import inventory from '@/routes/admin/inventory';
import inventorySources from '@/routes/admin/inventory-sources';
import invoices from '@/routes/admin/invoices';
import media from '@/routes/admin/media';
import adminOrders from '@/routes/admin/orders';
import payments from '@/routes/admin/payments';
import permissions from '@/routes/admin/permissions';
import productLabels from '@/routes/admin/product-labels';
import products from '@/routes/admin/products';
import promotions from '@/routes/admin/promotions';
import roles from '@/routes/admin/roles';
import shipments from '@/routes/admin/shipments';
import shipping from '@/routes/admin/shipping';
import storefrontPages from '@/routes/admin/storefront/pages';
import stores from '@/routes/admin/stores';
import users from '@/routes/admin/users';
import websites from '@/routes/admin/websites';
import { edit as editProfile } from '@/routes/profile';
import { edit as editSecurity } from '@/routes/security';
import { edit as editAppearance } from '@/routes/appearance';

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
        title: 'Clientes',
        icon: Users,
        items: [
            {
                title: 'Clientes',
                href: customers.index(),
                icon: Users,
                permission: 'customers.view',
            },
            {
                title: 'Grupos de clientes',
                href: customerGroups.index(),
                icon: Tags,
                permission: 'customer_groups.view',
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
            {
                title: 'Etiquetas',
                href: productLabels.index(),
                icon: Tag,
                permission: 'catalog.labels.view',
            },
        ],
    },
    {
        title: 'Contenido',
        icon: PanelTop,
        items: [
            {
                title: 'Páginas',
                href: storefrontPages.index(),
                icon: LayoutDashboard,
                permission: 'settings.storefront',
            },
            {
                title: 'Header y footer',
                href: headerSettings.edit(),
                icon: Megaphone,
                permission: 'settings.storefront',
            },
            {
                title: 'Menú del header',
                href: headerMenu.index(),
                icon: Menu,
                permission: 'settings.storefront',
            },
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
        title: 'Cuenta',
        icon: Users,
        items: [
            {
                title: 'Perfil',
                href: editProfile(),
                icon: Users,
            },
            {
                title: 'Seguridad',
                href: editSecurity(),
                icon: ShieldCheck,
            },
            {
                title: 'Apariencia',
                href: editAppearance(),
                icon: LayoutDashboard,
            },
        ],
    },
];

function AdminNavGroup({ group }: { group: AdminNavGroup }) {
    const { isCurrentOrParentUrl } = useCurrentUrl();
    const { state } = useSidebar();
    const hasActiveItem = group.items.some((item) =>
        isCurrentOrParentUrl(item.href),
    );
    const [open, setOpen] = useState(hasActiveItem);

    // En modo icono el sidebar es angosto y las etiquetas se ocultan: forzamos
    // el contenido abierto para que todos los iconos sigan accesibles.
    const isIconMode = state === 'collapsed';

    return (
        <Collapsible
            open={isIconMode || open}
            onOpenChange={setOpen}
            className="group/collapsible"
        >
            <SidebarGroup className="py-1">
                <SidebarGroupLabel asChild>
                    <CollapsibleTrigger className="cursor-pointer hover:text-sidebar-foreground">
                        <group.icon />
                        <span className="ml-2">{group.title}</span>
                        <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
                    </CollapsibleTrigger>
                </SidebarGroupLabel>
                <CollapsibleContent>
                    <SidebarGroupContent>
                        <SidebarMenu>
                            {group.items.map((item) => (
                                <SidebarMenuItem key={item.title}>
                                    <SidebarMenuButton
                                        asChild
                                        isActive={isCurrentOrParentUrl(
                                            item.href,
                                        )}
                                        tooltip={item.title}
                                    >
                                        <Link href={item.href} prefetch>
                                            <item.icon />
                                            <span>{item.title}</span>
                                        </Link>
                                    </SidebarMenuButton>
                                </SidebarMenuItem>
                            ))}
                        </SidebarMenu>
                    </SidebarGroupContent>
                </CollapsibleContent>
            </SidebarGroup>
        </Collapsible>
    );
}

function AdminSidebar() {
    const { can } = usePermissions();

    const visibleGroups = navGroups
        .map((group) => ({
            ...group,
            items: group.items.filter(
                (item) => !item.permission || can(item.permission),
            ),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <Sidebar collapsible="icon">
            <SidebarHeader className="gap-2">
                <Link
                    href={dashboard()}
                    className="px-2 text-lg font-semibold group-data-[collapsible=icon]:hidden"
                >
                    Admin
                </Link>
            </SidebarHeader>

            <SidebarContent>
                {visibleGroups.map((group) => (
                    <AdminNavGroup key={group.title} group={group} />
                ))}
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}

export default function AdminLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const { flash } = usePage().props;

    useEffect(() => {
        if (flash.success) {
            toast.success(flash.success);
        }

        if (flash.error) {
            toast.error(flash.error);
        }
    }, [flash.success, flash.error]);

    return (
        <AppShell variant="sidebar">
            <AdminSidebar />

            <SidebarInset className="min-w-0">
                <header className="sticky top-0 z-10 flex h-14 shrink-0 items-center gap-2 border-b border-neutral-200 bg-white px-4 dark:border-neutral-800 dark:bg-neutral-900">
                    <SidebarTrigger className="-ml-1" />
                </header>

                <main className="min-w-0 flex-1 overflow-x-auto p-6">
                    {children}
                </main>
            </SidebarInset>
        </AppShell>
    );
}
