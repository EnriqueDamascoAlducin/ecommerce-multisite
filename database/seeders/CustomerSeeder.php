<?php

namespace Database\Seeders;

use App\Models\Customer;
use App\Models\Website;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CustomerSeeder extends Seeder
{
    public function run(): void
    {
        $website = Website::where('is_default', true)->first() ?? Website::orderBy('sort_order')->first();

        if (! $website) {
            return;
        }

        $customer = Customer::firstOrCreate(
            ['website_id' => $website->id, 'email' => 'cliente@example.com'],
            ['name' => 'Cliente Demo', 'password' => Hash::make('password'), 'email_verified_at' => now()],
        );

        $customer->addresses()->firstOrCreate(
            ['label' => 'Casa'],
            [
                'first_name' => 'Cliente',
                'last_name' => 'Demo',
                'line1' => 'Av. Reforma 100',
                'city' => 'CDMX',
                'state' => 'CDMX',
                'postal_code' => '06600',
                'country' => 'MX',
                'is_default_shipping' => true,
                'is_default_billing' => true,
            ],
        );
    }
}
