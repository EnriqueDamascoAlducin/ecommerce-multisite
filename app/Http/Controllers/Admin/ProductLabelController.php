<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProductLabelRequest;
use App\Models\ProductLabel;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductLabelController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $labels = ProductLabel::query()
            ->with('website:id,name')
            ->orderBy('website_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (ProductLabel $label) => $this->present($label));

        return Inertia::render('admin/product-labels/index', ['labels' => $labels]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/product-labels/create', $this->formData());
    }

    public function store(ProductLabelRequest $request): RedirectResponse
    {
        $label = ProductLabel::create($this->normalized($request));

        $this->auditLogger->log('product_label.created', $label, "Etiqueta «{$label->text}» creada");

        return to_route('admin.product-labels.index')->with('success', 'Etiqueta creada.');
    }

    public function edit(ProductLabel $productLabel): Response
    {
        return Inertia::render('admin/product-labels/edit', [
            ...$this->formData(),
            'label' => [
                'id' => $productLabel->id,
                'website_id' => $productLabel->website_id,
                'text' => $productLabel->text,
                'text_color' => $productLabel->text_color,
                'background_color' => $productLabel->background_color,
                'is_active' => $productLabel->is_active,
                'sort_order' => $productLabel->sort_order,
            ],
        ]);
    }

    public function update(ProductLabelRequest $request, ProductLabel $productLabel): RedirectResponse
    {
        $productLabel->update($this->normalized($request));

        $this->auditLogger->log('product_label.updated', $productLabel, "Etiqueta «{$productLabel->text}» actualizada");

        return to_route('admin.product-labels.index')->with('success', 'Etiqueta actualizada.');
    }

    public function destroy(ProductLabel $productLabel): RedirectResponse
    {
        $text = $productLabel->text;
        $productLabel->delete();

        $this->auditLogger->log('product_label.deleted', null, "Etiqueta «{$text}» eliminada");

        return to_route('admin.product-labels.index')->with('success', 'Etiqueta eliminada.');
    }

    /**
     * @return array<string, mixed>
     */
    private function present(ProductLabel $label): array
    {
        return [
            'id' => $label->id,
            'text' => $label->text,
            'text_color' => $label->text_color,
            'background_color' => $label->background_color,
            'website' => $label->website?->name,
            'is_active' => $label->is_active,
            'sort_order' => $label->sort_order,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function normalized(ProductLabelRequest $request): array
    {
        $data = $request->validated();
        $data['is_active'] = (bool) ($data['is_active'] ?? false);
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(): array
    {
        return [
            'websites' => Website::orderBy('name')->get(['id', 'name']),
        ];
    }
}
