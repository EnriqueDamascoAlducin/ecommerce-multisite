<?php

namespace App\Http\Requests\Admin;

use App\Models\StoreDomain;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_array($this->domains)) {
            $this->merge([
                'domains' => array_values(array_filter(
                    array_map(fn ($host) => trim((string) $host), $this->domains),
                    fn (string $host) => $host !== '',
                )),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'code' => ['required', 'string', 'alpha_dash', 'max:255', Rule::unique('stores', 'code')->where('website_id', $this->input('website_id'))->ignore($this->route('store'))],
            'name' => ['required', 'string', 'max:255'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'sort_order' => ['integer', 'min:0'],
            'domains' => ['array', $this->uniqueDomainsRule()],
            'domains.*' => ['string', 'max:255', 'regex:/^[a-z0-9.\-]+$/i', 'distinct'],
            'logo_file' => ['nullable', 'image', 'mimes:png,jpg,jpeg,webp,svg', 'max:2048'],
            'logo_media_id' => ['nullable', 'integer', 'exists:media,id'],
            'remove_logo' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Verifica que ningún host pertenezca ya a otra tienda.
     */
    protected function uniqueDomainsRule(): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail): void {
            $ignoreStoreId = $this->route('store')?->id;

            $conflicts = StoreDomain::whereIn('host', $value ?? [])
                ->when($ignoreStoreId, fn ($query) => $query->where('store_id', '!=', $ignoreStoreId))
                ->pluck('host');

            if ($conflicts->isNotEmpty()) {
                $fail('Estos dominios ya están en uso: '.$conflicts->implode(', '));
            }
        };
    }
}
