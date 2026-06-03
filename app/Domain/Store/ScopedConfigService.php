<?php

namespace App\Domain\Store;

use App\Models\Store;
use App\Models\StoreConfiguration;
use App\Models\Website;

/**
 * Configuración por scope con herencia: store sobreescribe website, y website
 * sobreescribe global. El scope global usa scope_id = 0.
 */
class ScopedConfigService
{
    public const SCOPE_GLOBAL = 'global';

    public const SCOPE_WEBSITE = 'website';

    public const SCOPE_STORE = 'store';

    public const GLOBAL_ID = 0;

    /**
     * Valor efectivo resolviendo store → website → global → default.
     */
    public function get(string $key, mixed $default = null, ?Website $website = null, ?Store $store = null): mixed
    {
        if ($store) {
            $value = $this->read(self::SCOPE_STORE, $store->id, $key);
            $website ??= $store->website;
        }

        if (! isset($value) && $website) {
            $value = $this->read(self::SCOPE_WEBSITE, $website->id, $key);
        }

        $value ??= $this->read(self::SCOPE_GLOBAL, self::GLOBAL_ID, $key);

        return $value ?? $default;
    }

    /**
     * Resuelve usando el sitio de la petición actual.
     */
    public function getForContext(StoreContext $context, string $key, mixed $default = null): mixed
    {
        return $this->get($key, $default, $context->website(), $context->store());
    }

    public function set(string $scope, int $scopeId, string $key, ?string $value): void
    {
        StoreConfiguration::updateOrCreate(
            ['scope' => $scope, 'scope_id' => $scopeId, 'key' => $key],
            ['value' => $value],
        );
    }

    /**
     * Todos los valores definidos directamente en un scope (sin herencia).
     *
     * @return array<string, string|null>
     */
    public function allForScope(string $scope, int $scopeId): array
    {
        return StoreConfiguration::query()
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->pluck('value', 'key')
            ->all();
    }

    private function read(string $scope, int $scopeId, string $key): ?string
    {
        return StoreConfiguration::query()
            ->where('scope', $scope)
            ->where('scope_id', $scopeId)
            ->where('key', $key)
            ->value('value');
    }
}
