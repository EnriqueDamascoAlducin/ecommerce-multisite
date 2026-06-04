<?php

namespace Database\Seeders;

use App\Domain\Catalog\ConfigurableProductService;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\InventorySource;
use App\Models\Product;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Productos de demostración: simples y configurables (con variantes generadas).
 * Idempotente — se identifica por SKU, así que `db:seed` se puede repetir.
 * Depende de MultisiteSeeder (tiendas), CatalogSeeder (categorías/atributos) e
 * InventorySeeder (fuente por defecto).
 */
class ProductSeeder extends Seeder
{
    public function __construct(private readonly ConfigurableProductService $configurable) {}

    public function run(): void
    {
        $source = InventorySource::where('code', 'default')->first();
        $interferenciales = Website::where('code', 'interferenciales')->first();

        if (! $source || ! $interferenciales) {
            return;
        }

        $main = Store::where('website_id', $interferenciales->id)->where('code', 'main')->first();
        $sports = Store::where('website_id', $interferenciales->id)->where('code', 'sports')->first();
        $interStores = array_values(array_filter([$main, $sports]));

        // --- Simples (Interferenciales) ---
        $this->simple('IPHONE-15', 'iPhone 15', 19999, $source, 25, $interStores, $this->cats($interferenciales, ['celulares']));
        $sony = $this->simple('SONY-XM5', 'Audífonos Sony WH-1000XM5', 7499, $source, 40, $interStores, $this->cats($interferenciales, ['audio']));
        $balon = $this->simple('BALON-FUT-PRO', 'Balón de Fútbol Pro', 599, $source, 100, $interStores, $this->cats($interferenciales, ['futbol']));

        // --- Configurables (Interferenciales) ---
        // Playera con color + talla (5 × 4 = 20 variantes).
        $this->configurableProduct('PLAYERA-DEP', 'Playera Deportiva', 399, $source, 15, $interStores, ['color', 'talla'], $this->cats($interferenciales, ['running']));
        // Gorra solo por color (5 variantes).
        $this->configurableProduct('GORRA-LOGO', 'Gorra con Logo', 249, $source, 30, $interStores, ['color'], $this->cats($interferenciales, ['running']));

        // --- Bundle (Interferenciales) ---
        // Paquete dinámico: su precio es la suma de los componentes.
        $this->bundle('KIT-DEPORTIVO', 'Kit Deportivo', [[$balon, 2], [$sony, 1]], $interStores, $this->cats($interferenciales, ['futbol']));

        // --- Simples (Veterinaria) ---
        $veterinaria = Website::where('code', 'veterinaria')->first();

        if ($veterinaria) {
            $vet = Store::where('website_id', $veterinaria->id)->where('code', 'main')->first();
            $vetStores = array_values(array_filter([$vet]));

            $this->simple('CROQ-PERRO-15', 'Croquetas Perro Adulto 15kg', 899, $source, 60, $vetStores, $this->cats($veterinaria, ['perros']));
            $this->simple('CROQ-GATO-3', 'Croquetas Gato 3kg', 459, $source, 80, $vetStores, $this->cats($veterinaria, ['gatos']));
        }
    }

    /**
     * Crea un producto simple completamente vendible (precio base, tiendas, stock, categorías).
     *
     * @param  list<Store>  $stores
     * @param  list<int>  $categoryIds
     */
    private function simple(string $sku, string $name, float $price, InventorySource $source, int $stock, array $stores, array $categoryIds): Product
    {
        $product = Product::firstOrCreate(
            ['sku' => $sku],
            [
                'type' => Product::TYPE_SIMPLE,
                'name' => $name,
                'slug' => Str::slug($name),
                'status' => Product::STATUS_ACTIVE,
                'visibility' => 'both',
            ],
        );

        $product->prices()->firstOrCreate(['store_id' => null], ['price' => $price]);

        foreach ($stores as $store) {
            $product->storeLinks()->firstOrCreate(['store_id' => $store->id], ['is_active' => true]);
        }

        $product->inventoryStocks()->firstOrCreate(
            ['inventory_source_id' => $source->id],
            ['physical_qty' => $stock, 'reserved_qty' => 0, 'manage_stock' => true],
        );

        if ($categoryIds !== []) {
            $product->categories()->syncWithoutDetaching($categoryIds);
        }

        return $product;
    }

