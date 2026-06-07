<?php

namespace App\Http\Requests\Admin;

use App\Support\Concerns\ValidatesCustomerAddresses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreCustomerRequest extends FormRequest
{
    use ValidatesCustomerAddresses;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $websiteId = $this->integer('website_id');

        return [
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('website_id', $websiteId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('customers', 'email')->where('website_id', $websiteId)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['required', Password::defaults()],
            ...$this->customerAddressRules(),
        ];
    }
}
