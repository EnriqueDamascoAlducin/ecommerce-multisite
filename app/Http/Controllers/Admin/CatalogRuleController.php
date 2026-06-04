<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CatalogPriceRuleRequest;
use App\Models\CatalogPriceRule;
use App\Models\Category;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CatalogRuleController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $rules = CatalogPriceRule::query()
            ->with(['website:id,name', 'category:id,name'])
            ->orderBy('priority')
            ->latest()
            ->get()
            ->map(fn (CatalogPriceRule $rule) => [
                'id' => $rule->id,
                'name' => $rule->name,
                'action' => $rule->action,
                'value' => (string) $rule->value,
                'website' => $rule->website?->name,
                'category' => $rule->category?->name,
                'priority' => $rule->priority,
                'is_active' => $rule->is_active,
                'ends_at' => $rule->ends_at?->toDateString(),
            ]);

        return Inertia::render('admin/catalog-rules/index', ['rules' => $rules]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/catalog-rules/create', $this->formData());
    }

    public function store(CatalogPriceRuleRequest $request): RedirectResponse
    {
        $rule = CatalogPriceRule::create($this->normalized($request));

        $this->auditLogger->log('catalog_rule.created', $rule, "Regla de catálogo «{$rule->name}» creada");

        return to_route('admin.catalog-rules.index')->with('success', 'Regla de catálogo creada.');
    }

    public function edit(CatalogPriceRule $catalogRule): Response
    {
        return Inertia::render('admin/catalog-rules/edit', [
            ...$this->formData(),
            'rule' => [
                'id' => $catalogRule->id,
                'name' => $catalogRule->name,
                'description' => $catalogRule->description,
                'website_id' => $catalogRule->website_id,
                'category_id' => $catalogRule->category_id,
                'action' => $catalogRule->action,
                'value' => (string) $catalogRule->value,
                'priority' => $catalogRule->priority,
                'starts_at' => $catalogRule->starts_at?->toDateString(),
                'ends_at' => $catalogRule->ends_at?->toDateString(),
                'is_active' => $catalogRule->is_active,
            ],
        ]);
    }

    public function update(CatalogPriceRuleRequest $request, CatalogPriceRule $catalogRule): RedirectResponse
    {
        $catalogRule->update($this->normalized($request));

        $this->auditLogger->log('catalog_rule.updated', $catalogRule, "Regla de catálogo «{$catalogRule->name}» actualizada");

        return to_route('admin.catalog-rules.index')->with('success', 'Regla de catálogo actualizada.');
    }

    public function destroy(CatalogPriceRule $catalogRule): RedirectResponse
    {
        $name = $catalogRule->name;
        $catalogRule->delete();

        $this->auditLogger->log('catalog_rule.deleted', null, "Regla de catálogo «{$name}» eliminada");

        return to_route('admin.catalog-rules.index')->with('success', 'Regla de catálogo eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(CatalogPriceRuleRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['priority'] = (int) ($data['priority'] ?? 0);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'websites' => Website::orderBy('name')->get(['id', 'name']),
            'categories' => Category::with('website:id,name')->orderBy('website_id')->orderBy('name')->get()
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'label' => "{$category->website->name} / {$category->name}",
                ]),
            'actions' => CatalogPriceRule::ACTIONS,
        ];
    }
}
