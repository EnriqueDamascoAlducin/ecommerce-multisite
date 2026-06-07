<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreWebsiteRequest extends FormRequest
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
            'code' => ['required', 'string', 'alpha_dash', 'max:255', 'unique:websites,code'],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'logo_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'remove_logo' => ['nullable', 'boolean'],
            'favicon_file' => ['nullable', 'file', 'mimes:png,jpg,jpeg,webp,svg,ico', 'max:1024'],
            'favicon_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'remove_favicon' => ['nullable', 'boolean'],
        ];
    }
}
