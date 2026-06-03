import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

const links = [
    { href: '/cuenta', label: 'Perfil' },
    { href: '/cuenta/direcciones', label: 'Direcciones' },
];

export function AccountNav() {
    const { url } = usePage();

    return (
        <nav className="mb-6 flex gap-4 border-b border-neutral-200 text-sm dark:border-neutral-800">
            {links.map((link) => (
                <Link
                    key={link.href}
                    href={link.href}
                    className={cn(
                        'border-b-2 border-transparent pb-2 text-neutral-500 hover:text-neutral-900 dark:hover:text-neutral-100',
                        url === link.href && 'border-neutral-900 font-medium text-neutral-900 dark:border-neutral-100 dark:text-neutral-100',
                    )}
                >
                    {link.label}
                </Link>
            ))}
        </nav>
    );
}
