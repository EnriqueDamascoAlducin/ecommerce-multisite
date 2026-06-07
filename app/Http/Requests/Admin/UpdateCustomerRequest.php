<?php

namespace App\Http\Requests\Admin;

use App\Models\Customer;
use App\Support\Concerns\ValidatesCustomerAddresses;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateCustomerRequest extends FormRequest
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
        /** @var Customer $customer */
        $customer = $this->route('customer');
        $websiteId = $customer->website_id;

        return [
            'group_id' => ['nullable', 'integer', Rule::exists('customer_groups', 'id')->where('website_id', $websiteId)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('customers', 'email')->where('website_id', $websiteId)->ignore($customer->id)],
            'phone' => ['nullable', 'string', 'max:30'],
            'password' => ['nullable', Password::defaults()],
            'addresses.*.id' => ['nullable', 'integer', Rule::exists('customer_addresses', 'id')->where('customer_id', $customer->id)],
            ...$this->customerAddressRules(),
        ];
    }
}
