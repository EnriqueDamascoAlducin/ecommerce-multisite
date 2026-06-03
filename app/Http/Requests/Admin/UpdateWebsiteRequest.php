<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateWebsiteRequest extends FormRequest
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
            'code' => ['required', 'string', 'alpha_dash', 'max:255', Rule::unique('websites', 'code')->ignore($this->route('website'))],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
        ];
    }
}
