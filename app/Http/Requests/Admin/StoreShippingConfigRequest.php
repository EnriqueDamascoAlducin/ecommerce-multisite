<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreShippingConfigRequest extends FormRequest
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
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'methods' => ['array'],
            'methods.*.shipping_method_id' => ['required', 'integer', 'exists:shipping_methods,id'],
            'methods.*.enabled' => ['boolean'],
            'methods.*.label' => ['nullable', 'string', 'max:255'],
            'methods.*.amount' => ['nullable', 'numeric', 'min:0'],
            'methods.*.free_over' => ['nullable', 'numeric', 'min:0'],
            'methods.*.min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'methods.*.max_subtotal' => ['nullable', 'numeric', 'min:0'],
            'methods.*.countries' => ['nullable', 'string', 'max:255'],
        ];
    }
}
