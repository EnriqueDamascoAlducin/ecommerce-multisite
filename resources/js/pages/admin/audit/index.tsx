import { Form, Head, Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import audit from '@/routes/admin/audit';

type LogRow = {
    id: number;
    action: string;
    description: string | null;
    user: string;
    subject: string | null;
    ip_address: string | null;
    properties: Record<string, unknown> | null;
    created_at: string | null;
};

type Paginated<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    total: number;
};

const fieldClass =
    'rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800';

export default function AuditIndex({
    logs,
    filters,
    actions,
    users,
}: {
    logs: Paginated<LogRow>;
    filters: { action: string; user_id: number | null; search: string; from: string; to: string };
    actions: string[];
    users: { id: number; name: string }[];
}) {
    return (
        <>
            <Head title="Auditoría" />
            <h1 className="mb-6 text-2xl font-semibold">Auditoría</h1>

            <Form {...audit.index.form()} className="mb-4 flex flex-wrap items-end gap-2" options={{ preserveState: true }}>
                <Input name="search" defaultValue={filters.search} placeholder="Buscar en la descripción" className="max-w-xs" />
                <select name="action" defaultValue={filters.action} className={fieldClass}>
                    <option value="">Todas las acciones</option>
                    {actions.map((action) => (
                        <option key={action} value={action}>{action}</option>
                    ))}
                </select>
                <select name="user_id" defaultValue={filters.user_id ?? ''} className={fieldClass}>
                    <option value="">Todos los usuarios</option>
                    {users.map((user) => (
                        <option key={user.id} value={user.id}>{user.name}</option>
                    ))}
                </select>
                <Input type="date" name="from" defaultValue={filters.from} className={fieldClass} />
                <Input type="date" name="to" defaultValue={filters.to} className={fieldClass} />
                <Button variant="outline">Filtrar</Button>
            </Form>

            <div className="overflow-hidden rounded-lg border border-neutral-200 bg-white dark:border-neutral-800 dark:bg-neutral-900">
                <table className="w-full text-left text-sm">
                    <thead className="border-b border-neutral-200 text-neutral-500 dark:border-neutral-800">
                        <tr>
                            <th className="px-4 py-3 font-medium">Fecha</th>
                            <th className="px-4 py-3 font-medium">Usuario</th>
                            <th className="px-4 py-3 font-medium">Acción</th>
                            <th className="px-4 py-3 font-medium">Descripción</th>
                            <th className="px-4 py-3 font-medium">Objeto</th>
                            <th className="px-4 py-3 font-medium">IP</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-neutral-100 dark:divide-neutral-800">
                        {logs.data.map((log) => (
                            <tr key={log.id} className="hover:bg-neutral-50 dark:hover:bg-neutral-800/50">
                                <td className="px-4 py-3 text-xs whitespace-nowrap text-neutral-500">{log.created_at}</td>
                                <td className="px-4 py-3 whitespace-nowrap">{log.user}</td>
                                <td className="px-4 py-3"><Badge variant="outline" className="font-mono text-xs">{log.action}</Badge></td>
                                <td className="px-4 py-3 text-neutral-600 dark:text-neutral-300">{log.description ?? '—'}</td>
                                <td className="px-4 py-3 text-xs text-neutral-400">{log.subject ?? '—'}</td>
                                <td className="px-4 py-3 font-mono text-xs text-neutral-400">{log.ip_address ?? '—'}</td>
                            </tr>
                        ))}
                        {logs.data.length === 0 && (
                            <tr>
                                <td colSpan={6} className="px-4 py-8 text-center text-neutral-500">No hay registros.</td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            <div className="mt-4 flex items-center justify-between text-sm text-neutral-500">
                <span>{logs.total} registros</span>
                <div className="flex gap-2">
                    {logs.prev_page_url ? (
                        <Button variant="outline" size="sm" asChild><Link href={logs.prev_page_url} preserveScroll>Anterior</Link></Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Anterior</Button>
                    )}
                    {logs.next_page_url ? (
                        <Button variant="outline" size="sm" asChild><Link href={logs.next_page_url} preserveScroll>Siguiente</Link></Button>
                    ) : (
                        <Button variant="outline" size="sm" disabled>Siguiente</Button>
                    )}
                </div>
            </div>
        </>
    );
}
