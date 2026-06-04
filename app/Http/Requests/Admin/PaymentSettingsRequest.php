<?php

namespace App\Http\Requests\Admin;

use App\Models\PaymentGatewaySetting;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PaymentSettingsRequest extends FormRequest
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
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'gateway' => ['required', 'string', 'max:50'],
            'is_enabled' => ['boolean'],
            'mode' => ['nullable', Rule::in(PaymentGatewaySetting::MODES)],
            'credentials' => ['array'],
            'credentials.*' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
