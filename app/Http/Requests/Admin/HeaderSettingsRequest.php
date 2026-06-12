<?php

namespace App\Http\Requests\Admin;

use App\Models\WebsiteHeaderSettings;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'footer.columns.*.links' => ['nullable', 'array', 'max:8'],
            'footer.columns.*.links.*.label' => ['nullable', 'string', 'max:80'],
            'footer.columns.*.links.*.url' => ['nullable', 'string', 'max:2048'],
            'footer.contact' => ['nullable', 'array', 'max:6'],
            'footer.contact.*.label' => ['nullable', 'string', 'max:80'],
            'footer.contact.*.value' => ['nullable', 'string', 'max:160'],
            'footer.social' => ['nullable', 'array', 'max:5'],
            'footer.social.*.platform' => ['required', 'string', Rule::in(WebsiteHeaderSettings::SOCIAL_PLATFORMS)],
            'footer.social.*.url' => ['nullable', 'url', 'max:2048'],
        ];
    }
}
