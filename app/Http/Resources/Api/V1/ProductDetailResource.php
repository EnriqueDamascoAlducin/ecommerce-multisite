<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Producto en detalle (PDP). Galería, atributos, categorías y opciones
 * configurables los inyecta el controller antes de serializar.
 *
 * @mixin Product
 */
class ProductDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'sku' => $this->sku,
            'name' => $this->name,
            'slug' => $this->slug,
            'short_description' => $this->short_description,
            'description' => $this->description,
            'price' => $this->catalog_price,
            'in_stock' => (bool) $this->catalog_in_stock,
            'gallery' => $this->catalog_gallery,
            'attributes' => $this->catalog_attributes,
            'categories' => $this->catalog_categories,
            'configurable_options' => $this->catalog_configurable_options,
            'variants' => $this->catalog_variants,
        ];
    }
}
