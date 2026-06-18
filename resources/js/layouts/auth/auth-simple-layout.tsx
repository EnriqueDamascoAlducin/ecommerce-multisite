import { Link } from '@inertiajs/react';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="min-h-svh bg-neutral-100 text-neutral-950 dark:bg-neutral-950 dark:text-neutral-50">
            <div className="grid min-h-svh lg:grid-cols-[minmax(0,1fr)_31rem]">
                <section className="hidden bg-neutral-950 text-white lg:flex">
                    <div className="flex w-full flex-col justify-between px-12 py-10">
                        <Link
                            href={home()}
                            className="flex w-fit items-center gap-3"
                        >
                            <span className="flex size-11 items-center justify-center rounded-lg bg-white text-sm font-bold tracking-wide text-neutral-950">
                                IA
                            </span>
                            <span className="text-base font-semibold">
                                Interferenciales Admin
                            </span>
                        </Link>

                        <div className="max-w-xl">
                            <p className="text-sm font-semibold tracking-[0.28em] text-red-300 uppercase">
                                Panel administrativo
                            </p>
                            <h2 className="mt-5 text-4xl leading-tight font-semibold">
                                Control operativo para comercio, catálogo y
                                contenido.
                            </h2>
                            <p className="mt-5 max-w-lg text-base leading-7 text-neutral-300">
                                Gestiona tiendas, productos, órdenes y páginas
                                desde un entorno centralizado y preparado para
                                equipos.
                            </p>
                        </div>

                        <div className="grid max-w-lg grid-cols-3 gap-3 text-sm text-neutral-300">
                            <div className="border-t border-white/15 pt-3">
                                Catálogo
                            </div>
                            <div className="border-t border-white/15 pt-3">
                                Operación
                            </div>
                            <div className="border-t border-white/15 pt-3">
                                Contenido
                            </div>
                        </div>
                    </div>
                </section>

                <main className="flex min-h-svh items-center justify-center px-5 py-10 sm:px-8">
                    <div className="w-full max-w-md">
                        <div className="mb-8 flex items-center gap-3 lg:hidden">
                            <Link
                                href={home()}
                                className="flex size-11 items-center justify-center rounded-lg bg-neutral-950 text-sm font-bold tracking-wide text-white dark:bg-white dark:text-neutral-950"
                            >
                                IA
                            </Link>
                            <div>
                                <p className="text-base font-semibold">
                                    Interferenciales Admin
                                </p>
                                <p className="text-xs text-neutral-500 dark:text-neutral-400">
                                    Panel administrativo
                                </p>
                            </div>
                        </div>

                        <div className="rounded-lg border border-neutral-200 bg-white p-6 shadow-sm sm:p-8 dark:border-neutral-800 dark:bg-neutral-900">
                            <div className="mb-7 space-y-2">
                                <p className="text-xs font-semibold tracking-[0.2em] text-red-700 uppercase dark:text-red-300">
                                    Interferenciales Admin
                                </p>
                                <h1 className="text-2xl font-semibold">
                                    {title}
                                </h1>
                                <p className="text-sm leading-6 text-neutral-500 dark:text-neutral-400">
                                    {description}
                                </p>
                            </div>
                            {children}
                        </div>
                    </div>
                </main>
            </div>
        </div>
    );
}
