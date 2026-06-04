<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Order
 */
class OrderResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'status' => $this->status,
            'currency' => $this->currency,
            'subtotal' => (string) $this->subtotal,
            'discount' => (string) $this->discount,
            'shipping_amount' => (string) $this->shipping_amount,
            'tax' => (string) $this->tax,
            'total' => (string) $this->total,
            'payment_method' => $this->payment_method,
            'shipping_method_label' => $this->shipping_method_label,
            'placed_at' => $this->placed_at?->toIso8601String(),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
