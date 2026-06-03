<?php

namespace App\Http\Controllers\Storefront\Auth;

use App\Domain\Cart\CartService;
use App\Domain\Store\StoreContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerRegisterRequest;
use App\Models\Customer;
use App\Notifications\CustomerRegistered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class RegisterController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly CartService $cart,
    ) {}

    public function create(): Response
    {
        return Inertia::render('storefront/auth/register');
    }

    public function store(CustomerRegisterRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $customer = Customer::create([
            'website_id' => $this->context->website()->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
        ]);

        Auth::guard('customer')->login($customer);

        $this->cart->mergeForCustomer($customer);

        $customer->notify(new CustomerRegistered($customer));

        return to_route('customer.account')->with('success', '¡Cuenta creada!');
    }
}
