<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Storefront\HtmlSanitizer;
use App\Domain\Storefront\StorefrontPagePresenter;
use App\Domain\Storefront\StorefrontSeoService;
use App\Domain\Storefront\Templates\PageTemplate;
use App\Domain\Storefront\Templates\PageTemplateRegistry;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorefrontPageRequest;
use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\Media;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontPageController extends Controller
{
    public function __construct(
        private readonly StorefrontPagePresenter $presenter,
        private readonly StorefrontSeoService $seo,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $store = $this->resolveStore($request->integer('store_id'));
        $this->homePage($store);

        return Inertia::render('admin/storefront/pages/index', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $store->id,
            'availableTemplates' => PageTemplateRegistry::options(),
            'media' => $this->mediaOptions(),
            'pages' => StorefrontPage::query()
                ->whereHas('stores', fn ($query) => $query->whereKey($store->id))
                ->with('stores.website:id,name')
                ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
                ->orderBy('title')
                ->get()
                ->map(fn (StorefrontPage $page) => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'is_published' => $page->is_published,
                    'updated_at' => $page->updated_at?->toDateString(),
                    'url' => $this->publicUrl($page, $store),
                    'is_home' => $page->slug === StorefrontPage::HOME,
                    'stores' => $page->stores->map(fn (Store $assignedStore) => [
                        'id' => $assignedStore->id,
                        'label' => "{$assignedStore->website->name} / {$assignedStore->name}",
                    ])->values(),
                ]),
        ]);
    }

    public function store(StorefrontPageRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $page = DB::transaction(function () use ($validated): StorefrontPage {
            $page = StorefrontPage::create([
                'store_id' => (int) $validated['store_ids'][0],
                'title' => $validated['title'],
                'slug' => $validated['slug'],
                'template' => $validated['slug'] === StorefrontPage::HOME
                    ? 'home'
                    : ($validated['template'] ?? 'flexible'),
                'is_published' => $validated['is_published'] ?? false,
            ]);

            $seo = $this->seo->normalizePageMeta($validated['seo'] ?? []);
            $assignments = collect($validated['store_ids'])
                ->mapWithKeys(fn ($storeId) => [(int) $storeId => $seo]);
            $page->stores()->sync($assignments);
            $this->seedFromTemplate($page);

            return $page;
        });
        $page->load('stores');
        $page->stores->each(fn (Store $store) => $this->seo->forget($store));

        $this->auditLogger->log('storefront_page.created', $page, "Pagina {$page->title} creada");

        return to_route('admin.storefront.pages.edit', $page)
            ->with('success', 'Pagina creada.');
    }

    public function edit(Request $request, StorefrontPage $page): Response
    {
        $this->ensureFixedSections($page);

        $page->load('store.website', 'stores.website', 'sections');

        $requestedStoreId = $request->integer('store_id');
        $editorStore = $page->stores->firstWhere('id', $requestedStoreId) ?? $page->store;
        $isShared = $page->stores->count() > 1;
        $template = $this->resolveTemplate($page);
        $extraTypes = $template?->extraTypes() ?? [];

        if ($isShared) {
            $extraTypes = array_values(array_filter(
                $extraTypes,
                fn (string $type) => $type !== StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS,
            ));
        }

        return Inertia::render('admin/storefront/pages/edit', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $editorStore->id,
            'page' => [
                ...$this->presenter->present($page, $editorStore),
                'store_id' => $page->store_id,
                'store_ids' => $page->stores->pluck('id')->values(),
                'template' => $page->template,
                'is_published' => $page->is_published,
                'seo' => $this->seo->editablePageMeta($page, $editorStore),
            ],
            'media' => $this->mediaOptions(),
            'products' => $isShared ? [] : $this->productOptions($editorStore),
            'categories' => $this->categoryOptions($editorStore),
            'pages' => $this->pageOptions($editorStore),
            'publicUrl' => $this->publicUrl($page, $editorStore),
            'isHome' => $page->slug === StorefrontPage::HOME,
            'template' => [
                'key' => $template?->key(),
                'label' => $template?->label(),
                'fixedTypes' => $template?->fixedTypes() ?? [],
                'extraTypes' => $extraTypes,
            ],
            'availableTemplates' => PageTemplateRegistry::options(),
        ]);
    }

    public function update(StorefrontPageRequest $request, StorefrontPage $page): RedirectResponse
    {
        $validated = $request->validated();
        $storeIds = array_map('intval', $validated['store_ids']);
        $templateChanged = false;

        DB::transaction(function () use ($request, $page, $validated, $storeIds, &$templateChanged): void {
            $payload = [
                'store_id' => in_array($page->store_id, $storeIds, true) ? $page->store_id : $storeIds[0],
                'title' => $validated['title'],
                'is_published' => $validated['is_published'] ?? false,
            ];

            if ($page->slug !== StorefrontPage::HOME) {
                $payload['slug'] = $validated['slug'];

                if (isset($validated['template']) && $validated['template'] !== $page->template) {
                    $payload['template'] = $validated['template'];
                    $templateChanged = true;
                }
            }

            $page->update($payload);
            $page->stores()->sync($storeIds);

            if ($request->has('seo') && $request->filled('seo_store_id')) {
                $page->stores()->updateExistingPivot(
                    $request->integer('seo_store_id'),
                    $this->seo->normalizePageMeta($validated['seo'] ?? []),
                );
            }

            if ($templateChanged) {
                $page->refresh();
                $this->ensureFixedSections($page);

                return;
            }

            if ($request->has('sections')) {
                $sections = $this->validateSections($request, $page);
                $this->persistSections($page, $this->resolveTemplate($page), $sections);
            }
        });
        $page->load('stores');
        $page->stores->each(fn (Store $store) => $this->seo->forget($store));

        if ($templateChanged) {
            $this->auditLogger->log('storefront_page.updated', $page, "Plantilla de {$page->title} actualizada");

            return back()->with('success', 'Plantilla actualizada.');
        }

        $this->auditLogger->log('storefront_page.updated', $page, "Pagina {$page->title} actualizada");

        return back()->with('success', 'Pagina actualizada.');
    }

    public function destroy(StorefrontPage $page): RedirectResponse
    {
        abort_if($page->slug === StorefrontPage::HOME, 422, 'Home no se puede eliminar.');

        $storeId = $page->store_id;
        $title = $page->title;
        $stores = $page->stores()->get();
        $page->delete();
        $stores->each(fn (Store $store) => $this->seo->forget($store));

        $this->auditLogger->log('storefront_page.deleted', null, "Pagina {$title} eliminada");

        return to_route('admin.storefront.pages.index', ['store_id' => $storeId])
            ->with('success', 'Pagina eliminada de todas las tiendas.');
    }

    public function unlink(StorefrontPage $page, Store $store): RedirectResponse
    {
        abort_if($page->slug === StorefrontPage::HOME, 422, 'Home no se puede desvincular.');
        abort_unless($page->stores()->whereKey($store->id)->exists(), 404);

        if ($page->stores()->count() <= 1) {
            throw ValidationException::withMessages([
                'page' => 'No puedes desvincular la ultima tienda. Elimina la pagina globalmente.',
            ]);
        }

        DB::transaction(function () use ($page, $store): void {
            if ($page->store_id === $store->id) {
                $replacementStoreId = $page->stores()
                    ->whereKeyNot($store->id)
                    ->orderBy('stores.id')
                    ->value('stores.id');

                $page->update(['store_id' => $replacementStoreId]);
            }

            HeaderMenuItem::query()
                ->where('store_id', $store->id)
                ->where('page_id', $page->id)
                ->delete();

            $page->stores()->detach($store->id);
        });
        $this->seo->forget($store);

        $this->auditLogger->log(
            'storefront_page.unlinked',
            $page,
            "Pagina {$page->title} desvinculada de {$store->name}",
        );

        return to_route('admin.storefront.pages.index', ['store_id' => $store->id])
            ->with('success', 'Pagina desvinculada de la tienda.');
    }

    public function home(Request $request): RedirectResponse
    {
        $store = $this->resolveStore($request->integer('store_id'));

        return to_route('admin.storefront.pages.edit', $this->homePage($store));
    }

    public function updateHome(Request $request): RedirectResponse
    {
        $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'title' => ['required', 'string', 'max:255'],
            'is_published' => ['boolean'],
        ]);

        $store = Store::findOrFail($request->integer('store_id'));
        $page = $this->homePage($store);

        $page->update([
            'title' => (string) $request->string('title'),
            'is_published' => $request->boolean('is_published'),
        ]);
        $this->seo->forget($store);

        return back()->with('success', 'Home actualizado.');
    }

    private function resolveStore(int $storeId): Store
    {
        return ($storeId ? Store::find($storeId) : null)
            ?? Store::orderBy('website_id')->orderBy('sort_order')->firstOrFail();
    }

    private function homePage(Store $store): StorefrontPage
    {
        $page = StorefrontPage::firstOrCreate(
            ['store_id' => $store->id, 'slug' => StorefrontPage::HOME],
            ['title' => 'Home', 'template' => 'home', 'is_published' => true],
        );

        $page->stores()->syncWithoutDetaching([$store->id]);
        $this->ensureFixedSections($page);

        return $page->load('sections');
    }

    /**
     * @return list<array{id?: int, type?: string, settings?: array<string, mixed>}>
     */
    private function validateSections(Request $request, StorefrontPage $page): array
    {
        $template = $this->resolveTemplate($page);
        $allowedTypes = $template?->allowedTypes() ?? StorefrontPageSection::TYPES;

        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => [
                'nullable',
                'integer',
                Rule::exists('storefront_page_sections', 'id')
                    ->where('storefront_page_id', $page->id),
            ],
            'sections.*.type' => [
                'required_without:sections.*.id',
                'nullable',
                'string',
                Rule::in($allowedTypes),
            ],
            'sections.*.settings' => ['nullable', 'array'],
            'sections.*.settings.background_color' => ['nullable', 'hex_color'],
            'sections.*.settings.content_width' => ['nullable', Rule::in(['container', 'full'])],
            'sections.*.settings.eyebrow' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.title' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.title_color' => ['nullable', 'hex_color'],
            'sections.*.settings.subtitle' => ['nullable', 'string'],
            'sections.*.settings.text' => ['nullable', 'string'],
            'sections.*.settings.phone' => ['nullable', 'string', 'max:50'],
            'sections.*.settings.email' => ['nullable', 'email', 'max:255'],
            'sections.*.settings.address' => ['nullable', 'string', 'max:500'],
            'sections.*.settings.hours' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.map_url' => ['nullable', 'string', 'max:1000', 'url'],
            'sections.*.settings.html' => ['nullable', 'string'],
            'sections.*.settings.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'sections.*.settings.overlay_enabled' => ['boolean'],
            'sections.*.settings.overlay_color' => ['nullable', 'hex_color'],
            'sections.*.settings.overlay_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sections.*.settings.slides' => ['nullable', 'array', 'max:5'],
            'sections.*.settings.slides.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'sections.*.settings.slides.*.eyebrow' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.slides.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.slides.*.subtitle' => ['nullable', 'string'],
            'sections.*.settings.slides.*.overlay_enabled' => ['boolean'],
            'sections.*.settings.slides.*.overlay_color' => ['nullable', 'hex_color'],
            'sections.*.settings.slides.*.overlay_opacity' => ['nullable', 'integer', 'min:0', 'max:100'],
            'sections.*.settings.slides.*.buttons' => ['nullable', 'array', 'max:2'],
            'sections.*.settings.slides.*.buttons.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.slides.*.buttons.*.url' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.items' => ['nullable', 'array'],
            'sections.*.settings.items.*.title' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.items.*.text' => ['nullable', 'string'],
            'sections.*.settings.items.*.icon' => ['nullable', 'string', 'max:50'],
            'sections.*.settings.items.*.link' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.items.*.highlighted' => ['boolean'],
            'sections.*.settings.items.*.wide' => ['boolean'],
            'sections.*.settings.items.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'sections.*.settings.items.*.cta_label' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.items.*.cta_url' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.buttons' => ['nullable', 'array'],
            'sections.*.settings.buttons.*.label' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.buttons.*.url' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.brands' => ['nullable', 'array'],
            'sections.*.settings.brands.*' => ['nullable'],
            'sections.*.settings.brands.*.name' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.brands.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'sections.*.settings.brands.*.link_type' => ['nullable', Rule::in(['none', 'category', 'product', 'page', 'custom'])],
            'sections.*.settings.brands.*.url' => ['nullable', 'string', 'max:2048'],
            'sections.*.settings.brands.*.category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'sections.*.settings.brands.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'sections.*.settings.brands.*.page_id' => ['nullable', 'integer', 'exists:storefront_pages,id'],
            'sections.*.settings.logo_size' => ['nullable', Rule::in(['small', 'medium', 'large'])],
            'sections.*.settings.logo_radius' => ['nullable', Rule::in(['none', 'medium', 'full'])],
            'sections.*.settings.interest_areas' => ['nullable', 'array'],
            'sections.*.settings.interest_areas.*' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.button_label' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.button_url' => ['nullable', 'string', 'max:255'],
            'sections.*.settings.image_position' => ['nullable', Rule::in(['left', 'right', 'background'])],
            'sections.*.settings.product_ids' => ['nullable', 'array', 'max:12'],
            'sections.*.settings.product_ids.*' => ['integer', 'distinct:strict'],
            'sections.*.settings.display_type' => ['nullable', Rule::in(['grid', 'carousel'])],
            'sections.*.settings.columns' => ['nullable', Rule::in([3, 4, '3', '4'])],
        ]);

        $this->validateTemplateSections($page, $template, $validated['sections']);

        // Laravel's validated() groups id-bearing items before type-only items,
        // but their array keys still match the submitted positions. Sort by key
        // to restore the original order before assigning display_order.
        $ordered = $validated['sections'];
        ksort($ordered);

        return collect($ordered)
            ->values()
            ->map(fn (array $section, int $index) => [
                ...$section,
                'settings' => [
                    ...$this->sanitizeSettings($section['settings'] ?? []),
                    'display_order' => $index,
                ],
            ])
            ->all();
    }

    /**
     * @param  array<string, mixed>  $settings
     * @return array<string, mixed>
     */
    private function sanitizeSettings(array $settings): array
    {
        unset($settings['media']);

        if (array_key_exists('html', $settings)) {
            $settings['html'] = HtmlSanitizer::clean(
                is_string($settings['html']) ? $settings['html'] : null,
            );
        }

        foreach ($settings as $key => $value) {
            if (is_array($value)) {
                $settings[$key] = $this->sanitizeSettings($value);
            }
        }

        return $settings;
    }

    /**
     * @param  list<array{id?: int, type?: string, settings?: array<string, mixed>}>  $sections
     */
    private function validateTemplateSections(StorefrontPage $page, ?PageTemplate $template, array $sections): void
    {
        $fixedTypes = $template?->fixedTypes() ?? [];

        $existingSections = $page->sections()->get()->keyBy('id');
        $submittedExistingIds = collect($sections)
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id) => (int) $id)
            ->values();

        if ($submittedExistingIds->duplicates()->isNotEmpty()) {
            throw ValidationException::withMessages([
                'sections.1.id' => 'La seccion esta duplicada.',
            ]);
        }

        $submittedFixedTypes = collect($sections)
            ->map(function (array $section) use ($existingSections): ?string {
                if (isset($section['id'])) {
                    return $existingSections->get($section['id'])?->type;
                }

                return $section['type'] ?? null;
            })
            ->filter(fn (?string $type) => in_array($type, $fixedTypes, true))
            ->values();

        foreach ($sections as $sectionIndex => $section) {
            if (! isset($section['id']) && in_array($section['type'] ?? null, $fixedTypes, true)) {
                throw ValidationException::withMessages([
                    "sections.{$sectionIndex}.type" => 'Las secciones fijas no se pueden crear manualmente.',
                ]);
            }
        }

        foreach ($fixedTypes as $fixedType) {
            if (! $submittedFixedTypes->contains($fixedType)) {
                throw ValidationException::withMessages([
                    'sections' => "La seccion fija {$fixedType} no se puede eliminar.",
                ]);
            }
        }

        foreach ($sections as $sectionIndex => $section) {
            $type = isset($section['id'])
                ? $existingSections->get($section['id'])?->type
                : ($section['type'] ?? null);

            if ($type === StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS) {
                $this->validateRecommendedProducts($page, $section, $sectionIndex);
            }
        }
    }

    /**
     * @param  array{id?: int, type?: string, settings?: array<string, mixed>}  $section
     */
    private function validateRecommendedProducts(StorefrontPage $page, array $section, int $sectionIndex): void
    {
        $productIds = collect($section['settings']['product_ids'] ?? [])
            ->filter(fn (mixed $id) => is_numeric($id))
            ->map(fn (mixed $id) => (int) $id)
            ->values();

        if ($productIds->isEmpty()) {
            return;
        }

        $validCount = Product::query()
            ->whereIn('id', $productIds)
            ->where('status', Product::STATUS_ACTIVE)
            ->whereIn('visibility', ['both', 'catalog'])
            ->whereHas('storeLinks', fn ($query) => $query
                ->where('store_id', $page->store_id)
                ->where('is_active', true))
            ->count();

        if ($validCount !== $productIds->count()) {
            throw ValidationException::withMessages([
                "sections.{$sectionIndex}.settings.product_ids" => 'Selecciona solo productos activos de esta tienda.',
            ]);
        }
    }

    /**
     * @param  list<array{id?: int, type?: string, settings?: array<string, mixed>}>  $sections
     */
    private function persistSections(StorefrontPage $page, ?PageTemplate $template, array $sections): void
    {
        $extraTypes = $template?->extraTypes() ?? StorefrontPageSection::TYPES;

        $existingSections = $page->sections()->get()->keyBy('id');
        $submittedExistingIds = collect($sections)
            ->pluck('id')
            ->filter()
            ->map(fn (mixed $id) => (int) $id);

        // Only repeatable (extra) sections can be removed; fixed ones persist.
        $page->sections()
            ->whereIn('type', $extraTypes)
            ->whereNotIn('id', $submittedExistingIds)
            ->delete();

        foreach ($sections as $data) {
            if (isset($data['id'])) {
                $section = $existingSections->get($data['id']);

                if (! $section) {
                    continue;
                }

                $section->update(['settings' => $data['settings'] ?? []]);

                continue;
            }

            $type = $data['type'] ?? null;

            if (! in_array($type, $extraTypes, true)) {
                continue;
            }

            $page->sections()->create([
                'type' => $type,
                'settings' => $data['settings'] ?? [],
            ]);
        }
    }

    private function seedFromTemplate(StorefrontPage $page): void
    {
        if ($page->sections()->exists()) {
            return;
        }

        $template = $this->resolveTemplate($page);
        if ($template === null) {
            return;
        }

        foreach ($template->sections() as $section) {
            $page->sections()->create([
                'type' => $section['type'],
                'settings' => $section['settings'] ?? [],
            ]);
        }
    }

    /**
     * Ensure the template's mandatory (fixed) sections exist, without touching
     * repeatable content. Used on edit and after switching template.
     */
    private function ensureFixedSections(StorefrontPage $page): void
    {
        $template = $this->resolveTemplate($page);
        if ($template === null) {
            return;
        }

        $fixedTypes = $template->fixedTypes();
        if ($fixedTypes === []) {
            return;
        }

        $existingTypes = $page->sections()->pluck('type')->all();

        foreach ($template->sections() as $section) {
            if (! in_array($section['type'], $fixedTypes, true)) {
                continue;
            }

            if (in_array($section['type'], $existingTypes, true)) {
                continue;
            }

            $page->sections()->create([
                'type' => $section['type'],
                'settings' => $section['settings'] ?? [],
            ]);

            $existingTypes[] = $section['type'];
        }
    }

    private function resolveTemplate(StorefrontPage $page): ?PageTemplate
    {
        return PageTemplateRegistry::find($page->template);
    }

    private function publicUrl(StorefrontPage $page, Store $store): string
    {
        return $this->seo->pageUrl($page, $store);
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function storeOptions(): array
    {
        return Store::with('website:id,name')
            ->orderBy('website_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'label' => "{$store->website->name} / {$store->name}",
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, url: string}>
     */
    private function mediaOptions(): array
    {
        return Media::query()
            ->where('is_image', true)
            ->where('visibility', Media::VISIBILITY_PUBLIC)
            ->latest()
            ->limit(100)
            ->get()
            ->map(fn (Media $media) => [
                'id' => $media->id,
                'label' => $media->title ?: $media->name,
                'url' => $media->url,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function categoryOptions(Store $store): array
    {
        return Category::query()
            ->where('website_id', $store->website_id)
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Category $category) => [
                'id' => $category->id,
                'label' => $category->name,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, sku: string}>
     */
    private function productOptions(Store $store): array
    {
        return Product::query()
            ->active()
            ->whereIn('visibility', ['both', 'catalog'])
            ->whereHas('storeLinks', fn ($query) => $query
                ->where('store_id', $store->id)
                ->where('is_active', true))
            ->orderBy('name')
            ->get(['id', 'name', 'sku'])
            ->map(fn (Product $product) => [
                'id' => $product->id,
                'label' => $product->name,
                'sku' => $product->sku,
            ])
            ->all();
    }

    /**
     * @return list<array{id: int, label: string, slug: string}>
     */
    private function pageOptions(Store $store): array
    {
        return StorefrontPage::query()
            ->whereHas('stores', fn ($query) => $query->whereKey($store->id))
            ->where('is_published', true)
            ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
            ->orderBy('title')
            ->get(['id', 'title', 'slug'])
            ->map(fn (StorefrontPage $page) => [
                'id' => $page->id,
                'label' => $page->slug === StorefrontPage::HOME ? "{$page->title} (/)" : $page->title,
                'slug' => $page->slug,
            ])
            ->all();
    }
}
