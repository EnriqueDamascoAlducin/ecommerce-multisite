<?php

namespace App\Http\Requests\Admin;

use App\Domain\Storefront\Templates\PageTemplateRegistry;
use App\Models\StorefrontPage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $page = $this->route('page');
        $pageId = $page instanceof StorefrontPage ? $page->id : null;
        $isHome = $page instanceof StorefrontPage && $page->slug === StorefrontPage::HOME;
        $isCreate = ! $page instanceof StorefrontPage;
        // Home is a singleton resolved by slug; it carries no selectable template.
        $templateRequired = $isCreate && $this->input('slug') !== StorefrontPage::HOME;

        return [
            'store_id' => [
                'required',
                'integer',
                'exists:stores,id',
            ],
            'title' => [
                'required',
                'string',
                'max:255',
            ],
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
                Rule::unique('storefront_pages', 'slug')
                    ->where(fn ($query) => $query->where('store_id', $this->integer('store_id')))
                    ->ignore($pageId),
            ],
            'is_published' => ['boolean'],
        ];
    }
}
