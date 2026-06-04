<?php

namespace App\Domain\Catalog;

use App\Models\Attribute;
use App\Models\AttributeOption;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ConfigurableProductService
{
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
            $variantKeyParts = [];

            foreach ($combination as $option) {
                if ($option === null) {
                    continue;
                }
                $variantSkus[] = Str::upper(Str::slug($option->value, '_'));
                $variantLabels[] = $option->label;

                $attr = $attributes->firstWhere('id', $option->attribute_id);
                if ($attr) {
                    $variantKeyParts[] = "{$attr->code}:{$option->value}";
                }
            }

            // El nombre acumula todas las etiquetas (p. ej. "Playera Rojo M"),
            // no solo la última opción.
            $variantName = $variantLabels === []
                ? $product->name
                : $product->name.' '.implode(' ', $variantLabels);

            $variantSku = $product->sku.'-'.implode('-', $variantSkus);
            $variantKey = implode('|', $variantKeyParts);

            if ($product->children()->where('sku', $variantSku)->exists()) {
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
        $keyParts = [];
        foreach ($selectedOptions as $code => $value) {
            $keyParts[] = "{$code}:{$value}";
        }
        $variantKey = implode('|', $keyParts);

        return $product->children()
            ->where('attributes->_variant_key', $variantKey)
            ->first();
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
            ->with(['prices' => fn ($q) => $q->where('store_id', $storeId)])
            ->get();

        if ($variants->isEmpty()) {
            return null;
        }

        $cheapest = $variants->sortBy(fn (Product $v) => (float) ($v->prices->first()?->price ?? 0))->first();
        $priceRow = $cheapest?->prices->first();

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
