<?php

namespace App\Http\Requests\Admin;

use App\Models\HeaderMenuItem;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHeaderMenuItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $linkTypes = [HeaderMenuItem::TYPE_LINK, HeaderMenuItem::TYPE_CUSTOM];

        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('store_header_menu_items', 'id')->where('store_id', $this->integer('store_id')),
            ],
            'type' => ['required', Rule::in([
                HeaderMenuItem::TYPE_ALL_CATEGORIES,
                HeaderMenuItem::TYPE_LINK,
                HeaderMenuItem::TYPE_CATEGORY,
                HeaderMenuItem::TYPE_PRODUCT,
                HeaderMenuItem::TYPE_PAGE,
                HeaderMenuItem::TYPE_CUSTOM,
            ])],
            'label' => ['required', 'string', 'max:255'],
            'url' => [
                Rule::requiredIf(fn () => in_array($this->input('type'), $linkTypes, true)),
                'nullable', 'string', 'max:2048',
            ],
            'category_id' => [
                Rule::requiredIf($this->input('type') === HeaderMenuItem::TYPE_CATEGORY),
                'nullable', 'integer', 'exists:categories,id',
            ],
            'product_id' => [
                Rule::requiredIf($this->input('type') === HeaderMenuItem::TYPE_PRODUCT),
                'nullable', 'integer', 'exists:products,id',
            ],
            'page_id' => [
                Rule::requiredIf($this->input('type') === HeaderMenuItem::TYPE_PAGE),
                'nullable', 'integer',
                Rule::exists('storefront_page_store', 'storefront_page_id')
                    ->where('store_id', $this->integer('store_id')),
            ],
            'is_active' => ['boolean'],
            'expand_products' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
