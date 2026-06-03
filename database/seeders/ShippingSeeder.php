<?php

namespace Database\Seeders;

use App\Models\ShippingMethod;
use App\Models\Store;
use App\Models\StoreShippingMethod;
use Illuminate\Database\Seeder;

class ShippingSeeder extends Seeder
{
    public function run(): void
    {
        $flat = ShippingMethod::firstOrCreate(
            ['code' => 'flat_rate'],
            ['name' => 'Envío estándar', 'type' => ShippingMethod::TYPE_FLAT_RATE, 'sort_order' => 1],
        );

        $free = ShippingMethod::firstOrCreate(
            ['code' => 'free_shipping'],
            ['name' => 'Envío gratis', 'type' => ShippingMethod::TYPE_FREE_SHIPPING, 'sort_order' => 2],
        );

        $pickup = ShippingMethod::firstOrCreate(
            ['code' => 'pickup'],
            ['name' => 'Recoger en tienda', 'type' => ShippingMethod::TYPE_PICKUP, 'sort_order' => 3],
        );

        foreach (Store::all() as $store) {
            // Tarifa fija $99, gratis a partir de $999.
            $this->enable($store, $flat, ['free_over' => 999], rate: 99);

            // Envío gratis solo a partir de $1500 de subtotal.
            $this->enable($store, $free, ['min_subtotal' => 1500], rate: 0);

            // Recoger en tienda, sin costo.
            $this->enable($store, $pickup, [], rate: 0);
        }
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function enable(Store $store, ShippingMethod $method, array $config, float $rate): void
    {
        $ssm = StoreShippingMethod::firstOrCreate(
            ['store_id' => $store->id, 'shipping_method_id' => $method->id],
            array_merge(['is_active' => true], $config),
        );

        if ($ssm->rates()->count() === 0) {
            $ssm->rates()->create(['min_subtotal' => 0, 'max_subtotal' => null, 'amount' => $rate, 'sort_order' => 0]);
        }
    }
}
