<?php

namespace App\Http\Controllers\Storefront\Account;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    public function edit(Request $request): Response
    {
        $customer = $request->user('customer');

        return Inertia::render('storefront/account/profile', [
            'profile' => [
                'name' => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone,
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('customers', 'email')
                    ->where('website_id', $customer->website_id)
                    ->ignore($customer->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        $customer->update($data);

        return back()->with('success', 'Perfil actualizado.');
    }

    public function updatePassword(Request $request): RedirectResponse
    {
        $customer = $request->user('customer');

        $data = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', PasswordRule::defaults()],
        ]);

        if (! Hash::check($data['current_password'], $customer->password)) {
            return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.']);
        }

        $customer->update(['password' => $data['password']]);

        return back()->with('success', 'Contraseña actualizada.');
    }
}
