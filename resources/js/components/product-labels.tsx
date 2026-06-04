import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

export type ProductLabelData = {
    text: string;
    text_color: string;
    background_color: string;
};

/**
 * Fila de etiquetas (badges) de un producto con colores personalizados.
 * Reutilizado en tarjetas de catálogo, ficha de producto y listado admin.
 */
export function ProductLabels({ labels, className }: { labels?: ProductLabelData[]; className?: string }) {
    if (!labels || labels.length === 0) {
        return null;
    }

    return (
        <div className={cn('flex flex-wrap gap-1', className)}>
            {labels.map((label, index) => (
                <Badge
                    key={index}
                    className="border-transparent"
                    style={{ color: label.text_color, backgroundColor: label.background_color }}
                >
                    {label.text}
                </Badge>
            ))}
        </div>
    );
}
