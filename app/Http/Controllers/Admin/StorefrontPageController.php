<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Storefront\StorefrontPagePresenter;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorefrontPageRequest;
use App\Http\Requests\Admin\StorefrontPageSectionRequest;
use App\Models\Media;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StorefrontPageController extends Controller
{
    public function __construct(
        private readonly StorefrontPagePresenter $presenter,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $store = $this->resolveStore($request->integer('store_id'));
        $this->homePage($store);

        return Inertia::render('admin/storefront/pages/index', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $store->id,
            'pages' => StorefrontPage::query()
                ->where('store_id', $store->id)
                ->orderByRaw("CASE WHEN slug = 'home' THEN 0 ELSE 1 END")
                ->orderBy('title')
                ->get()
                ->map(fn (StorefrontPage $page) => [
                    'id' => $page->id,
                    'title' => $page->title,
                    'slug' => $page->slug,
                    'is_published' => $page->is_published,
                    'updated_at' => $page->updated_at?->toDateString(),
                    'url' => $this->publicUrl($page),
                    'is_home' => $page->slug === StorefrontPage::HOME,
                ]),
        ]);
    }

    public function store(StorefrontPageRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $page = StorefrontPage::create([
            'store_id' => $validated['store_id'],
            'title' => $validated['title'],
            'slug' => $validated['slug'],
            'is_published' => $validated['is_published'] ?? false,
        ]);

        $this->auditLogger->log('storefront_page.created', $page, "Pagina {$page->title} creada");

        return to_route('admin.storefront.pages.edit', $page)
            ->with('success', 'Pagina creada.');
    }

    public function edit(StorefrontPage $page): Response
    {
        $page->load('store.website', 'sections');

        return Inertia::render('admin/storefront/pages/edit', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $page->store_id,
            'page' => [
                ...$this->presenter->present($page, false),
                'store_id' => $page->store_id,
                'is_published' => $page->is_published,
            ],
            'sectionTypes' => StorefrontPageSection::TYPES,
            'media' => $this->mediaOptions(),
            'publicUrl' => $this->publicUrl($page),
            'isHome' => $page->slug === StorefrontPage::HOME,
        ]);
    }

    public function update(StorefrontPageRequest $request, StorefrontPage $page): RedirectResponse
    {
        $validated = $request->validated();

        $payload = [
            'title' => $validated['title'],
            'is_published' => $validated['is_published'] ?? false,
        ];

        if ($page->slug !== StorefrontPage::HOME) {
            $payload['slug'] = $validated['slug'];
        }

        $page->update($payload);

        $this->auditLogger->log('storefront_page.updated', $page, "Pagina {$page->title} actualizada");

        return back()->with('success', 'Pagina actualizada.');
    }

    public function destroy(StorefrontPage $page): RedirectResponse
    {
        abort_if($page->slug === StorefrontPage::HOME, 422, 'Home no se puede eliminar.');

        $storeId = $page->store_id;
        $title = $page->title;
        $page->delete();

        $this->auditLogger->log('storefront_page.deleted', null, "Pagina {$title} eliminada");

        return to_route('admin.storefront.pages.index', ['store_id' => $storeId])
            ->with('success', 'Pagina eliminada.');
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

        return back()->with('success', 'Home actualizado.');
    }

    public function storeSection(StorefrontPageSectionRequest $request, StorefrontPage $page): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless((int) $validated['store_id'] === $page->store_id, 404);

        $section = $page->sections()->create([
            'type' => $validated['type'],
            'is_active' => $validated['is_active'] ?? true,
            'settings' => $validated['settings'] ?? [],
            'sort_order' => (int) ($page->sections()->max('sort_order') ?? -1) + 1,
        ]);

        $this->auditLogger->log('storefront_section.created', $section, "Seccion {$section->type} creada");

        return back()->with('success', 'Seccion creada.');
    }

    public function updateSection(StorefrontPageSectionRequest $request, StorefrontPageSection $section): RedirectResponse
    {
        $validated = $request->validated();
        abort_unless((int) $validated['store_id'] === $section->page->store_id, 404);

        $section->update([
            'type' => $validated['type'],
            'is_active' => $validated['is_active'] ?? false,
            'settings' => $validated['settings'] ?? [],
        ]);

        $this->auditLogger->log('storefront_section.updated', $section, "Seccion {$section->type} actualizada");

        return back()->with('success', 'Seccion actualizada.');
    }

    public function destroySection(StorefrontPageSection $section): RedirectResponse
    {
        $section->delete();

        $this->auditLogger->log('storefront_section.deleted', null, "Seccion {$section->type} eliminada");

        return back()->with('success', 'Seccion eliminada.');
    }

    public function reorderSections(Request $request, StorefrontPage $page): RedirectResponse
    {
        $validated = $request->validate([
            'sections' => ['required', 'array'],
            'sections.*.id' => ['required', 'integer', 'exists:storefront_page_sections,id'],
            'sections.*.sort_order' => ['required', 'integer', 'min:0'],
        ]);

        foreach ($validated['sections'] as $row) {
            StorefrontPageSection::query()
                ->where('storefront_page_id', $page->id)
                ->whereKey($row['id'])
                ->update(['sort_order' => $row['sort_order']]);
        }

        return back()->with('success', 'Secciones reordenadas.');
    }

    private function resolveStore(int $storeId): Store
    {
        return ($storeId ? Store::find($storeId) : null)
            ?? Store::orderBy('website_id')->orderBy('sort_order')->firstOrFail();
    }

    private function homePage(Store $store): StorefrontPage
    {
        return StorefrontPage::firstOrCreate(
            ['store_id' => $store->id, 'slug' => StorefrontPage::HOME],
            ['title' => 'Home', 'is_published' => true],
        )->load('sections');
    }

    private function publicUrl(StorefrontPage $page): string
    {
        return $page->slug === StorefrontPage::HOME ? '/' : "/{$page->slug}";
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
}
