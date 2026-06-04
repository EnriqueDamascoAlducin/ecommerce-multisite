<?php

namespace Database\Factories;

use App\Models\PaymentGatewaySetting;
use App\Models\Website;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentGatewaySetting>
 */
class PaymentGatewaySettingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'website_id' => Website::factory(),
            'gateway' => 'mercadopago',
            'is_enabled' => true,
            'mode' => PaymentGatewaySetting::MODE_SANDBOX,
            'credentials' => ['access_token' => 'TEST-'.fake()->uuid()],
        ];
    }

    public function live(): static
    {
        return $this->state(fn () => ['mode' => PaymentGatewaySetting::MODE_LIVE]);
    }

    public function disabled(): static
    {
        return $this->state(fn () => ['is_enabled' => false]);
    }
}
