<?php

namespace App\Domain\Catalog;

use App\Domain\Inventory\StockService;
use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConfigurableProductService
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    /**
     * Genera variantes hijas para cada combinación de opciones de los atributos
     * configurables. Omite combinaciones que ya existen (misma firma).
     *
     * @param  Product  $product  Producto padre (type=configurable)
     * @param  list<int>  $attributeIds  Atributos seleccionados como configurables
     * @return int Variantes creadas
     */
    public function generateVariants(Product $product, array $attributeIds): int
    {
        // Las relaciones del padre se copian a cada variante; se cargan una vez
        // para evitar consultas repetidas (N+1) dentro del bucle de combinaciones.
        $product->loadMissing(['storeLinks', 'prices', 'categories', 'media']);

        $attributes = Attribute::whereIn('id', $attributeIds)
            ->with('options')
            ->get();

        $optionMatrix = $attributes->map(fn (Attribute $attr) => $attr->options->values())->values();

        if ($optionMatrix->isEmpty()) {
            return 0;
        }

        $combinations = $this->cartesianProduct($optionMatrix);
        $created = 0;

        foreach ($combinations as $combination) {
            $variantSkus = [];
            $variantLabels = [];
            $codeValueMap = [];

            foreach ($combination as $option) {
                if ($option === null) {
                    continue;
                }
                $variantSkus[] = Str::upper(Str::slug($option->value, '_'));
                $variantLabels[] = $option->label;

                $attr = $attributes->firstWhere('id', $option->attribute_id);
                if ($attr) {
                    $codeValueMap[$attr->code] = $option->value;
                }
            }

            // El nombre acumula todas las etiquetas (p. ej. "Playera Rojo M"),
            // no solo la última opción.
            $variantName = $variantLabels === []
                ? $product->name
                : $product->name.' '.implode(' ', $variantLabels);

            $variantSku = $product->sku.'-'.implode('-', $variantSkus);
            $variantKey = $this->variantKeyFor($codeValueMap);

            // Saltar si ya existe un hijo con el mismo SKU o la misma combinación
            // (esta última puede provenir de un producto simple vinculado a mano).
            if (
                $product->children()->where('sku', $variantSku)->exists()
                || $product->children()->where('attributes->_variant_key', $variantKey)->exists()
            ) {
                continue;
            }

            $child = $product->children()->create([
                'type' => Product::TYPE_SIMPLE,
                'sku' => $variantSku,
                'name' => $variantName,
                'slug' => $product->slug.'-'.implode('-', $variantSkus),
                'status' => Product::STATUS_ACTIVE,
                'visibility' => $product->visibility,
                'weight' => $product->weight,
                'short_description' => $product->short_description,
                'description' => $product->description,
                'attributes' => array_merge($product->attributes ?? [], ['_variant_key' => $variantKey]),
            ]);

            $this->syncVariantRelations($product, $child, $combination, $attributes);

            $created++;
        }

        return $created;
    }

    /**
     * Dado un producto configurable y un map de opciones (attribute_code => option_value),
     * devuelve la variante hija que coincide, o null si no existe.
     *
     * @param  array<string, string>  $selectedOptions  ej. ['color' => 'rojo', 'talla' => 'm']
     */
    public function resolveVariant(Product $product, array $selectedOptions): ?Product
    {
        return $product->children()
            ->where('attributes->_variant_key', $this->variantKeyFor($selectedOptions))
            ->first();
    }

    /**
     * Firma canónica de una variante a partir de un mapa código=>valor.
     * Se ordena por código para que la clave sea estable sin importar el
     * orden de entrada (autogeneración, vinculación o selección en el PDP).
     *
     * @param  array<string, string>  $codeValueMap  ej. ['color' => 'rojo', 'talla' => 'm']
     */
    public function variantKeyFor(array $codeValueMap): string
    {
        ksort($codeValueMap);

        return collect($codeValueMap)
            ->map(fn (string $value, string $code) => "{$code}:{$value}")
            ->implode('|');
    }

    /**
     * Aplica ediciones inline a las variantes hijas (precio base, SKU, estado,
     * imagen y stock). Solo afecta hijos del producto dado.
     *
     * @param  list<array{id?: int|string, sku?: ?string, price?: int|string|null, status?: ?string, stock_qty?: int|string|null, media_id?: int|string|null}>  $edits
     */
    public function applyVariantEdits(Product $product, array $edits, ?User $user = null): void
    {
        foreach ($edits as $edit) {
            $variantId = (int) ($edit['id'] ?? 0);

            if ($variantId === 0) {
                continue;
            }

            $variant = $product->children()->whereKey($variantId)->first();

            if (! $variant) {
                continue;
            }

            $attributes = [];

            if (isset($edit['sku']) && $edit['sku'] !== '') {
                $attributes['sku'] = $edit['sku'];
            }

            if (! empty($edit['status'])) {
                $attributes['status'] = $edit['status'];
            }

            if ($attributes !== []) {
                $variant->update($attributes);
            }

            if (array_key_exists('price', $edit) && $edit['price'] !== null && $edit['price'] !== '') {
                $variant->prices()->updateOrCreate(['store_id' => null], ['price' => $edit['price']]);
            }

            if (array_key_exists('media_id', $edit)) {
                $mediaId = $edit['media_id'];
                $variant->syncMediaCollection(($mediaId === null || $mediaId === '') ? [] : [(int) $mediaId], 'gallery');
            }

            if (array_key_exists('stock_qty', $edit) && $edit['stock_qty'] !== null && $edit['stock_qty'] !== '') {
                $this->stock->setPhysical($variant, (int) $edit['stock_qty'], null, 'Ajuste desde variante', $user);
            }
        }
    }

    /**
     * Productos simple que pueden vincularse como variantes del configurable:
     * simples sin padre, del mismo website, con valor en cada atributo
     * configurable y cuya combinación de opciones aún no exista.
     *
     * @return Collection<int, array{id: int, sku: string, name: string, options: array<string, string>}>
     */
    public function eligibleVariantCandidates(Product $product): Collection
    {
        $product->loadMissing(['configurableAttributes', 'storeLinks.store', 'children']);

        $configAttributes = $product->configurableAttributes;

        if ($configAttributes->isEmpty()) {
            return collect();
        }

        $websiteIds = $product->storeLinks
            ->map(fn ($link) => $link->store?->website_id)
            ->filter()
            ->unique()
            ->values();

        $query = Product::query()
            ->where('type', Product::TYPE_SIMPLE)
            ->whereNull('parent_id')
            ->whereKeyNot($product->id)
            ->with(['attributeValues']);

        foreach ($configAttributes as $attribute) {
            $query->whereHas('attributeValues', fn ($q) => $q->where('attribute_id', $attribute->id));
        }

        if ($websiteIds->isNotEmpty()) {
            $query->whereHas('storeLinks.store', fn ($q) => $q->whereIn('website_id', $websiteIds));
        }

        $existingKeys = $product->children
            ->map(fn (Product $child) => $child->attributes['_variant_key'] ?? null)
            ->filter()
            ->values()
            ->all();

        return $query->orderBy('name')->limit(300)->get()
            ->map(function (Product $candidate) use ($configAttributes) {
                $options = [];

                foreach ($configAttributes as $attribute) {
                    $value = $candidate->attributeValues->firstWhere('attribute_id', $attribute->id)?->value;

                    if ($value === null) {
                        return null;
                    }

                    $options[$attribute->code] = $value;
                }

                return [
                    'product' => $candidate,
                    'key' => $this->variantKeyFor($options),
                    'options' => $options,
                ];
            })
            ->filter()
            ->reject(fn (array $row) => in_array($row['key'], $existingKeys, true))
            ->map(fn (array $row) => [
                'id' => $row['product']->id,
                'sku' => $row['product']->sku,
                'name' => $row['product']->name,
                'options' => $row['options'],
            ])
            ->values();
    }

    /**
     * Vincula un producto simple existente como variante del configurable.
     * Conserva SKU, precio, media y stock propios del hijo.
     */
    public function attachExistingVariant(Product $product, Product $child): void
    {
        $product->loadMissing('configurableAttributes');
        $child->loadMissing('attributeValues');

        $options = [];

        foreach ($product->configurableAttributes as $attribute) {
            $value = $child->attributeValues->firstWhere('attribute_id', $attribute->id)?->value;

            if ($value !== null) {
                $options[$attribute->code] = $value;
            }
        }

        $attributes = $child->attributes ?? [];
        $attributes['_variant_key'] = $this->variantKeyFor($options);

        $child->update([
            'parent_id' => $product->id,
            'attributes' => $attributes,
        ]);
    }

    /**
     * Desvincula una variante: vuelve a ser un producto simple independiente
     * (sin borrar sus datos).
     */
    public function detachVariant(Product $child): void
    {
        $attributes = $child->attributes ?? [];
        unset($attributes['_variant_key']);

        $child->update([
            'parent_id' => null,
            'attributes' => $attributes,
        ]);
    }

    /**
     * Opciones agrupadas por atributo configurable para el PDP.
     *
     * @return list<array{attribute: array{id: int, code: string, name: string}, options: list<array{label: string, value: string}>}>
     */
    public function getConfigurableOptions(Product $product): array
    {
        $attributes = $product->configurableAttributes()->with('options')->get();

        return $attributes->map(fn (Attribute $attr) => [
            'attribute' => [
                'id' => $attr->id,
                'code' => $attr->code,
                'name' => $attr->name,
            ],
            'options' => $attr->options->map(fn (AttributeOption $option) => [
                'label' => $option->label,
                'value' => $option->value,
            ])->values()->toArray(),
        ])->values()->toArray();
    }

    /**
     * Precio efectivo de un configurable (el más barato de sus variantes activas
     * visibles en la tienda actual, o null si ninguna está disponible).
     *
     * @return array{price: float, effective_price: float, special_price: ?float, is_special: bool}|null
     */
    public function priceForConfigurable(Product $product, int $storeId): ?array
    {
        $variants = $product->variants()
            ->where('status', Product::STATUS_ACTIVE)
            ->whereHas('storeLinks', fn ($q) => $q->where('store_id', $storeId)->where('is_active', true))
            ->with(['prices' => fn ($q) => $q->whereIn('store_id', [$storeId, null])])
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $cheapest = $variants->sortBy(function (Product $v) use ($storeId): float {
            $storePrice = $v->prices->firstWhere('store_id', $storeId);
            $priceRow = $storePrice ?? $v->prices->firstWhere('store_id', null);

            return (float) ($priceRow?->effectivePrice() ?? 0);
        })->first();

        $storePrice = $cheapest->prices->firstWhere('store_id', $storeId);
        $priceRow = $storePrice ?? $cheapest->prices->firstWhere('store_id', null);

        if (! $priceRow) {
            return null;
        }

        return [
            'price' => (float) $priceRow->price,
            'effective_price' => (float) $priceRow->effectivePrice(),
            'special_price' => $priceRow->special_price ? (float) $priceRow->special_price : null,
            'is_special' => $priceRow->isSpecialActive(),
        ];
    }

    /**
     * Precio efectivo más bajo entre las variantes activas, sin scope de tienda
     * (para listados administrativos). Cae al precio base del padre si no hay variantes.
     */
    public function lowestVariantBasePrice(Product $product): float|string|null
    {
        $cheapest = $product->variants()
            ->where('status', Product::STATUS_ACTIVE)
            ->with(['prices' => fn ($q) => $q->whereNull('store_id')])
            ->get()
            ->flatMap->prices
            ->sortBy('price')
            ->first();

        return $cheapest?->effectivePrice() ?? $product->basePrice()?->price;
    }

    /**
     * Sincroniza stores, precios, categorías, media e imágenes del padre hacia un hijo.
     */
    private function syncVariantRelations(
        Product $product,
        Product $child,
        Collection $combination,
        Collection $attributes,
    ): void {
        // Copiar storeLinks
        foreach ($product->storeLinks as $link) {
            $child->storeLinks()->create([
                'store_id' => $link->store_id,
                'is_active' => $link->is_active,
            ]);
        }

        // Copiar todos los precios (base + por tienda)
        foreach ($product->prices as $priceRow) {
            $child->prices()->create([
                'store_id' => $priceRow->store_id,
                'price' => $priceRow->price,
                'special_price' => $priceRow->special_price,
                'special_price_from' => $priceRow->special_price_from,
                'special_price_to' => $priceRow->special_price_to,
            ]);
        }

        // Copiar categorías
        $child->categories()->sync($product->categories->pluck('id'));

        // Copiar media
        $mediaIds = $product->mediaInCollection('gallery')->pluck('id');
        $child->syncMediaCollection($mediaIds->toArray(), 'gallery');

        // Guardar valores de atributo en la variante (para que aparezca en PDP como atributos visibles)
        foreach ($combination as $option) {
            if ($option === null) {
                continue;
            }
            $attr = $attributes->firstWhere('id', $option->attribute_id);
            if ($attr) {
                $child->attributeValues()->updateOrCreate(
                    ['attribute_id' => $attr->id],
                    ['value' => $option->value],
                );
            }
        }
    }

    /**
     * @param  Collection<int, Collection<int, AttributeOption>>  $data
     * @return Collection<int, Collection<int, AttributeOption>>
     */
    private function cartesianProduct($data)
    {
        if ($data->isEmpty()) {
            return collect([collect()]);
        }

        /** @var Collection<int, Collection<int, AttributeOption>> $result */
        $result = collect([collect()]);

        foreach ($data as $options) {
            $append = collect();

            foreach ($result as $product) {
                foreach ($options as $option) {
                    $append->push(collect($product->all())->push($option));
                }
            }

            $result = $append;
        }

        return $result;
    }
}
