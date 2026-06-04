<?php

namespace App\Domain\Sales;

use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Agrega métricas de ventas para el panel de reportes. Todas las consultas se
 * acotan por rango de fechas (placed_at) y, opcionalmente, por tienda.
 */
class ReportService
{
    /** Estados que cuentan como ingreso confirmado. */
    public const REVENUE_STATUSES = [
        Order::STATUS_PAID,
        Order::STATUS_INVOICED,
        Order::STATUS_PARTIALLY_SHIPPED,
        Order::STATUS_SHIPPED,
        Order::STATUS_COMPLETE,
    ];

    /**
     * KPIs principales del periodo.
     *
     * @param  array{from: string, to: string, store_id: ?int}  $filters
     * @return array{total_orders: int, paid_orders: int, revenue: string, avg_order_value: string, units_sold: int}
     */
    public function summary(array $filters): array
    {
        $totalOrders = $this->baseQuery($filters)->count();

        $paid = $this->baseQuery($filters)->whereIn('status', self::REVENUE_STATUSES);
        $paidOrders = (clone $paid)->count();
        $revenue = (float) (clone $paid)->sum('total');
        $aov = $paidOrders > 0 ? $revenue / $paidOrders : 0.0;

        $unitsSold = (int) OrderItem::query()
            ->whereHas('order', fn (Builder $q) => $this->applyFilters($q, $filters)->whereIn('status', self::REVENUE_STATUSES))
            ->sum('quantity');

        return [
            'total_orders' => $totalOrders,
            'paid_orders' => $paidOrders,
            'revenue' => $this->money($revenue),
            'avg_order_value' => $this->money($aov),
            'units_sold' => $unitsSold,
        ];
    }

    /**
     * Conteo e importe por estado de orden.
     *
     * @param  array{from: string, to: string, store_id: ?int}  $filters
     * @return list<array{status: string, count: int, total: string}>
     */
    public function ordersByStatus(array $filters): array
    {
        return $this->baseQuery($filters)
            ->selectRaw('status, count(*) as count, coalesce(sum(total), 0) as total')
            ->groupBy('status')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($row) => [
                'status' => $row->status,
                'count' => (int) $row->count,
                'total' => $this->money((float) $row->total),
            ])
            ->all();
    }

    /**
     * Ingresos por día (sólo estados con ingreso confirmado).
     *
     * @param  array{from: string, to: string, store_id: ?int}  $filters
     * @return list<array{day: string, orders: int, revenue: string}>
     */
    public function revenueByDay(array $filters): array
    {
        return $this->baseQuery($filters)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->selectRaw('DATE(placed_at) as day, count(*) as orders, coalesce(sum(total), 0) as revenue')
            ->groupByRaw('DATE(placed_at)')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day' => (string) $row->day,
                'orders' => (int) $row->orders,
                'revenue' => $this->money((float) $row->revenue),
            ])
            ->all();
    }

    /**
     * Productos más vendidos por unidades (estados con ingreso confirmado).
     *
     * @param  array{from: string, to: string, store_id: ?int}  $filters
     * @return list<array{sku: string, name: string, quantity: int, revenue: string}>
     */
    public function topProducts(array $filters, int $limit = 10): array
    {
        return OrderItem::query()
            ->whereHas('order', fn (Builder $q) => $this->applyFilters($q, $filters)->whereIn('status', self::REVENUE_STATUSES))
            ->selectRaw('sku, name, sum(quantity) as quantity, coalesce(sum(line_total), 0) as revenue')
            ->groupBy('sku', 'name')
            ->orderByDesc('quantity')
            ->limit($limit)
            ->get()
            ->map(fn ($row) => [
                'sku' => (string) $row->sku,
                'name' => (string) $row->name,
                'quantity' => (int) $row->quantity,
                'revenue' => $this->money((float) $row->revenue),
            ])
            ->all();
    }

    /**
     * Ingresos por tienda (estados con ingreso confirmado).
     *
     * @param  array{from: string, to: string, store_id: ?int}  $filters
     * @return list<array{store: string, orders: int, revenue: string}>
     */
    public function byStore(array $filters): array
    {
        return $this->baseQuery($filters)
            ->whereIn('status', self::REVENUE_STATUSES)
            ->join('stores', 'stores.id', '=', 'orders.store_id')
            ->selectRaw('stores.name as store, count(*) as orders, coalesce(sum(orders.total), 0) as revenue')
            ->groupBy('stores.id', 'stores.name')
            ->orderByDesc('revenue')
            ->get()
            ->map(fn ($row) => [
                'store' => (string) $row->store,
                'orders' => (int) $row->orders,
                'revenue' => $this->money((float) $row->revenue),
            ])
            ->all();
    }

    /**
     * @param  array{from: string, to: string, store_id: ?int}  $filters
     * @return Builder<Order>
     */
    private function baseQuery(array $filters): Builder
    {
        return $this->applyFilters(Order::query(), $filters);
    }

    /**
     * Aplica los filtros de fecha/tienda sobre una consulta de órdenes.
     *
     * @param  Builder<Order>  $query
     * @param  array{from: string, to: string, store_id: ?int}  $filters
     * @return Builder<Order>
     */
    private function applyFilters(Builder $query, array $filters): Builder
    {
        return $query
            ->when($filters['from'] ?? null, fn (Builder $q, $from) => $q->whereDate('orders.placed_at', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $q, $to) => $q->whereDate('orders.placed_at', '<=', $to))
            ->when($filters['store_id'] ?? null, fn (Builder $q, $storeId) => $q->where('orders.store_id', $storeId));
    }

    private function money(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
