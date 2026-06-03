export const INVOICE_STATUS_LABELS: Record<string, string> = {
    pending: 'Pendiente',
    paid: 'Pagada',
    cancelled: 'Cancelada',
    refunded: 'Reembolsada',
};

export function statusLabel(status: string): string {
    return INVOICE_STATUS_LABELS[status] ?? status;
}
