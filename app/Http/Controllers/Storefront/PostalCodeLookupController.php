<?php

namespace App\Http\Controllers\Storefront;

use App\Http\Controllers\Controller;
use App\Models\PostalCodeSettlement;
use Illuminate\Http\JsonResponse;

class PostalCodeLookupController extends Controller
{
    public function show(string $postalCode): JsonResponse
    {
        $settlements = PostalCodeSettlement::query()
            ->where('postal_code', $postalCode)
            ->orderBy('settlement')
            ->get(['postal_code', 'settlement', 'settlement_type', 'municipality', 'state', 'city', 'zone']);

        if ($settlements->isEmpty()) {
            return response()->json(['message' => 'Código postal no encontrado.'], 404);
        }

        $first = $settlements->first();

        return response()->json([
            'postal_code' => $postalCode,
            'state' => $first->state,
            'city' => $first->municipality,
            'settlements' => $settlements->map(fn (PostalCodeSettlement $settlement) => [
                'name' => $settlement->settlement,
                'type' => $settlement->settlement_type,
                'city' => $settlement->municipality,
                'state' => $settlement->state,
                'zone' => $settlement->zone,
            ])->values(),
        ]);
    }
}
