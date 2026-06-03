<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'visibility' => ['required', 'in:both,catalog,search,hidden'],
            'weight' => ['nullable', 'numeric', 'min:0'],

            'price' => ['required', 'numeric', 'min:0'],
            'special_price' => ['nullable', 'numeric', 'min:0'],
            'special_price_from' => ['nullable', 'date'],
            'special_price_to' => ['nullable', 'date', 'after_or_equal:special_price_from'],

            'stores' => ['array'],
            'stores.*.store_id' => ['required', 'integer', 'exists:stores,id'],
            'stores.*.is_active' => ['boolean'],
            'stores.*.price' => ['nullable', 'numeric', 'min:0'],
            'stores.*.special_price' => ['nullable', 'numeric', 'min:0'],
            'stores.*.special_price_from' => ['nullable', 'date'],
            'stores.*.special_price_to' => ['nullable', 'date', 'after_or_equal:stores.*.special_price_from'],

            'media' => ['array'],
            'media.*' => ['integer', 'exists:media,id'],

            'categories' => ['array'],
            'categories.*' => ['integer', 'exists:categories,id'],

            'attribute_values' => ['array'],
        ];
    }
}
