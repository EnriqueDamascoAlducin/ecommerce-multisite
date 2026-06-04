<?php

namespace Database\Seeders;

use App\Models\CartPriceRule;
use Illuminate\Database\Seeder;

class PromotionSeeder extends Seeder
{
    /**
     * Reglas de carrito de demostración. Idempotente por nombre/cupón.
     */
    public function run(): void
    {
        CartPriceRule::firstOrCreate(
            ['coupon_code' => 'BIENVENIDA10'],
            [
                'name' => 'Bienvenida 10%',
                'description' => '10% de descuento para nuevos clientes.',
                'action' => CartPriceRule::ACTION_PERCENT,
                'value' => 10,
                'is_active' => true,
                'usage_limit' => 1000,
            ],
        );

        // Regla automática (sin cupón): envío gratis a partir de $1500.
        CartPriceRule::firstOrCreate(
            ['name' => 'Envío gratis +$1500'],
            [
                'coupon_code' => null,
                'description' => 'Envío gratis en compras mayores a $1500.',
                'action' => CartPriceRule::ACTION_FREE_SHIPPING,
                'value' => 0,
                'min_subtotal' => 1500,
                'is_active' => true,
            ],
        );
    }
}
