import { Head } from '@inertiajs/react';
import { Download } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { AccountNav } from './account-nav';

type DownloadGrant = {
    id: number;
    title: string;
    order_number: string | null;
    downloads_used: number;
    max_downloads: number | null;
    remaining: number | null;
    available: boolean;
    granted_at: string | null;
};

export default function CustomerDownloads({ downloads }: { downloads: DownloadGrant[] }) {
    return (
        <div className="mx-auto max-w-2xl">
            <Head title="Mis descargas" />
            <h1 className="mb-6 text-2xl font-semibold">Mi cuenta</h1>
            <AccountNav />

            {downloads.length === 0 ? (
                <p className="text-sm text-neutral-500">Todavía no tienes productos descargables.</p>
            ) : (
                <ul className="divide-y divide-neutral-100 rounded-lg border border-neutral-100 dark:divide-neutral-800 dark:border-neutral-800">
                    {downloads.map((grant) => (
                        <li key={grant.id} className="flex items-center justify-between gap-4 px-4 py-3">
                            <div>
                                <p className="font-medium">{grant.title}</p>
                                <p className="text-xs text-neutral-500">
                                    {grant.order_number && <>Orden {grant.order_number} · </>}
                                    {grant.max_downloads === null
                                        ? 'Descargas ilimitadas'
                                        : `${grant.remaining} de ${grant.max_downloads} descargas restantes`}
                                </p>
                            </div>
                            {grant.available ? (
                                <a
                                    href={`/cuenta/descargas/${grant.id}/archivo`}
                                    className="inline-flex items-center gap-2 rounded-md bg-neutral-900 px-3 py-1.5 text-sm text-white hover:bg-neutral-700 dark:bg-neutral-100 dark:text-neutral-900"
                                >
                                    <Download className="size-4" />
                                    Descargar
                                </a>
                            ) : (
                                <Badge variant="outline">Límite alcanzado</Badge>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
