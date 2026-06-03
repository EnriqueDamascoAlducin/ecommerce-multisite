<?php

namespace App\Domain\Store;

use App\Models\Store;
use App\Models\User;
use App\Models\Website;
use Illuminate\Support\Collection;

/**
 * Resuelve qué tiendas/websites puede gestionar un usuario administrativo.
 * El rol "Super Admin" tiene acceso a todos los sitios.
 */
class StorePermissionService
{
    public function isSuperAdmin(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    public function canManageStore(User $user, Store $store): bool
    {
        return $this->isSuperAdmin($user)
            || $user->stores()->whereKey($store->getKey())->exists();
    }

    public function canManageWebsite(User $user, Website $website): bool
    {
        return $this->isSuperAdmin($user)
            || $user->stores()->where('website_id', $website->getKey())->exists();
    }

    /**
     * Tiendas que el usuario puede gestionar.
     *
     * @return Collection<int, Store>
     */
    public function manageableStores(User $user): Collection
    {
        if ($this->isSuperAdmin($user)) {
            return Store::with('website')->orderBy('website_id')->orderBy('sort_order')->get();
        }

        return $user->stores()->with('website')->get();
    }
}
