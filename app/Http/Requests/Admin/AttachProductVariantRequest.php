<?php

namespace App\Http\Requests\Admin;

use App\Domain\Catalog\ConfigurableProductService;
use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class AttachProductVariantRequest extends FormRequest
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
            'product_id' => ['required', 'integer', 'exists:products,id'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $parent = $this->route('product');

            if (! $parent instanceof Product || ! $parent->isConfigurable()) {
                $validator->errors()->add('product_id', 'El producto padre no es configurable.');

                return;
            }

            $isEligible = app(ConfigurableProductService::class)
                ->eligibleVariantCandidates($parent)
                ->contains('id', (int) $this->input('product_id'));

            if (! $isEligible) {
                $validator->errors()->add('product_id', 'Este producto no cumple los requisitos para vincularse como variante.');
            }
        });
    }
}
