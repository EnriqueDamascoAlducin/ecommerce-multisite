<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CartPriceRuleRequest;
use App\Models\CartPriceRule;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PromotionController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $rules = CartPriceRule::query()
            ->with('website:id,name')
            ->latest()
            ->get()
            ->map(fn (CartPriceRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'coupon_code' => $rule->coupon_code,
                'action' => $rule->action,
                'value' => (string) $rule->value,
                'website' => $rule->website?->name,
                'is_active' => $rule->is_active,
                'times_used' => $rule->times_used,
                'usage_limit' => $rule->usage_limit,
                'ends_at' => $rule->ends_at?->toDateString(),
            ]);

        return Inertia::render('admin/promotions/index', ['rules' => $rules]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/promotions/create', $this->formData());
    }

    public function store(CartPriceRuleRequest $request): RedirectResponse
    {
        $rule = CartPriceRule::create($this->normalized($request));

        $this->auditLogger->log('promotion.created', $rule, "Regla de carrito «{$rule->name}» creada");

        return to_route('admin.promotions.index')->with('success', 'Regla creada.');
    }

    public function edit(CartPriceRule $cartPriceRule): Response
    {
        return Inertia::render('admin/promotions/edit', [
            ...$this->formData(),
            'rule' => [
                'id' => $cartPriceRule->id,
                'name' => $cartPriceRule->name,
                'description' => $cartPriceRule->description,
                'website_id' => $cartPriceRule->website_id,
                'coupon_code' => $cartPriceRule->coupon_code,
                'action' => $cartPriceRule->action,
                'value' => (string) $cartPriceRule->value,
                'min_subtotal' => $cartPriceRule->min_subtotal !== null ? (string) $cartPriceRule->min_subtotal : null,
                'starts_at' => $cartPriceRule->starts_at?->toDateString(),
                'ends_at' => $cartPriceRule->ends_at?->toDateString(),
                'is_active' => $cartPriceRule->is_active,
                'usage_limit' => $cartPriceRule->usage_limit,
                'times_used' => $cartPriceRule->times_used,
            ],
        ]);
    }

    public function update(CartPriceRuleRequest $request, CartPriceRule $cartPriceRule): RedirectResponse
    {
        $cartPriceRule->update($this->normalized($request));

        $this->auditLogger->log('promotion.updated', $cartPriceRule, "Regla de carrito «{$cartPriceRule->name}» actualizada");

        return to_route('admin.promotions.index')->with('success', 'Regla actualizada.');
    }

    public function destroy(CartPriceRule $cartPriceRule): RedirectResponse
    {
        $name = $cartPriceRule->name;
        $cartPriceRule->delete();

        $this->auditLogger->log('promotion.deleted', null, "Regla de carrito «{$name}» eliminada");

        return to_route('admin.promotions.index')->with('success', 'Regla eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(CartPriceRuleRequest $request): array
    {
        $data = $request->validated();
        $data['coupon_code'] = ($data['coupon_code'] ?? '') !== '' ? trim($data['coupon_code']) : null;
        $data['is_active'] = (bool) ($data['is_active'] ?? false);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'websites' => Website::orderBy('name')->get(['id', 'name']),
            'actions' => CartPriceRule::ACTIONS,
        ];
    }
}
