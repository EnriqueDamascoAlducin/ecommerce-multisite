import { Search, X } from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type ProductOption = { id: number; label: string };

export default function ProductSelector({
    label,
    options,
    value,
    onChange,
}: {
    label: string;
    options: ProductOption[];
    value: number[];
    onChange: (value: number[]) => void;
}) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);

    const selected = useMemo(
        () => options.filter((opt) => value.includes(opt.id)),
        [options, value],
    );

    const filtered = useMemo(
        () =>
            options.filter(
                (opt) =>
                    opt.label.toLowerCase().includes(search.toLowerCase()) &&
                    !value.includes(opt.id),
            ),
        [options, search, value],
    );

    const toggle = (id: number) => {
        onChange(
            value.includes(id)
                ? value.filter((v) => v !== id)
                : [...value, id],
        );
    };

    const clearAll = () => onChange([]);

    return (
        <div ref={containerRef} className="relative grid gap-1">
            <Label>{label}</Label>

            <div
                role="button"
                tabIndex={0}
                onClick={() => setOpen(!open)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        setOpen(!open);
                    }
                }}
                className="flex min-h-10 cursor-pointer flex-wrap items-center gap-1.5 rounded-md border border-neutral-300 bg-white px-3 py-2 text-sm dark:border-neutral-700 dark:bg-neutral-800"
            >
                {selected.length === 0 ? (
                    <span className="text-neutral-400">
                        Seleccionar productos...
                    </span>
                ) : (
                    selected.map((opt) => (
                        <Badge key={opt.id} variant="secondary">
                            {opt.label}
                            <button
                                type="button"
                                onClick={(e) => {
                                    e.stopPropagation();
                                    toggle(opt.id);
                                }}
                                className="ml-1 hover:text-red-800"
                            >
                                <X className="size-3" />
                            </button>
                        </Badge>
                    ))
                )}
            </div>

            {value.length > 0 && (
                <Button
                    variant="ghost"
                    size="sm"
                    onClick={clearAll}
                    className="h-auto justify-start px-1 text-xs text-neutral-500"
                >
                    Limpiar seleccion
                </Button>
            )}

            {open && (
                <div className="absolute top-full z-50 mt-1 w-full rounded-md border border-neutral-200 bg-white shadow-lg dark:border-neutral-700 dark:bg-neutral-900">
                    <div className="flex items-center gap-2 border-b border-neutral-200 px-3 py-2 dark:border-neutral-700">
                        <Search className="size-4 shrink-0 text-neutral-400" />
                        <Input
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Buscar productos..."
                            className="border-0 p-0 shadow-none focus-visible:ring-0"
                        />
                    </div>
                    <div className="max-h-56 overflow-y-auto">
                        {filtered.length === 0 ? (
                            <p className="px-3 py-4 text-center text-sm text-neutral-400">
                                {search
                                    ? 'Sin resultados'
                                    : 'No hay mas productos'}
                            </p>
                        ) : (
                            filtered.map((opt) => (
                                <label
                                    key={opt.id}
                                    className="flex cursor-pointer items-center gap-2 px-3 py-2 text-sm hover:bg-neutral-50 dark:hover:bg-neutral-800"
                                >
                                    <Checkbox
                                        checked={false}
                                        onCheckedChange={() => toggle(opt.id)}
                                    />
                                    {opt.label}
                                </label>
                            ))
                        )}
                    </div>
                </div>
            )}

            {open && (
                <div
                    className="fixed inset-0 z-40"
                    onClick={() => setOpen(false)}
                />
            )}
        </div>
    );
}
