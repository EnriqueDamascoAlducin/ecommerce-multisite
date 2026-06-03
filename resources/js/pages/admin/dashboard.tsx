import { Head } from '@inertiajs/react';

export default function AdminDashboard() {
    return (
        <>
            <Head title="Panel admin" />
            <h1 className="text-2xl font-semibold">Panel de administración</h1>
            <p className="mt-2 text-neutral-500 dark:text-neutral-400">
                Base del admin. Usuarios, roles y permisos llegan en la Fase 2.
            </p>
        </>
    );
}
