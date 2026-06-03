export const ORDER_STATUS_LABELS: Record<string, string> = {
    pending_payment: 'Pago pendiente',
    payment_review: 'Revisión de pago',
    processing: 'En proceso',
    paid: 'Pagada',
    invoiced: 'Facturada',
    partially_shipped: 'Envío parcial',
    shipped: 'Enviada',
    complete: 'Completada',
    cancelled: 'Cancelada',
    failed: 'Fallida',
    refunded: 'Reembolsada',
};

export function statusLabel(status: string): string {
    return ORDER_STATUS_LABELS[status] ?? status;
}
