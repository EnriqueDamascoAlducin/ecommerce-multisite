<?php

namespace App\Support\Concerns;

trait ValidatesCustomerAddresses
{
    /**
     * Reglas para el editor de direcciones embebido en el formulario de cliente.
     *
     * @return array<string, mixed>
     */
    protected function customerAddressRules(): array
    {
        return [
            'addresses' => ['array'],
            'addresses.*.label' => ['nullable', 'string', 'max:50'],
            'addresses.*.first_name' => ['required', 'string', 'max:255'],
            'addresses.*.last_name' => ['required', 'string', 'max:255'],
            'addresses.*.company' => ['nullable', 'string', 'max:255'],
            'addresses.*.phone' => ['nullable', 'string', 'max:30'],
            'addresses.*.line1' => ['required', 'string', 'max:255'],
            'addresses.*.line2' => ['nullable', 'string', 'max:255'],
            'addresses.*.neighborhood' => ['nullable', 'string', 'max:255'],
            'addresses.*.city' => ['required', 'string', 'max:255'],
            'addresses.*.state' => ['required', 'string', 'max:255'],
            'addresses.*.postal_code' => ['required', 'string', 'max:20'],
            'addresses.*.country' => ['required', 'string', 'size:2'],
            'addresses.*.is_default_shipping' => ['boolean'],
            'addresses.*.is_default_billing' => ['boolean'],
        ];
    }
}
