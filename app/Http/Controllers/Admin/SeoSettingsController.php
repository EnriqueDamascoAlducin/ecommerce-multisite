<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Storefront\StorefrontSeoService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSeoSettingsRequest;
use App\Models\Store;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SeoSettingsController extends Controller
{
    public function __construct(
        private readonly StorefrontSeoService $seo,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function index(Request $request): Response
    {
        $store = $this->resolveStore($request->integer('store_id'));
        $robots = $this->seo->robotsText($store);
        $sitemap = $this->seo->sitemapXml($store);

        return Inertia::render('admin/seo/index', [
            'stores' => $this->storeOptions(),
            'currentStoreId' => $store->id,
            'indexingEnabled' => $this->seo->indexingEnabled($store),
            'additionalRules' => $this->seo->additionalRules($store),
            'sitemapUrl' => $this->seo->sitemapUrl($store),
            'robotsUrl' => $this->seo->robotsUrl($store),
            'counts' => $this->seo->counts($store),
            'sitemapPreview' => Str::limit($sitemap, 16000, "\n..."),
            'robotsPreview' => $robots,
        ]);
    }

    public function update(UpdateSeoSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $store = Store::findOrFail((int) $validated['store_id']);

        $this->seo->saveRobotsSettings(
            $store,
            (bool) $validated['indexing_enabled'],
            $validated['additional_rules'] ?? null,
        );

        $this->auditLogger->log(
            'seo.robots.updated',
            $store,
            "Configuracion SEO actualizada para {$store->name}",
        );

        return back()->with('success', 'Configuracion SEO guardada.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'store_id' => ['required', 'integer', 'exists:stores,id'],
        ]);
        $store = Store::findOrFail((int) $validated['store_id']);

        $this->seo->regenerate($store);

        $this->auditLogger->log(
            'seo.generated',
            $store,
            "Sitemap y robots regenerados para {$store->name}",
        );

        return back()->with('success', 'Sitemap y robots regenerados.');
    }

    private function resolveStore(int $storeId): Store
    {
        return ($storeId ? Store::find($storeId) : null)
            ?? Store::query()->orderBy('website_id')->orderBy('sort_order')->firstOrFail();
    }

    /**
     * @return list<array{id: int, label: string}>
     */
    private function storeOptions(): array
    {
        return Store::query()
            ->with('website:id,name')
            ->orderBy('website_id')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Store $store) => [
                'id' => $store->id,
                'label' => $store->website->name.' / '.$store->name,
            ])
            ->all();
    }
}
