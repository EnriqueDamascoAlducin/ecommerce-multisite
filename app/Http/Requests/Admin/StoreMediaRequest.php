<?php

namespace App\Http\Requests\Admin;

use App\Models\Media;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMediaRequest extends FormRequest
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
            'files' => ['required', 'array'],
            'files.*' => ['file', 'max:10240'], // 10 MB
            'collection' => ['nullable', 'string', 'max:255'],
            'visibility' => ['nullable', Rule::in([Media::VISIBILITY_PUBLIC, Media::VISIBILITY_PRIVATE])],
        ];
    }
}
