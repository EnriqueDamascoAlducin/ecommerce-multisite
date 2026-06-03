<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
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
            'inventory_source_id' => ['required', 'integer', 'exists:inventory_sources,id'],
            'physical_qty' => ['required', 'integer', 'min:0'],
            'manage_stock' => ['boolean'],
            'allow_backorders' => ['boolean'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}
