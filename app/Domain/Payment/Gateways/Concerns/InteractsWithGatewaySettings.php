<?php

namespace App\Domain\Payment\Gateways\Concerns;

use App\Domain\Payment\PaymentSettings;

/**
 * Da a una pasarela acceso a su configuración resuelta por website
 * (admin) con fallback a `config/payments` (env).
 */
trait InteractsWithGatewaySettings
{
    protected function settings(): PaymentSettings
    {
        return app(PaymentSettings::class);
    }

    protected function config(string $key, mixed $default = null): mixed
    {
        return $this->settings()->value($this->code(), $key, $default);
    }

    protected function isLive(): bool
    {
        return $this->settings()->isLive($this->code());
    }

    protected function enabledInSettings(): bool
    {
        return $this->settings()->isEnabled($this->code());
    }
}
