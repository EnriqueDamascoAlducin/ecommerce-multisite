<?php

namespace App\Http\Controllers\Storefront\Auth;

use App\Domain\Store\StoreContext;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordResetLinkController extends Controller
{
    public function __construct(private readonly StoreContext $context) {}

    public function create(): Response
    {
        return Inertia::render('storefront/auth/forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $request->validate(['email' => ['required', 'email']]);

        // Acota al website actual para no filtrar cuentas entre sitios.
        Password::broker('customers')->sendResetLink([
            'website_id' => $this->context->website()->id,
            'email' => $request->string('email')->toString(),
        ]);

        // Respuesta neutra: no revelamos si el email existe.
        return back()->with('success', 'Si el correo existe, te enviamos un enlace para restablecer la contraseña.');
    }
}
