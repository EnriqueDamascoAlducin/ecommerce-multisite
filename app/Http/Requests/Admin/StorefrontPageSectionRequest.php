<?php

namespace App\Http\Requests\Admin;

use App\Models\StorefrontPageSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorefrontPageSectionRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(StorefrontPageSection::TYPES)],
            'is_active' => ['boolean'],
            'settings' => ['nullable', 'array'],
            'settings.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'settings.items' => ['nullable', 'array'],
            'settings.items.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'settings.buttons' => ['nullable', 'array'],
            'settings.brands' => ['nullable', 'array'],
            'settings.interest_areas' => ['nullable', 'array'],
            'settings.product_ids' => ['nullable', 'array'],
            'settings.product_ids.*' => ['integer', 'exists:products,id'],
            'settings.display_type' => ['nullable', 'string', Rule::in(['grid', 'carrousel'])],
        ];
    }
}
