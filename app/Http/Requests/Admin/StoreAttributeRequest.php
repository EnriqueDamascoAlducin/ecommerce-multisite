<?php

namespace App\Http\Requests\Admin;

use App\Models\Attribute;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAttributeRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:255', 'regex:/^[a-z][a-z0-9_]*$/', 'unique:attributes,code'],
            'name' => ['required', 'string', 'max:255'],
            'type' => ['required', Rule::in(Attribute::TYPES)],
            'is_required' => ['boolean'],
            'is_filterable' => ['boolean'],
            'is_visible' => ['boolean'],
            'is_configurable' => ['boolean'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'options' => ['array'],
            'options.*.label' => ['nullable', 'string', 'max:255'],
            'options.*.value' => ['nullable', 'string', 'max:255'],
        ];
    }
}
