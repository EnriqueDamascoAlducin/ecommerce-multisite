<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreAttributeRequest;
use App\Http\Requests\Admin\UpdateAttributeRequest;
use App\Models\Attribute;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AttributeController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $attributes = Attribute::query()
            ->withCount('options')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Attribute $attribute) => [
                'id' => $attribute->id,
                'code' => $attribute->code,
                'name' => $attribute->name,
                'type' => $attribute->type,
                'is_filterable' => $attribute->is_filterable,
                'is_configurable' => $attribute->is_configurable,
                'options_count' => $attribute->options_count,
            ]);

        return Inertia::render('admin/attributes/index', [
            'attributes' => $attributes,
            'types' => Attribute::TYPES,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/attributes/create', [
            'types' => Attribute::TYPES,
        ]);
    }

    public function store(StoreAttributeRequest $request): RedirectResponse
    {
        $attribute = DB::transaction(function () use ($request) {
            $data = $request->validated();
            $attribute = Attribute::create($this->attributes($data));
            $this->syncOptions($attribute, $data['options'] ?? []);

            return $attribute;
        });

        $this->auditLogger->log('attribute.created', $attribute, "Atributo {$attribute->code} creado");

        return to_route('admin.attributes.index')->with('success', 'Atributo creado.');
    }

    public function edit(Attribute $attribute): Response
    {
        $attribute->load('options');

        return Inertia::render('admin/attributes/edit', [
            'attribute' => [
                ...$attribute->only([
                    'id', 'code', 'name', 'type', 'is_required',
                    'is_filterable', 'is_visible', 'is_configurable', 'sort_order',
                ]),
                'options' => $attribute->options->map(fn ($option) => [
                    'label' => $option->label,
                    'value' => $option->value,
                ])->values(),
            ],
            'types' => Attribute::TYPES,
        ]);
    }

    public function update(UpdateAttributeRequest $request, Attribute $attribute): RedirectResponse
    {
        DB::transaction(function () use ($request, $attribute) {
            $data = $request->validated();
            $attribute->update($this->attributes($data));
            $this->syncOptions($attribute, $data['options'] ?? []);
        });

        $this->auditLogger->log('attribute.updated', $attribute, "Atributo {$attribute->code} actualizado");

        return to_route('admin.attributes.index')->with('success', 'Atributo actualizado.');
    }

    public function destroy(Attribute $attribute): RedirectResponse
    {
        $code = $attribute->code;
        $attribute->delete();

        $this->auditLogger->log('attribute.deleted', null, "Atributo {$code} eliminado");

        return to_route('admin.attributes.index')->with('success', 'Atributo eliminado.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'code' => $data['code'],
            'name' => $data['name'],
            'type' => $data['type'],
            'is_required' => $data['is_required'] ?? false,
            'is_filterable' => $data['is_filterable'] ?? false,
            'is_visible' => $data['is_visible'] ?? true,
            'is_configurable' => $data['is_configurable'] ?? false,
            'sort_order' => $data['sort_order'] ?? 0,
        ];
    }

    /**
     * Reemplaza las opciones del atributo (solo aplica a select/multiselect).
     *
     * @param  list<array{label?: string, value?: string}>  $options
     */
    private function syncOptions(Attribute $attribute, array $options): void
    {
        $attribute->options()->delete();

        if (! $attribute->hasOptions()) {
            return;
        }

        foreach (array_values($options) as $index => $option) {
            $label = $option['label'] ?? '';

            if ($label === '') {
                continue;
            }

            $attribute->options()->create([
                'label' => $label,
                'value' => ($option['value'] ?? '') ?: Str::slug($label, '_'),
                'sort_order' => $index,
            ]);
        }
    }
}
