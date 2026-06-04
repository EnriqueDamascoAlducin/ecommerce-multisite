<?php

namespace App\Http\Requests\Admin;

use App\Models\CatalogPriceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CatalogPriceRuleRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'action' => ['required', Rule::in(CatalogPriceRule::ACTIONS)],
            'value' => ['required', 'numeric', 'min:0', Rule::when($this->input('action') === CatalogPriceRule::ACTION_PERCENT, ['max:100'])],
            'priority' => ['nullable', 'integer', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
        ];
    }
}
