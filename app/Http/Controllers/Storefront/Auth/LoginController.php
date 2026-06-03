<?php

namespace App\Http\Controllers\Storefront\Auth;

use App\Domain\Cart\CartService;
use App\Domain\Store\StoreContext;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\CustomerLoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class LoginController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly CartService $cart,
    ) {}

    public function create(): Response
    {
        return Inertia::render('storefront/auth/login');
    }

    public function store(CustomerLoginRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // El login se acota al website actual (el email es único por website).
        $credentials = [
            'website_id' => $this->context->website()->id,
            'email' => $data['email'],
            'password' => $data['password'],
        ];

        if (! Auth::guard('customer')->attempt($credentials, (bool) ($data['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con nuestros registros.',
            ]);
        }

        $request->session()->regenerate();

        $this->cart->mergeForCustomer(Auth::guard('customer')->user());

        return to_route('customer.account');
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('customer')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('home');
    }
}
