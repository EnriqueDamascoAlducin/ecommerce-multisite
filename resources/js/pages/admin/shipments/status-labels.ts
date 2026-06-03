export function statusLabel(status: string): string {
    const labels: Record<string, string> = {
        pending: 'Pendiente',
        shipped: 'Enviado',
        delivered: 'Entregado',
        cancelled: 'Cancelado',
    };
    return labels[status] ?? status;
}
