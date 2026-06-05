<?php

use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHeaderSettings;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);

    $this->website = Website::factory()->create();
});

function headerPayload(array $overrides = []): array
{
    return array_merge([
        'website_id' => null,
        'cintillo_enabled' => true,
        'cintillo_show_on_mobile' => true,
        'cintillo_blocks' => [
            ['type' => 'text', 'text' => 'Envío gratis en compras mayores a $999'],
        ],
        'cintillo_text_color' => '#ffffff',
        'cintillo_background_color' => '#111827',
    ], $overrides);
}

test('an admin can save a text block and the mobile flag', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_show_on_mobile' => false,
    ]))->assertRedirect(route('admin.header-settings.edit', ['website_id' => $this->website->id]));

    $settings = WebsiteHeaderSettings::firstWhere('website_id', $this->website->id);

    expect($settings->cintillo_show_on_mobile)->toBeFalse()
        ->and($settings->cintillo_blocks)->toHaveCount(1)
        ->and($settings->cintillo_blocks[0]['text'])->toBe('Envío gratis en compras mayores a $999');
    $this->assertDatabaseHas('audit_logs', ['action' => 'header_settings.updated']);
});

test('an admin can save mixed text and social blocks', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_blocks' => [
            ['type' => 'text', 'text' => 'Tel: 555-1234'],
            ['type' => 'social', 'social' => [
                ['platform' => 'facebook', 'url' => 'https://facebook.com/x'],
            ]],
        ],
    ]))->assertRedirect();

    $settings = WebsiteHeaderSettings::firstWhere('website_id', $this->website->id);

    expect($settings->cintillo_blocks)->toHaveCount(2)
        ->and($settings->cintillo_blocks[0]['type'])->toBe('text')
        ->and($settings->cintillo_blocks[1]['type'])->toBe('social')
        ->and($settings->cintillo_blocks[1]['social'][0]['platform'])->toBe('facebook');
});

test('more than three blocks are rejected', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_blocks' => [
            ['type' => 'text', 'text' => 'Uno'],
            ['type' => 'text', 'text' => 'Dos'],
            ['type' => 'text', 'text' => 'Tres'],
            ['type' => 'text', 'text' => 'Cuatro'],
        ],
    ]))->assertSessionHasErrors('cintillo_blocks');
});

test('a social link with an invalid url is rejected', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_blocks' => [
            ['type' => 'social', 'social' => [['platform' => 'facebook', 'url' => 'not-a-url']]],
        ],
    ]))->assertSessionHasErrors('cintillo_blocks.0.social.0.url');
});

test('empty blocks and social links without a url are dropped on save', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_blocks' => [
            ['type' => 'text', 'text' => '   '],
            ['type' => 'social', 'social' => [
                ['platform' => 'facebook', 'url' => 'https://facebook.com/x'],
                ['platform' => 'instagram', 'url' => ''],
            ]],
        ],
    ]))->assertRedirect();

    $settings = WebsiteHeaderSettings::firstWhere('website_id', $this->website->id);

    expect($settings->cintillo_blocks)->toHaveCount(1)
        ->and($settings->cintillo_blocks[0]['type'])->toBe('social')
        ->and($settings->cintillo_blocks[0]['social'])->toHaveCount(1);
});

test('an admin can save an image block', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_blocks' => [
            ['type' => 'image', 'url' => 'https://cdn.example.com/promo.png', 'alt' => 'Promo', 'link' => 'https://example.com'],
        ],
    ]))->assertRedirect();

    $settings = WebsiteHeaderSettings::firstWhere('website_id', $this->website->id);

    expect($settings->cintillo_blocks)->toHaveCount(1)
        ->and($settings->cintillo_blocks[0]['type'])->toBe('image')
        ->and($settings->cintillo_blocks[0]['url'])->toBe('https://cdn.example.com/promo.png')
        ->and($settings->cintillo_blocks[0]['link'])->toBe('https://example.com');
});

test('an image block without a url is dropped', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_blocks' => [['type' => 'image', 'url' => '']],
    ]))->assertRedirect();

    $settings = WebsiteHeaderSettings::firstWhere('website_id', $this->website->id);

    expect($settings->cintillo_blocks)->toHaveCount(0);
});

test('an invalid image link is rejected', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_blocks' => [
            ['type' => 'image', 'url' => 'https://cdn.example.com/x.png', 'link' => 'not-a-url'],
        ],
    ]))->assertSessionHasErrors('cintillo_blocks.0.link');
});

test('an admin can upload a cintillo image', function () {
    Storage::fake('public');

    $this->post(route('admin.header-settings.image'), [
        'file' => UploadedFile::fake()->image('promo.png', 600, 80),
    ])->assertOk()->assertJsonStructure(['id', 'url']);

    $this->assertDatabaseCount('media', 1);
});

test('an invalid hex color is rejected', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'cintillo_background_color' => 'black',
    ]))->assertSessionHasErrors('cintillo_background_color');
});

test('an admin can save custom header and menu colors', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'header_text_color' => '#111827',
        'header_background_color' => '#ffffff',
        'menu_text_color' => '#525252',
        'menu_background_color' => '#f5f5f5',
    ]))->assertRedirect();

    $this->assertDatabaseHas('website_header_settings', [
        'website_id' => $this->website->id,
        'header_background_color' => '#ffffff',
        'menu_background_color' => '#f5f5f5',
    ]);
});

test('an invalid header color is rejected', function () {
    $this->put(route('admin.header-settings.update'), headerPayload([
        'website_id' => $this->website->id,
        'header_text_color' => 'navy',
    ]))->assertSessionHasErrors('header_text_color');
});

test('the Soporte role cannot manage the header settings', function () {
    $support = User::factory()->create();
    $support->assignRole('Soporte');

    $this->actingAs($support)
        ->get(route('admin.header-settings.edit'))
        ->assertForbidden();
});
