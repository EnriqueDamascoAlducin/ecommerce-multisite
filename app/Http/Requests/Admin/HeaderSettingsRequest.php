<?php

namespace App\Http\Requests\Admin;

use App\Domain\Store\FooterSettingsService;
use App\Models\Category;
use App\Models\Product;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\Website;
use App\Models\WebsiteHeaderSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class HeaderSettingsRequest extends FormRequest
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
            'website_id' => ['required', 'integer', 'exists:websites,id'],
            'store_id' => ['nullable', 'integer', 'exists:stores,id'],
            'cintillo_mode' => ['nullable', Rule::in(['website', 'inherit', 'custom'])],
            'footer_mode' => ['nullable', Rule::in(['website', 'inherit', 'custom'])],
            'cintillo_enabled' => ['boolean'],
            'cintillo_show_on_mobile' => ['boolean'],
            'cintillo_blocks' => ['nullable', 'array', 'max:3'],
            'cintillo_blocks.*.type' => ['required', Rule::in(WebsiteHeaderSettings::BLOCK_TYPES)],
            'cintillo_blocks.*.text' => ['nullable', 'string', 'max:255'],
            'cintillo_blocks.*.social' => ['nullable', 'array'],
            'cintillo_blocks.*.social.*.platform' => ['required', 'string', Rule::in(WebsiteHeaderSettings::SOCIAL_PLATFORMS)],
            'cintillo_blocks.*.social.*.url' => ['nullable', 'url', 'max:2048'],
            'cintillo_blocks.*.url' => ['nullable', 'string', 'max:2048'],
            'cintillo_blocks.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'cintillo_blocks.*.alt' => ['nullable', 'string', 'max:255'],
            'cintillo_blocks.*.link' => ['nullable', 'url', 'max:2048'],
            'cintillo_blocks.*.images' => ['nullable', 'array', 'max:6'],
            'cintillo_blocks.*.images.*.url' => ['nullable', 'string', 'max:2048'],
            'cintillo_blocks.*.images.*.media_id' => ['nullable', 'integer', 'exists:media,id'],
            'cintillo_blocks.*.images.*.alt' => ['nullable', 'string', 'max:255'],
            'cintillo_blocks.*.images.*.link' => ['nullable', 'url', 'max:2048'],
            'cintillo_text_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'cintillo_background_color' => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'header_text_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'header_background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'menu_text_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'menu_background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer' => ['nullable', 'array'],
            'footer.enabled' => ['boolean'],
            'footer.description' => ['nullable', 'string', 'max:500'],
            'footer.copyright' => ['nullable', 'string', 'max:255'],
            'footer.background_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer.text_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer.columns' => ['nullable', 'array', 'max:4'],
            'footer.columns.*.title' => ['nullable', 'string', 'max:80'],
            'footer.columns.*.title_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer.columns.*.link_color' => ['nullable', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'footer.columns.*.links' => ['nullable', 'array', 'max:8'],
            'footer.columns.*.links.*.label' => ['nullable', 'string', 'max:80'],
            'footer.columns.*.links.*.type' => ['nullable', Rule::in(FooterSettingsService::LINK_TYPES)],
            'footer.columns.*.links.*.url' => ['nullable', 'string', 'max:2048'],
            'footer.columns.*.links.*.category_id' => ['nullable', 'integer'],
            'footer.columns.*.links.*.product_id' => ['nullable', 'integer'],
            'footer.columns.*.links.*.page_id' => ['nullable', 'integer'],
            'footer.contact' => ['nullable', 'array', 'max:6'],
            'footer.contact.*.label' => ['nullable', 'string', 'max:80'],
            'footer.contact.*.value' => ['nullable', 'string', 'max:160'],
            'footer.social' => ['nullable', 'array', 'max:5'],
            'footer.social.*.platform' => ['required', 'string', Rule::in(WebsiteHeaderSettings::SOCIAL_PLATFORMS)],
            'footer.social.*.url' => ['nullable', 'url', 'max:2048'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $website = Website::find($this->integer('website_id'));
            $selectedStore = $this->filled('store_id') ? Store::find($this->integer('store_id')) : null;

            if ($selectedStore && $website && $selectedStore->website_id !== $website->id) {
                $validator->errors()->add('store_id', 'La tienda seleccionada no pertenece al website.');

                return;
            }

            if ($this->input('footer_mode') === 'inherit') {
                return;
            }

            $store = $selectedStore ?? $website?->defaultStore();

            if (! $store) {
                return;
            }

            foreach ($this->input('footer.columns', []) as $columnIndex => $column) {
                if (! is_array($column)) {
                    continue;
                }

                foreach ($column['links'] ?? [] as $linkIndex => $link) {
                    if (! is_array($link)) {
                        continue;
                    }

                    $type = $link['type'] ?? 'custom';
                    $path = "footer.columns.{$columnIndex}.links.{$linkIndex}";

                    if ($type === 'custom' && trim((string) ($link['url'] ?? '')) === '') {
                        $validator->errors()->add("{$path}.url", 'La URL es obligatoria para un enlace personalizado.');
                    }

                    if ($type === 'category' && ! Category::query()
                        ->whereKey($link['category_id'] ?? null)
                        ->where('store_id', $store->id)
                        ->active()
                        ->exists()) {
                        $validator->errors()->add("{$path}.category_id", 'La categor?a no est? disponible en esta tienda.');
                    }

                    if ($type === 'product' && ! Product::query()
                        ->whereKey($link['product_id'] ?? null)
                        ->active()
                        ->whereHas('storeLinks', fn ($query) => $query
                            ->where('store_id', $store->id)
                            ->where('is_active', true))
                        ->exists()) {
                        $validator->errors()->add("{$path}.product_id", 'El producto no est? disponible en esta tienda.');
                    }

                    if ($type === 'page' && ! StorefrontPage::query()
                        ->whereKey($link['page_id'] ?? null)
                        ->where('is_published', true)
                        ->whereHas('stores', fn ($query) => $query->where('stores.id', $store->id))
                        ->exists()) {
                        $validator->errors()->add("{$path}.page_id", 'La p?gina no est? disponible en esta tienda.');
                    }
                }
            }
        }];
    }
}
