<?php

namespace App\Http\Requests\Admin;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
        $product = $this->route('product');
        $isBundle = $product instanceof Product && $product->isBundle();
        $isDownloadable = $product instanceof Product && $product->isDownloadable();

        return [
            'price_type' => ['nullable', 'in:dynamic,fixed'],
            'sku' => ['required', 'string', 'max:255', Rule::unique('products', 'sku')->ignore($this->route('product'))],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:255'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'status' => ['required', 'in:active,inactive'],
            'visibility' => ['required', 'in:both,catalog,search,hidden'],
            'weight' => ['nullable', 'numeric', 'min:0'],

            // El precio base no aplica a un bundle dinámico (suma de componentes).
            'price' => [$isBundle ? 'nullable' : 'required', 'numeric', 'min:0'],
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

            'labels' => ['array'],
            'labels.*' => ['integer', 'exists:product_labels,id'],

            'upsell_products' => ['array'],
            'upsell_products.*' => ['integer', 'distinct', 'exists:products,id'],
            'cross_sell_products' => ['array'],
            'cross_sell_products.*' => ['integer', 'distinct', 'exists:products,id'],

            'attribute_values' => ['array'],

            'configurable_attributes' => ['nullable', 'array'],
            'configurable_attributes.*' => ['integer', 'exists:attributes,id'],

            // Ediciones inline de las variantes hijas (precio base, SKU, estado, stock, imagen).
            'variants' => ['array'],
            'variants.*.id' => ['required', 'integer', Rule::exists('products', 'id')->where('parent_id', $product instanceof Product ? $product->id : 0)],
            'variants.*.sku' => ['nullable', 'string', 'max:255'],
            'variants.*.price' => ['nullable', 'numeric', 'min:0'],
            'variants.*.status' => ['nullable', 'in:active,inactive'],
            'variants.*.stock_qty' => ['nullable', 'integer', 'min:0'],
            'variants.*.media_id' => ['nullable', 'integer', 'exists:media,id'],

            'bundle_items' => [$isBundle ? 'required' : 'nullable', 'array'],
            'bundle_items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'bundle_items.*.quantity' => ['required', 'integer', 'min:1', 'max:999'],

            'downloadable_links' => [$isDownloadable ? 'required' : 'nullable', 'array'],
            'downloadable_links.*.id' => ['nullable', 'integer'],
            'downloadable_links.*.title' => ['required', 'string', 'max:255'],
            'downloadable_links.*.file_path' => ['required', 'string', 'max:2048'],
            'downloadable_links.*.original_name' => ['nullable', 'string', 'max:255'],
            'downloadable_links.*.max_downloads' => ['nullable', 'integer', 'min:1'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $product = $this->route('product');

                if (! $product instanceof Product) {
                    return;
                }

                foreach (['upsell_products', 'cross_sell_products'] as $field) {
                    $productIds = $this->input($field, []);

                    if (! is_array($productIds)) {
                        continue;
                    }

                    if (in_array($product->id, array_map('intval', $productIds), true)) {
                        $validator->errors()->add($field, 'El producto no puede relacionarse consigo mismo.');
                    }
                }
            },
        ];
    }
}
