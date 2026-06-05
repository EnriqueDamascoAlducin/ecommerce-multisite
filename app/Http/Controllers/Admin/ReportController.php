<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Sales\ReportService;
use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports) {}

    public function index(Request $request): Response
    {
        return $this->dashboard($request);
    }

    public function dashboard(Request $request): Response
    {
        $to = $request->date('to') ?? now();
        $from = $request->date('from') ?? now()->subDays(29);

        // El inicio nunca puede ser posterior al fin.
        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        $filters = [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'store_id' => $request->integer('store_id') ?: null,
        ];

        return Inertia::render('admin/dashboard', [
            'filters' => $filters,
            'summary' => $this->reports->summary($filters),
            'revenueByDay' => $this->reports->revenueByDay($filters),
            'ordersByStatus' => $this->reports->ordersByStatus($filters),
            'topProducts' => $this->reports->topProducts($filters),
            'byStore' => $this->reports->byStore($filters),
            'stores' => Store::with('website:id,name')->orderBy('website_id')->get()
                ->map(fn (Store $store) => [
                    'id' => $store->id,
                    'label' => "{$store->website->name} / {$store->name}",
                ]),
            'statuses' => Order::STATUSES,
        ]);
    }
}
