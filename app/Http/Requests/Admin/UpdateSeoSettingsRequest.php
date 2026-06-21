<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class UpdateSeoSettingsRequest extends FormRequest
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
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'indexing_enabled' => ['required', 'boolean'],
            'additional_rules' => ['nullable', 'string', 'max:10000'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('additional_rules')) {
                return;
            }

            foreach (preg_split('/\\R/', (string) $this->input('additional_rules')) ?: [] as $index => $line) {
                $line = trim($line);

                if ($line !== '' && ! preg_match('/^(Allow|Disallow):\\s*\\/.*$/i', $line)) {
                    $validator->errors()->add(
                        'additional_rules',
                        'La linea '.($index + 1).' debe iniciar con Allow: / o Disallow: /.',
                    );
                }
            }
        }];
    }
}
