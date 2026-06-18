import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="flex min-h-svh items-center justify-center bg-neutral-100 px-5 py-10 text-neutral-950 dark:bg-neutral-950 dark:text-neutral-50">
            <main className="w-full max-w-md">
                <div className="mb-6 flex justify-center">
                    <Link href={home()} className="flex items-center gap-3">
                        <span className="flex size-10 items-center justify-center rounded-lg bg-neutral-950 text-xs font-bold tracking-wide text-white dark:bg-white dark:text-neutral-950">
                            IA
                        </span>
                        <span className="text-base font-semibold">
                            Interferenciales Admin
                        </span>
                    </Link>
                </div>

                <div className="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900">
                    <div className="mb-7 space-y-2 text-center">
                        <p className="text-xs font-semibold tracking-[0.2em] text-red-700 uppercase dark:text-red-300">
                            Panel administrativo
                        </p>
                        <h1 className="text-2xl font-semibold">{title}</h1>
                        <p className="text-sm leading-6 text-neutral-500 dark:text-neutral-400">
                            {description}
                        </p>
                    </div>
                    {children}
                </div>

                <p className="mt-5 text-center text-xs text-neutral-500 dark:text-neutral-500">
                    Acceso exclusivo para usuarios autorizados.
                </p>
            </main>
        </div>
    );
}
