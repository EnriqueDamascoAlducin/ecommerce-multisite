<?php

namespace App\Domain\Store;

use App\Models\Store;
use App\Models\User;
use App\Models\Website;

/**
 * Gestiona el "scope" que el admin está configurando (global / website / store).
 * Se persiste en sesión y se usa para leer/guardar configuración por sitio.
 */
class AdminScopeManager
{
    private const SESSION_KEY = 'admin.scope';

    public function __construct(private readonly StorePermissionService $permissions) {}

    /**
     * @return array{type: string, id: int}
     */
    public function current(): array
    {
        $scope = session(self::SESSION_KEY);

        if (! is_array($scope) || ! isset($scope['type'], $scope['id'])) {
            return ['type' => ScopedConfigService::SCOPE_GLOBAL, 'id' => ScopedConfigService::GLOBAL_ID];
        }

        return ['type' => $scope['type'], 'id' => (int) $scope['id']];
    }

    public function set(string $type, int $id): void
    {
        session([self::SESSION_KEY => ['type' => $type, 'id' => $id]]);
    }

    public function website(): ?Website
    {
        $current = $this->current();

        return match ($current['type']) {
            ScopedConfigService::SCOPE_WEBSITE => Website::find($current['id']),
            ScopedConfigService::SCOPE_STORE => Store::find($current['id'])?->website,
            default => null,
        };
    }

    public function store(): ?Store
    {
        $current = $this->current();

        return $current['type'] === ScopedConfigService::SCOPE_STORE
            ? Store::find($current['id'])
            : null;
    }

    /**
     * Opciones de scope disponibles para el usuario (global + sus websites + sus stores).
     *
     * @return list<array{type: string, id: int, label: string}>
     */
    public function options(User $user): array
    {
        $options = [[
            'type' => ScopedConfigService::SCOPE_GLOBAL,
            'id' => ScopedConfigService::GLOBAL_ID,
            'label' => 'Global',
        ]];

        $stores = $this->permissions->manageableStores($user);

        foreach ($stores->pluck('website')->filter()->unique('id') as $website) {
            $options[] = [
                'type' => ScopedConfigService::SCOPE_WEBSITE,
                'id' => $website->id,
                'label' => "Website · {$website->name}",
            ];
        }

        foreach ($stores as $store) {
            $options[] = [
                'type' => ScopedConfigService::SCOPE_STORE,
                'id' => $store->id,
                'label' => "Store · {$store->website->name} / {$store->name}",
            ];
        }

        return $options;
    }

    /**
     * Etiqueta legible del scope actual.
     */
    public function currentLabel(User $user): string
    {
        $current = $this->current();

        foreach ($this->options($user) as $option) {
            if ($option['type'] === $current['type'] && $option['id'] === $current['id']) {
                return $option['label'];
            }
        }

        return 'Global';
    }
}
