<?php

namespace App\Domain\Payment;

use App\Domain\Store\StoreContext;
use App\Models\PaymentGatewaySetting;

/**
 * Fuente de configuración de las pasarelas, resuelta por website.
 *
 * Las pasarelas piden sus credenciales aquí en lugar de leer `config()`
 * directamente: primero busca la configuración del website activo (guardada
 * desde el admin) y, si no existe o le falta una clave, cae a `config/payments`
 * (env) para conservar la compatibilidad previa y los entornos de prueba.
 *
 * El website activo es el resuelto por `StoreContext` (storefront/checkout),
 * salvo que se fije explícitamente con `usingWebsite()` (webhooks).
 */
class PaymentSettings
{
    private ?int $websiteOverride = null;

    /** @var array<string, ?PaymentGatewaySetting> */
    private array $cache = [];

    public function __construct(private readonly StoreContext $context) {}

    /**
     * Fija el website activo (p. ej. en webhooks, donde no hay tienda resuelta).
     */
    public function usingWebsite(?int $websiteId): void
    {
        $this->websiteOverride = $websiteId;
        $this->cache = [];
    }

    /**
     * Valor de una credencial: configuración del website o, si falta, `config()`.
     */
    public function value(string $gateway, string $key, mixed $default = null): mixed
    {
        $setting = $this->setting($gateway);

        if ($setting !== null) {
            $value = $setting->credential($key);

            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return config("payments.{$gateway}.{$key}", $default);
    }

    /**
     * ¿La pasarela está habilitada para el website activo? Sin fila guardada se
     * considera habilitada (modo heredado por env).
     */
    public function isEnabled(string $gateway): bool
    {
        $setting = $this->setting($gateway);

        return $setting !== null ? $setting->is_enabled : true;
    }

    public function mode(string $gateway): string
    {
        return $this->setting($gateway)?->mode ?? PaymentGatewaySetting::MODE_SANDBOX;
    }

    public function isLive(string $gateway): bool
    {
        return $this->mode($gateway) === PaymentGatewaySetting::MODE_LIVE;
    }

    /**
     * Configuración guardada de la pasarela para el website activo (cacheada).
     */
    public function setting(string $gateway): ?PaymentGatewaySetting
    {
        $websiteId = $this->resolveWebsiteId();
        $cacheKey = "{$websiteId}:{$gateway}";

        if (! array_key_exists($cacheKey, $this->cache)) {
            $this->cache[$cacheKey] = $websiteId === null
                ? null
                : PaymentGatewaySetting::query()
                    ->where('website_id', $websiteId)
                    ->where('gateway', $gateway)
                    ->first();
        }

        return $this->cache[$cacheKey];
    }

    /**
     * Limpia la caché tras guardar cambios desde el admin.
     */
    public function flush(): void
    {
        $this->cache = [];
    }

    private function resolveWebsiteId(): ?int
    {
        return $this->websiteOverride ?? $this->context->website()?->id;
    }
}
