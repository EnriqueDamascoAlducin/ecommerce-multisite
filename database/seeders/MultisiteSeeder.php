<?php

namespace Database\Seeders;

use App\Domain\Store\ScopedConfigService;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Database\Seeder;

class MultisiteSeeder extends Seeder
{
    public function __construct(private readonly ScopedConfigService $config) {}

    public function run(): void
    {
        // Configuración global por defecto.
        $this->config->set(ScopedConfigService::SCOPE_GLOBAL, ScopedConfigService::GLOBAL_ID, 'currency', 'MXN');
        $this->config->set(ScopedConfigService::SCOPE_GLOBAL, ScopedConfigService::GLOBAL_ID, 'locale', 'es');
        $this->config->set(ScopedConfigService::SCOPE_GLOBAL, ScopedConfigService::GLOBAL_ID, 'sender_email', 'no-reply@example.com');

        // Website principal: Interferenciales (dominio propio + tienda por prefijo).
        $interferenciales = Website::firstOrCreate(
            ['code' => 'interferenciales'],
            ['name' => 'Interferenciales', 'is_default' => true, 'sort_order' => 1],
        );

        $main = $this->store($interferenciales, 'main', 'Interferenciales', isDefault: true, sortOrder: 1);
        $this->domain($main, 'interferenciales.com.mx', primary: true);
        $this->domain($main, 'www.interferenciales.com.mx');
        $this->domain($main, 'localhost'); // desarrollo local
        $this->domain($main, '127.0.0.1');

        $sports = $this->store($interferenciales, 'sports', 'Interferenciales Sports', sortOrder: 2);
        // /sports resuelve a esta tienda (prefijo de ruta) — no necesita dominio propio.
        $this->config->set(ScopedConfigService::SCOPE_STORE, $sports->id, 'currency', 'MXN');

        // Website secundario: Veterinaria (dominio propio).
        $veterinaria = Website::firstOrCreate(
            ['code' => 'veterinaria'],
            ['name' => 'Veterinaria', 'is_default' => false, 'sort_order' => 2],
        );
        $this->config->set(ScopedConfigService::SCOPE_WEBSITE, $veterinaria->id, 'sender_email', 'contacto@veterinaria.com.mx');

        $vet = $this->store($veterinaria, 'main', 'Veterinaria', isDefault: true, sortOrder: 1);
        $this->domain($vet, 'veterinaria.com.mx', primary: true);
    }

    private function store(Website $website, string $code, string $name, bool $isDefault = false, int $sortOrder = 0): Store
    {
        $store = Store::firstOrCreate(
            ['website_id' => $website->id, 'code' => $code],
            ['name' => $name, 'is_default' => $isDefault, 'is_active' => true, 'sort_order' => $sortOrder],
        );

        $store->views()->firstOrCreate(
            ['code' => 'default'],
            ['name' => 'Predeterminada', 'locale' => 'es', 'is_default' => true, 'is_active' => true],
        );

        return $store;
    }

    private function domain(Store $store, string $host, bool $primary = false): void
    {
        $store->domains()->firstOrCreate(
            ['host' => $host],
            ['is_primary' => $primary],
        );
    }
}
