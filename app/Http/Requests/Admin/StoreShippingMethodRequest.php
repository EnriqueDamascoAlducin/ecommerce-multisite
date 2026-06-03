<?php

namespace App\Http\Requests\Admin;

use App\Models\ShippingMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreShippingMethodRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:shipping_methods,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(ShippingMethod::TYPES)],
            'is_active' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
        ];
    }
}
