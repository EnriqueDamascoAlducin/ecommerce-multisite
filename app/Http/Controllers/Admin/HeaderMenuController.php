<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Store\HeaderMenuService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreHeaderMenuItemRequest;
use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\Product;
use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class HeaderMenuController extends Controller
{
    public function __construct(
        private readonly HeaderMenuService $menuService,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $store = $this->resolveStore($request->integer('store_id'));

        return Inertia::render('admin/header-menu/index', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $store?->id,
            'tree' => $store ? $this->menuService->buildAdminTree($store) : [],
            'categories' => $store ? $this->categoryOptions($store) : [],
            'products' => $store ? $this->productOptions($store) : [],
        ]);
    }

    public function store(StoreHeaderMenuItemRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $item = HeaderMenuItem::create($this->menuItemPayload($data));

        $this->auditLogger->log('header_menu.created', $item, "Ítem de menú {$item->label} creado");

        return to_route('admin.header-menu.index', ['store_id' => $item->store_id])
            ->with('success', 'Ítem de menú creado.');
    }

    public function update(StoreHeaderMenuItemRequest $request, HeaderMenuItem $headerMenuItem): RedirectResponse
    {
        $data = $request->validated();

        $headerMenuItem->update($this->menuItemPayload($data, $headerMenuItem));

        $this->auditLogger->log('header_menu.updated', $headerMenuItem, "Ítem de menú {$headerMenuItem->label} actualizado");

        return to_route('admin.header-menu.index', ['store_id' => $headerMenuItem->store_id])
            ->with('success', 'Ítem de menú actualizado.');
    }

    public function destroy(HeaderMenuItem $headerMenuItem): RedirectResponse
    {
        $storeId = $headerMenuItem->store_id;
        $label = $headerMenuItem->label;
        $headerMenuItem->delete();

        $this->auditLogger->log('header_menu.deleted', null, "Ítem de menú {$label} eliminado");

        return to_route('admin.header-menu.index', ['store_id' => $storeId])
            ->with('success', 'Ítem de menú eliminado.');
    }

    /**
     * Reordena ítems vía drag & drop. Recibe un array de {id: int, sort_order: int, parent_id: int|null}.
     */
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function menuItemPayload(array $data, ?HeaderMenuItem $existing = null): array
    {
        $type = $data['type'];
        $isCategoryMenu = in_array($type, [HeaderMenuItem::TYPE_ALL_CATEGORIES, HeaderMenuItem::TYPE_CATEGORY], true);
        $isUrlMenu = in_array($type, [HeaderMenuItem::TYPE_LINK, HeaderMenuItem::TYPE_CUSTOM], true);

        return [
            'store_id' => $data['store_id'],
            'parent_id' => $data['parent_id'] ?? $existing?->parent_id,
            'type' => $type,
            'label' => $data['label'],
            'url' => $isUrlMenu ? ($data['url'] ?? null) : null,
            'category_id' => $type === HeaderMenuItem::TYPE_CATEGORY ? ($data['category_id'] ?? null) : null,
            'product_id' => $type === HeaderMenuItem::TYPE_PRODUCT ? ($data['product_id'] ?? null) : null,
            'is_active' => $data['is_active'] ?? true,
            'expand_products' => $isCategoryMenu && ($data['expand_products'] ?? false),
            'sort_order' => $data['sort_order'] ?? $existing?->sort_order ?? 0,
        ];
    }

    public function reorder(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'items' => ['required', 'array'],
            'items.*.id' => ['required', 'integer', 'exists:store_header_menu_items,id'],
            'items.*.sort_order' => ['required', 'integer', 'min:0'],
            'items.*.parent_id' => ['nullable', 'integer', 'exists:store_header_menu_items,id'],
        ]);

        foreach ($validated['items'] as $row) {
            HeaderMenuItem::whereKey($row['id'])->update([
                'sort_order' => $row['sort_order'],
                'parent_id' => $row['parent_id'] ?? null,
            ]);
        }

        $storeId = HeaderMenuItem::find($validated['items'][0]['id'])?->store_id;

        $this->auditLogger->log('header_menu.reordered', null, 'Ítems de menú reordenados');

        return to_route('admin.header-menu.index', ['store_id' => $storeId])
            ->with('success', 'Menú reordenado.');
    }

    private function resolveStore(int $storeId): ?Store
    {
        return ($storeId ? Store::find($storeId) : null)
            ?? Store::orderBy('website_id')->orderBy('sort_order')->first();
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function storeOptions()
    {
        return Store::with('website:id,name')->orderBy('website_id')->orderBy('sort_order')->get()
            ->map(fn (Store $store) => ['id' => $store->id, 'label' => "{$store->website->name} / {$store->name}"]);
    }

    /**
     * @return Collection<int, array{id: int, label: string}>
     */
    private function categoryOptions(Store $store): Collection
    {
        return Category::query()
            ->where('website_id', $store->website_id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => ['id' => $category->id, 'label' => $category->name]);
    }

    /**
     * @return Collection<int, array{id: int, label: string, sku: string}>
     */
    private function productOptions(Store $store): Collection
    {
        return Product::query()
            ->active()
            ->whereHas('storeLinks', fn ($query) => $query->where('store_id', $store->id)->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $product->name,
                'sku' => $product->sku,
            ]);
    }
}
