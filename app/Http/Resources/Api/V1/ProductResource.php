<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Producto en listados. Los campos calculados por tienda (precio, stock,
 * miniatura) los inyecta el controller antes de serializar.
 *
 * @mixin Product
 */
class ProductResource extends JsonResource
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
            'price' => $this->catalog_price,
            'in_stock' => (bool) $this->catalog_in_stock,
            'thumbnail' => $this->catalog_thumbnail,
        ];
    }
}
