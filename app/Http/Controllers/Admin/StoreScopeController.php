<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Store\AdminScopeManager;
use App\Domain\Store\ScopedConfigService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoreScopeController extends Controller
{
    public function __construct(private readonly AdminScopeManager $scope) {}

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'type' => ['required', 'in:'.implode(',', [
                ScopedConfigService::SCOPE_GLOBAL,
                ScopedConfigService::SCOPE_WEBSITE,
                ScopedConfigService::SCOPE_STORE,
            ])],
            'id' => ['required', 'integer', 'min:0'],
        ]);

        $this->scope->set($validated['type'], (int) $validated['id']);

        return back()->with('success', 'Scope actualizado.');
    }
}
