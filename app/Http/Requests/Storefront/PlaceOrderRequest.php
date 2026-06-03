<?php

namespace App\Http\Requests\Storefront;

use App\Domain\Payment\PaymentGatewayRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlaceOrderRequest extends FormRequest
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
        $codes = app(PaymentGatewayRegistry::class)->availableCodes();

        return [
            'email' => ['required', 'email', 'max:255'],
            'payment_method' => ['required', Rule::in($codes)],
            'shipping_method_code' => ['nullable', 'string'],

            'shipping' => ['required', 'array'],
            'shipping.first_name' => ['required', 'string', 'max:255'],
            'shipping.last_name' => ['required', 'string', 'max:255'],
            'shipping.company' => ['nullable', 'string', 'max:255'],
            'shipping.phone' => ['nullable', 'string', 'max:30'],
            'shipping.line1' => ['required', 'string', 'max:255'],
            'shipping.line2' => ['nullable', 'string', 'max:255'],
            'shipping.city' => ['required', 'string', 'max:255'],
            'shipping.state' => ['required', 'string', 'max:255'],
            'shipping.postal_code' => ['required', 'string', 'max:20'],
            'shipping.country' => ['required', 'string', 'size:2'],

            'billing_same' => ['boolean'],
            'billing' => ['array'],
            'billing.first_name' => ['required_if:billing_same,0', 'nullable', 'string', 'max:255'],
            'billing.last_name' => ['required_if:billing_same,0', 'nullable', 'string', 'max:255'],
            'billing.company' => ['nullable', 'string', 'max:255'],
            'billing.phone' => ['nullable', 'string', 'max:30'],
            'billing.line1' => ['required_if:billing_same,0', 'nullable', 'string', 'max:255'],
            'billing.line2' => ['nullable', 'string', 'max:255'],
            'billing.city' => ['required_if:billing_same,0', 'nullable', 'string', 'max:255'],
            'billing.state' => ['required_if:billing_same,0', 'nullable', 'string', 'max:255'],
            'billing.postal_code' => ['required_if:billing_same,0', 'nullable', 'string', 'max:20'],
            'billing.country' => ['required_if:billing_same,0', 'nullable', 'string', 'size:2'],
        ];
    }
}
