<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreWebsiteRequest;
use App\Http\Requests\Admin\UpdateWebsiteRequest;
use App\Models\Website;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class WebsiteController extends Controller
{
    public function __construct(private readonly AuditLogger $auditLogger) {}

    public function index(): Response
    {
        $websites = Website::query()
            ->withCount('stores')
            ->orderBy('sort_order')
            ->get()
            ->map(fn (Website $website) => [
                'id' => $website->id,
                'code' => $website->code,
                'name' => $website->name,
                'is_default' => $website->is_default,
                'stores_count' => $website->stores_count,
            ]);

        return Inertia::render('admin/websites/index', [
            'websites' => $websites,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/websites/create');
    }

    public function store(StoreWebsiteRequest $request): RedirectResponse
    {
        $website = Website::create($request->validated());

        $this->auditLogger->log('website.created', $website, "Website {$website->code} creado");

        return to_route('admin.websites.index')->with('success', 'Website creado.');
    }

    public function edit(Website $website): Response
    {
        return Inertia::render('admin/websites/edit', [
            'website' => $website->only(['id', 'code', 'name', 'is_default', 'sort_order']),
        ]);
    }

    public function update(UpdateWebsiteRequest $request, Website $website): RedirectResponse
    {
        $website->update($request->validated());

        $this->auditLogger->log('website.updated', $website, "Website {$website->code} actualizado");

        return to_route('admin.websites.index')->with('success', 'Website actualizado.');
    }

    public function destroy(Website $website): RedirectResponse
    {
        if ($website->is_default) {
            return back()->with('error', 'No puedes eliminar el website por defecto.');
        }

        $code = $website->code;
        $website->delete();

        $this->auditLogger->log('website.deleted', null, "Website {$code} eliminado");

        return to_route('admin.websites.index')->with('success', 'Website eliminado.');
    }
}
