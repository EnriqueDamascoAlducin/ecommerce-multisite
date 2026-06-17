<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CategoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function __construct(
        private readonly CategoryService $categories,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $store = $this->resolveStore($request->integer('store_id'));

        return Inertia::render('admin/categories/index', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $store?->id,
            'tree' => $store ? $this->categories->treeForStore($store->id) : [],
        ]);
    }

    public function create(Request $request): Response
    {
        $store = $this->resolveStore($request->integer('store_id'));

        return Inertia::render('admin/categories/create', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $store?->id,
            'parentOptions' => $store ? $this->categories->flattenedForStore($store->id) : [],
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $store = Store::findOrFail($data['store_id']);

        $category = Category::create([
            ...$this->attributes($data, $store),
            'slug' => $this->uniqueSlug($store->id, $data['slug'] ?? null, $data['name']),
        ]);

        $this->auditLogger->log('category.created', $category, "Categoría {$category->name} creada");

        return to_route('admin.categories.index', ['store_id' => $category->store_id])
            ->with('success', 'Categoría creada.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('admin/categories/edit', [
            'category' => $category->only([
                'id', 'store_id', 'parent_id', 'name', 'slug', 'description',
                'is_active', 'sort_order', 'meta_title', 'meta_description', 'meta_keywords',
            ]),
            'stores' => $this->storeOptions(),
            'parentOptions' => $this->categories->flattenedForStore($category->store_id, $category->id),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();
        $store = Store::findOrFail($data['store_id']);

        $category->update([
            ...$this->attributes($data, $store),
            'slug' => $this->uniqueSlug($store->id, $data['slug'] ?? null, $data['name'], $category->id),
        ]);

        $this->auditLogger->log('category.updated', $category, "Categoría {$category->name} actualizada");

        return to_route('admin.categories.index', ['store_id' => $category->store_id])
            ->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $storeId = $category->store_id;
        $name = $category->name;
        $category->delete();

        $this->auditLogger->log('category.deleted', null, "Categoría {$name} eliminada");

        return to_route('admin.categories.index', ['store_id' => $storeId])
            ->with('success', 'Categoría eliminada.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data, Store $store): array
    {
        return [
            'website_id' => $store->website_id,
            'store_id' => $store->id,
            'parent_id' => $data['parent_id'] ?? null,
            'name' => $data['name'],
            'description' => $data['description'] ?? null,
            'is_active' => $data['is_active'] ?? true,
            'sort_order' => $data['sort_order'] ?? 0,
            'meta_title' => $data['meta_title'] ?? null,
            'meta_description' => $data['meta_description'] ?? null,
            'meta_keywords' => $data['meta_keywords'] ?? null,
        ];
    }

    private function resolveStore(int $storeId): ?Store
    {
        return ($storeId ? Store::find($storeId) : null)
            ?? Store::orderBy('website_id')->orderBy('sort_order')->first();
    }

    private function uniqueSlug(int $storeId, ?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name);
        $candidate = $base;
        $counter = 2;

        while (
            Category::where('store_id', $storeId)
                ->where('slug', $candidate)
                ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $candidate = "{$base}-{$counter}";
            $counter++;
        }

        return $candidate;
    }

    /**
     * Tiendas para el selector, etiquetadas por website.
     *
     * @return Collection<int, array{id: int, name: string}>
     */
    private function storeOptions(): Collection
    {
        return Store::with('website:id,name')
            ->orderBy('website_id')
            ->orderBy('sort_order')
            ->get(['id', 'website_id', 'name'])
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'name' => "{$store->website->name} / {$store->name}",
            ]);
    }
}