    /**
     * Crea un producto configurable: precio/tiendas/categorías en el padre, genera
     * las variantes para cada combinación y les asigna stock.
     *
     * @param  list<Store>  $stores
     * @param  list<string>  $attributeCodes
     * @param  list<int>  $categoryIds
     */
    private function configurableProduct(string $sku, string $name, float $price, InventorySource $source, int $stockPerVariant, array $stores, array $attributeCodes, array $categoryIds): void
    {
        $product = Product::firstOrCreate(
            ['sku' => $sku],
            [
                'type' => Product::TYPE_CONFIGURABLE,
                'name' => $name,
                'slug' => Str::slug($name),
                'status' => Product::STATUS_ACTIVE,
                'visibility' => 'both',
            ],
        );

        // El padre lleva el precio base, las tiendas y las categorías; las variantes los heredan.
        $product->prices()->firstOrCreate(['store_id' => null], ['price' => $price]);

        foreach ($stores as $store) {
            $product->storeLinks()->firstOrCreate(['store_id' => $store->id], ['is_active' => true]);
        }

        if ($categoryIds !== []) {
            $product->categories()->syncWithoutDetaching($categoryIds);
        }

        $attributeIds = Attribute::whereIn('code', $attributeCodes)->pluck('id')->all();

        if ($attributeIds === []) {
            return;
        }

        $product->configurableAttributes()->sync($attributeIds);
        $this->configurable->generateVariants($product, $attributeIds);

        // Stock para cada variante (la generación copia precios/tiendas pero no inventario).
        foreach ($product->variants()->get() as $variant) {
            $variant->inventoryStocks()->firstOrCreate(
                ['inventory_source_id' => $source->id],
                ['physical_qty' => $stockPerVariant, 'reserved_qty' => 0, 'manage_stock' => true],
            );
        }
    }

    /**
     * Crea un bundle dinámico (precio = suma de componentes) y lo habilita en las tiendas.
     *
     * @param  list<array{0: Product, 1: int}>  $components
     * @param  list<Store>  $stores
     * @param  list<int>  $categoryIds
     */
    private function bundle(string $sku, string $name, array $components, array $stores, array $categoryIds): Product
    {
        $bundle = Product::firstOrCreate(
            ['sku' => $sku],
            [
                'type' => Product::TYPE_BUNDLE,
                'price_type' => Product::PRICE_TYPE_DYNAMIC,
                'name' => $name,
                'slug' => Str::slug($name),
                'status' => Product::STATUS_ACTIVE,
                'visibility' => 'both',
            ],
        );

        foreach ($stores as $store) {
            $bundle->storeLinks()->firstOrCreate(['store_id' => $store->id], ['is_active' => true]);
        }

        if ($categoryIds !== []) {
            $bundle->categories()->syncWithoutDetaching($categoryIds);
        }

        $sort = 0;
        foreach ($components as [$component, $quantity]) {
            $bundle->bundleItems()->firstOrCreate(
                ['product_id' => $component->id],
                ['quantity' => $quantity, 'sort_order' => $sort++],
            );
        }

        return $bundle;
    }

    /**
     * Resuelve ids de categoría por slug dentro de un website.
     *
     * @param  list<string>  $slugs
     * @return list<int>
     */
    private function cats(Website $website, array $slugs): array
    {
        return Category::where('website_id', $website->id)
            ->whereIn('slug', $slugs)
            ->pluck('id')
            ->all();
    }
}
