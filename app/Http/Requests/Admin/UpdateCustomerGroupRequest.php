<?php

namespace App\Http\Requests\Admin;

use App\Models\CustomerGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCustomerGroupRequest extends FormRequest
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
        /** @var CustomerGroup $group */
        $group = $this->route('customerGroup');

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:50', 'regex:/^[a-z0-9-]+$/', Rule::unique('customer_groups', 'code')->where('website_id', $group->website_id)->ignore($group->id)],
            'description' => ['nullable', 'string', 'max:255'],
            'color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'is_default' => ['boolean'],
        ];
    }
}
