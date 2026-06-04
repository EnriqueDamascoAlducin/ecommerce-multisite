<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Http\Request;

abstract class ApiController extends Controller
{
    /**
     * Resuelve la tienda para una petición de API: por `?store=<code>` (tienda
     * activa), o la tienda por defecto del website por defecto.
     */
    protected function resolveStore(Request $request): Store
    {
        $code = $request->string('store')->toString();

        $store = $code !== ''
            ? Store::where('code', $code)->where('is_active', true)->first()
            : null;

        if (! $store) {
            $website = Website::where('is_default', true)->first()
                ?? Website::orderBy('sort_order')->first();

            $store = $website?->defaultStore();
        }

        abort_if($store === null, 404, 'No hay una tienda disponible.');

        return $store;
    }
}
