<?php

namespace App\Http\Requests\Admin;

use App\Domain\Storefront\Templates\PageTemplateRegistry;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StorefrontPageRequest extends FormRequest
{
    /** @var list<string> */
    public const RESERVED_SLUGS = [
        'admin', 'cuenta', 'carrito', 'checkout', 'login', 'register', 'logout',
        'forgot-password', 'reset-password', 'dashboard', 'settings', 'storage',
        'media', 'webhooks', 'up', 'user', 'two-factor-challenge', 'email', 'api',
        'sanctum', 'build', 'c', 'p', 'consulta',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('store_ids') && $this->filled('store_id')) {
            $this->merge(['store_ids' => [$this->integer('store_id')]]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $page = $this->route('page');
        $isHome = $page instanceof StorefrontPage && $page->slug === StorefrontPage::HOME;
        $isCreate = ! $page instanceof StorefrontPage;
        // Home is a singleton resolved by slug; it carries no selectable template.
        $templateRequired = $isCreate && $this->input('slug') !== StorefrontPage::HOME;

        return [
            'store_id' => ['required', 'integer', 'exists:stores,id'],
            'store_ids' => ['required', 'array', 'min:1'],
            'store_ids.*' => ['required', 'integer', 'distinct:strict', 'exists:stores,id'],
            'title' => ['required', 'string', 'max:255'],
            'template' => [
                $isHome ? 'prohibited' : ($templateRequired ? 'required' : 'nullable'),
                Rule::in(PageTemplateRegistry::creatableKeys()),
            ],
            'slug' => [
                $isHome ? 'prohibited' : 'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(self::RESERVED_SLUGS),
            ],
            'is_published' => ['boolean'],
        ];
    }

    /** @return list<callable(Validator): void> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $page = $this->route('page');
            $pageId = $page instanceof StorefrontPage ? $page->id : null;
            $storeIds = collect($this->input('store_ids', []))
                ->map(fn ($id) => (int) $id)
                ->all();
            $slug = $page instanceof StorefrontPage && $page->slug === StorefrontPage::HOME
                ? StorefrontPage::HOME
                : (string) $this->input('slug');

            if ($slug === StorefrontPage::HOME
                && (count($storeIds) !== 1 || $storeIds[0] !== $this->integer('store_id'))) {
                $validator->errors()->add('store_ids', 'La pagina Home solo puede pertenecer a una tienda.');
            }

            $slugExists = StorefrontPage::query()
                ->where('slug', $slug)
                ->when($pageId, fn ($query) => $query->whereKeyNot($pageId))
                ->whereHas('stores', fn ($query) => $query->whereIn('stores.id', $storeIds))
                ->exists();

            if ($slugExists) {
                $validator->errors()->add('slug', 'El slug ya esta en uso en una de las tiendas seleccionadas.');
            }

            if (count($storeIds) > 1 && $page instanceof StorefrontPage) {
                $hasSavedRecommendedProducts = $page->sections()
                    ->where('type', StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS)
                    ->exists();
                $hasSubmittedRecommendedProducts = collect($this->input('sections', []))
                    ->contains(fn (array $section) => ($section['type'] ?? null)
                        === StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS);

                if ($hasSavedRecommendedProducts || $hasSubmittedRecommendedProducts) {
                    $validator->errors()->add(
                        'store_ids',
                        'Retira la seccion de productos recomendados antes de compartir la pagina.',
                    );
                }
            }
        }];
    }
}
