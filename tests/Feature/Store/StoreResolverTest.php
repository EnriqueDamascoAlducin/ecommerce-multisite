<?php

use App\Domain\Store\StoreContext;
use App\Domain\Store\StoreResolver;
use App\Models\Store;
use App\Models\StoreDomain;
use App\Models\Website;
use Illuminate\Http\Request;

function buildSites(): array
{
    $interferenciales = Website::factory()->create(['code' => 'interferenciales', 'is_default' => true]);
    $main = Store::factory()->for($interferenciales)->create(['code' => 'main', 'is_default' => true]);
    StoreDomain::factory()->for($main)->create(['host' => 'interferenciales.com.mx']);
    $sports = Store::factory()->for($interferenciales)->create(['code' => 'sports']);

    $veterinaria = Website::factory()->create(['code' => 'veterinaria', 'is_default' => false]);
    $vet = Store::factory()->for($veterinaria)->create(['code' => 'main', 'is_default' => true]);
    StoreDomain::factory()->for($vet)->create(['host' => 'veterinaria.com.mx']);

    return compact('main', 'sports', 'vet');
}

function resolveHost(string $url): ?Store
{
    return app(StoreResolver::class)->resolve(Request::create($url));
}

test('resolves the store by its domain', function () {
    $sites = buildSites();

    expect(resolveHost('http://interferenciales.com.mx/')->id)->toBe($sites['main']->id);
});

test('resolves a store by path prefix within the same website', function () {
    $sites = buildSites();

    expect(resolveHost('http://interferenciales.com.mx/sports')->id)->toBe($sites['sports']->id);
});

test('resolves another website by its own domain', function () {
    $sites = buildSites();

    expect(resolveHost('http://veterinaria.com.mx/')->id)->toBe($sites['vet']->id);
});

test('falls back to the default website store for an unknown host', function () {
    $sites = buildSites();

    expect(resolveHost('http://localhost/')->id)->toBe($sites['main']->id);
});

test('a path prefix does not leak across websites', function () {
    $sites = buildSites();

    // "sports" pertenece a interferenciales; en veterinaria.com.mx se ignora.
    expect(resolveHost('http://veterinaria.com.mx/sports')->id)->toBe($sites['vet']->id);
});

test('an inactive store is not reachable by prefix', function () {
    $sites = buildSites();
    $sites['sports']->update(['is_active' => false]);

    expect(resolveHost('http://interferenciales.com.mx/sports')->id)->toBe($sites['main']->id);
});

test('the resolver populates the store context', function () {
    buildSites();

    resolveHost('http://interferenciales.com.mx/sports');

    expect(app(StoreContext::class)->store()->code)->toBe('sports')
        ->and(app(StoreContext::class)->website()->code)->toBe('interferenciales');
});
