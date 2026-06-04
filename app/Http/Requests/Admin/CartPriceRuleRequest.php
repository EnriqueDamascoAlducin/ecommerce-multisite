<?php

namespace App\Http\Requests\Admin;

use App\Models\CartPriceRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CartPriceRuleRequest extends FormRequest
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
        $ruleId = $this->route('cartPriceRule')?->id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'website_id' => ['nullable', 'integer', 'exists:websites,id'],
            'coupon_code' => [
                'nullable', 'string', 'max:50',
                Rule::unique('cart_price_rules', 'coupon_code')->ignore($ruleId),
            ],
            'action' => ['required', Rule::in(CartPriceRule::ACTIONS)],
            'value' => ['required', 'numeric', 'min:0', Rule::when($this->input('action') === CartPriceRule::ACTION_PERCENT, ['max:100'])],
            'min_subtotal' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'is_active' => ['boolean'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
