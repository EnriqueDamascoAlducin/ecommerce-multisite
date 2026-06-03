<?php

namespace App\Http\Controllers\Storefront\Account;

use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerAddressRequest;
use App\Models\CustomerAddress;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AddressController extends Controller
{
    public function index(Request $request): Response
    {
        $addresses = $request->user('customer')->addresses()
            ->latest()
            ->get();

        return Inertia::render('storefront/account/addresses', [
            'addresses' => $addresses,
        ]);
    }

    public function store(CustomerAddressRequest $request): RedirectResponse
    {
        $customer = $request->user('customer');

        DB::transaction(function () use ($request, $customer) {
            $address = $customer->addresses()->create($request->validated());
            $this->applyDefaults($address);
        });

        return back()->with('success', 'Dirección agregada.');
    }

    public function update(CustomerAddressRequest $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        DB::transaction(function () use ($request, $address) {
            $address->update($request->validated());
            $this->applyDefaults($address);
        });

        return back()->with('success', 'Dirección actualizada.');
    }

    public function destroy(Request $request, CustomerAddress $address): RedirectResponse
    {
        $this->authorizeAddress($request, $address);

        $address->delete();

        return back()->with('success', 'Dirección eliminada.');
    }

    /**
     * Garantiza una sola dirección por defecto (envío/facturación) por cliente.
     */
    private function applyDefaults(CustomerAddress $address): void
    {
        if ($address->is_default_shipping) {
            CustomerAddress::where('customer_id', $address->customer_id)
                ->whereKeyNot($address->id)
                ->update(['is_default_shipping' => false]);
        }

        if ($address->is_default_billing) {
            CustomerAddress::where('customer_id', $address->customer_id)
                ->whereKeyNot($address->id)
                ->update(['is_default_billing' => false]);
        }
    }

    private function authorizeAddress(Request $request, CustomerAddress $address): void
    {
        abort_unless($address->customer_id === $request->user('customer')->id, 403);
    }
}
