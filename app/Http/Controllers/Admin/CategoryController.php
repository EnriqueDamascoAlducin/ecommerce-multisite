<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\CategoryService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Website;
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
        $website = $this->resolveWebsite($request->integer('website_id'));

        return Inertia::render('admin/categories/index', [
            'websites' => $this->websiteOptions(),
            'currentWebsiteId' => $website?->id,
            'tree' => $website ? $this->categories->treeForWebsite($website->id) : [],
        ]);
    }

    public function create(Request $request): Response
    {
        $website = $this->resolveWebsite($request->integer('website_id'));

        return Inertia::render('admin/categories/create', [
            'websites' => $this->websiteOptions(),
            'currentWebsiteId' => $website?->id,
            'parentOptions' => $website ? $this->categories->flattenedForWebsite($website->id) : [],
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $category = Category::create([
            ...$this->attributes($data),
            'slug' => $this->uniqueSlug($data['website_id'], $data['slug'] ?? null, $data['name']),
        ]);

        $this->auditLogger->log('category.created', $category, "Categoría {$category->name} creada");

        return to_route('admin.categories.index', ['website_id' => $category->website_id])
            ->with('success', 'Categoría creada.');
    }

    public function edit(Category $category): Response
    {
        return Inertia::render('admin/categories/edit', [
            'category' => $category->only([
                'id', 'website_id', 'parent_id', 'name', 'slug', 'description',
                'is_active', 'sort_order', 'meta_title', 'meta_description', 'meta_keywords',
            ]),
            'websites' => $this->websiteOptions(),
            'parentOptions' => $this->categories->flattenedForWebsite($category->website_id, $category->id),
        ]);
    }

    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $data = $request->validated();

        $category->update([
            ...$this->attributes($data),
            'slug' => $this->uniqueSlug($data['website_id'], $data['slug'] ?? null, $data['name'], $category->id),
        ]);

        $this->auditLogger->log('category.updated', $category, "Categoría {$category->name} actualizada");

        return to_route('admin.categories.index', ['website_id' => $category->website_id])
            ->with('success', 'Categoría actualizada.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        $websiteId = $category->website_id;
        $name = $category->name;
        $category->delete();

        $this->auditLogger->log('category.deleted', null, "Categoría {$name} eliminada");

        return to_route('admin.categories.index', ['website_id' => $websiteId])
            ->with('success', 'Categoría eliminada.');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function attributes(array $data): array
    {
        return [
            'website_id' => $data['website_id'],
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

    private function resolveWebsite(int $websiteId): ?Website
    {
        return ($websiteId ? Website::find($websiteId) : null)
            ?? Website::orderBy('sort_order')->first();
    }

    private function uniqueSlug(int $websiteId, ?string $slug, string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($slug ?: $name);
        $candidate = $base;
        $counter = 2;

        while (
            Category::where('website_id', $websiteId)
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
     * @return Collection<int, array{id: int, name: string}>
     */
    private function websiteOptions()
    {
        return Website::orderBy('sort_order')->get(['id', 'name'])
            ->map(fn (Website $website) => ['id' => $website->id, 'name' => $website->name]);
    }
}
