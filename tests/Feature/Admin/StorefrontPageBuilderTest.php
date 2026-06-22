<?php

use App\Domain\Storefront\StorefrontHomeTemplate;
use App\Models\Category;
use App\Models\HeaderMenuItem;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStore;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);
});

function seededHomeSectionTypes(): array
{
    return collect(StorefrontHomeTemplate::sections())->pluck('type')->all();
}

test('an admin can list storefront pages', function () {
    $store = Store::factory()->create();

    $this->get(route('admin.storefront.pages.index', ['store_id' => $store->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/storefront/pages/index')
            ->where('currentStoreId', $store->id)
            ->has('pages'));
});

test('an admin can create a storefront page', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Nosotros',
        'slug' => 'nosotros',
        'template' => 'flexible',
        'is_published' => true,
    ])->assertRedirect();

    $this->assertDatabaseHas('storefront_pages', [
        'store_id' => $store->id,
        'slug' => 'nosotros',
        'title' => 'Nosotros',
    ]);
});

test('home page is seeded with template sections on creation', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('slug', StorefrontPage::HOME)
        ->where('store_id', $store->id)
        ->firstOrFail();

    expect($page->sections()->pluck('type')->all())->toBe(seededHomeSectionTypes());
});

test('existing home page is completed without overwriting edited content', function () {
    $store = Store::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $store->id,
        'slug' => StorefrontPage::HOME,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'settings' => ['title' => 'Custom hero'],
    ]);

    $this->get(route('admin.storefront.pages.edit', $page))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('page.sections', 1)
            ->where('page.sections.0.settings.title', 'Custom hero'));

    expect($page->fresh()->sections()->pluck('type')->all())->toBe([
        StorefrontPageSection::TYPE_HERO,
    ]);
});

test('non-home pages are not seeded with template sections', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'About',
        'slug' => 'about',
        'template' => 'flexible',
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('slug', 'about')
        ->where('store_id', $store->id)
        ->firstOrFail();

    expect($page->sections()->count())->toBe(0);
});

test('a user without storefront permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.storefront.pages.index'))->assertForbidden();
});

test('home page cannot be deleted', function () {
    $page = StorefrontPage::factory()->create(['slug' => StorefrontPage::HOME]);

    $this->delete(route('admin.storefront.pages.destroy', $page))->assertStatus(422);
});

test('page content can be updated with template section settings', function () {
    $store = Store::factory()->create();
    $category = Category::factory()->create(['store_id' => $store->id]);
    $media = Media::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', StorefrontPage::HOME)
        ->firstOrFail();

    $sections = $page->sections()->get()->keyBy('type');

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home actualizado',
        'is_published' => true,
        'sections' => [
            [
                'id' => $sections[StorefrontPageSection::TYPE_HERO]->id,
                'settings' => [
                    'background_color' => '#101010',
                    'content_width' => 'full',
                    'eyebrow' => 'Nueva campaña',
                    'title' => 'HOT DAYS',
                    'subtitle' => 'Texto actualizado',
                    'media_id' => $media->id,
                    'buttons' => [
                        ['label' => 'Ver ofertas', 'url' => '/ofertas'],
                        ['label' => 'Catalogo', 'url' => '/catalogo'],
                    ],
                ],
            ],
            [
                'id' => $sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->id,
                'settings' => [
                    'background_color' => '#ffffff',
                    'content_width' => 'container',
                    'title' => 'Especialidades',
                    'title_color' => '#1f2937',
                    'items' => [
                        [
                            'title' => 'Electroterapia',
                            'text' => 'Precision',
                            'icon' => 'zap',
                            'link' => '/electroterapia',
                            'highlighted' => true,
                            'wide' => true,
                            'media_id' => $media->id,
                        ],
                    ],
                ],
            ],
            [
                'id' => $sections[StorefrontPageSection::TYPE_FEATURE_CARDS]->id,
                'settings' => [
                    'background_color' => '#f2f2f2',
                    'items' => [
                        [
                            'title' => 'Servicio tecnico',
                            'text' => 'Soporte experto',
                            'cta_label' => 'Agendar',
                            'cta_url' => '/servicio',
                            'media_id' => $media->id,
                        ],
                    ],
                ],
            ],
            [
                'id' => $sections[StorefrontPageSection::TYPE_BRAND_STRIP]->id,
                'settings' => [
                    'background_color' => '#fafafa',
                    'eyebrow' => 'Partners',
                    'title' => 'Marcas',
                    'display_type' => 'carousel',
                    'logo_size' => 'large',
                    'logo_radius' => 'full',
                    'brands' => [
                        [
                            'name' => 'BTL',
                            'media_id' => $media->id,
                            'link_type' => 'custom',
                            'url' => '/marcas/btl',
                            'category_id' => null,
                            'product_id' => null,
                            'page_id' => null,
                            'media' => ['id' => $media->id, 'url' => $media->url, 'alt' => null],
                        ],
                        [
                            'name' => 'DJO',
                            'media_id' => null,
                            'link_type' => 'category',
                            'url' => null,
                            'category_id' => $category->id,
                            'product_id' => null,
                            'page_id' => null,
                        ],
                    ],
                ],
            ],
            [
                'id' => $sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->id,
                'settings' => [
                    'background_color' => '#ffffff',
                    'title' => 'Cotiza hoy',
                    'text' => 'Te ayudamos',
                    'phone' => '+52 55 0000 0000',
                    'email' => 'ventas@example.com',
                    'interest_areas' => ['Electroterapia', 'Servicio tecnico'],
                    'media_id' => $media->id,
                ],
            ],
        ],
    ])->assertRedirect();

    $page->refresh();
    expect($page->title)->toBe('Home actualizado')
        ->and($sections[StorefrontPageSection::TYPE_HERO]->fresh()->settings['background_color'])->toBe('#101010')
        ->and($sections[StorefrontPageSection::TYPE_HERO]->fresh()->settings['content_width'])->toBe('full')
        ->and($sections[StorefrontPageSection::TYPE_HERO]->fresh()->settings['eyebrow'])->toBe('Nueva campaña')
        ->and($sections[StorefrontPageSection::TYPE_HERO]->fresh()->settings['title'])->toBe('HOT DAYS')
        ->and($sections[StorefrontPageSection::TYPE_HERO]->fresh()->settings['subtitle'])->toBe('Texto actualizado')
        ->and($sections[StorefrontPageSection::TYPE_HERO]->fresh()->settings['media_id'])->toBe($media->id)
        ->and($sections[StorefrontPageSection::TYPE_HERO]->fresh()->settings['buttons'][0]['label'])->toBe('Ver ofertas')
        ->and($sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->fresh()->settings['background_color'])->toBe('#ffffff')
        ->and($sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->fresh()->settings['content_width'])->toBe('container')
        ->and($sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->fresh()->settings['title'])->toBe('Especialidades')
        ->and($sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->fresh()->settings['title_color'])->toBe('#1f2937')
        ->and($sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->fresh()->settings['items'][0]['highlighted'])->toBeTrue()
        ->and($sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->fresh()->settings['items'][0]['wide'])->toBeTrue()
        ->and($sections[StorefrontPageSection::TYPE_FEATURE_CARDS]->fresh()->settings['background_color'])->toBe('#f2f2f2')
        ->and($sections[StorefrontPageSection::TYPE_BRAND_STRIP]->fresh()->settings['background_color'])->toBe('#fafafa')
        ->and($sections[StorefrontPageSection::TYPE_BRAND_STRIP]->fresh()->settings['eyebrow'])->toBe('Partners')
        ->and($sections[StorefrontPageSection::TYPE_BRAND_STRIP]->fresh()->settings['title'])->toBe('Marcas')
        ->and($sections[StorefrontPageSection::TYPE_BRAND_STRIP]->fresh()->settings['display_type'])->toBe('carousel')
        ->and($sections[StorefrontPageSection::TYPE_BRAND_STRIP]->fresh()->settings['logo_size'])->toBe('large')
        ->and($sections[StorefrontPageSection::TYPE_BRAND_STRIP]->fresh()->settings['logo_radius'])->toBe('full')
        ->and($sections[StorefrontPageSection::TYPE_BRAND_STRIP]->fresh()->settings['brands'])->toBe([
            [
                'name' => 'BTL',
                'media_id' => $media->id,
                'link_type' => 'custom',
                'url' => '/marcas/btl',
                'category_id' => null,
                'product_id' => null,
                'page_id' => null,
            ],
            [
                'name' => 'DJO',
                'media_id' => null,
                'link_type' => 'category',
                'url' => null,
                'category_id' => $category->id,
                'product_id' => null,
                'page_id' => null,
            ],
        ])
        ->and($sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->fresh()->settings['background_color'])->toBe('#ffffff')
        ->and($sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->fresh()->settings['title'])->toBe('Cotiza hoy')
        ->and($sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->fresh()->settings['text'])->toBe('Te ayudamos')
        ->and($sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->fresh()->settings['phone'])->toBe('+52 55 0000 0000')
        ->and($sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->fresh()->settings['email'])->toBe('ventas@example.com')
        ->and($sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->fresh()->settings['interest_areas'])->toBe(['Electroterapia', 'Servicio tecnico']);
});

test('creating a page seeds the chosen template fixed sections', function (string $template, array $expectedTypes) {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Pagina',
        'slug' => 'pagina',
        'template' => $template,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', 'pagina')
        ->firstOrFail();

    expect($page->template)->toBe($template)
        ->and($page->sections()->pluck('type')->all())->toBe($expectedTypes);
})->with([
    'contact' => ['contact', ['page_header', 'contact_info', 'inquiry_form']],
    'legal' => ['legal', ['page_header', 'rich_text']],
    'about' => ['about', ['page_header', 'rich_text']],
    'flexible' => ['flexible', []],
]);

test('creating a page rejects an unknown template', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Pagina',
        'slug' => 'pagina',
        'template' => 'does-not-exist',
        'is_published' => true,
    ])->assertSessionHasErrors('template');
});

test('a flexible page accepts any section type', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Landing',
        'slug' => 'landing',
        'template' => 'flexible',
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', 'landing')
        ->firstOrFail();

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Landing',
        'slug' => 'landing',
        'template' => 'flexible',
        'is_published' => true,
        'sections' => [
            ['type' => StorefrontPageSection::TYPE_HERO, 'settings' => ['title' => 'Hola']],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($page->fresh()->sections()->pluck('type')->all())
        ->toContain(StorefrontPageSection::TYPE_HERO);
});

test('a legal page rejects a section type outside its template', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Privacidad',
        'slug' => 'privacidad',
        'template' => 'legal',
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', 'privacidad')
        ->firstOrFail();
    $sections = $page->sections()->get()->keyBy('type');

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Privacidad',
        'slug' => 'privacidad',
        'template' => 'legal',
        'is_published' => true,
        'sections' => [
            ['id' => $sections[StorefrontPageSection::TYPE_PAGE_HEADER]->id, 'settings' => []],
            ['type' => StorefrontPageSection::TYPE_HERO, 'settings' => []],
        ],
    ])->assertSessionHasErrors('sections.1.type');
});

test('changing a page template reseeds fixed sections without deleting content', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Pagina',
        'slug' => 'pagina',
        'template' => 'legal',
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', 'pagina')
        ->firstOrFail();
    $richTextId = $page->sections()
        ->where('type', StorefrontPageSection::TYPE_RICH_TEXT)
        ->firstOrFail()->id;

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Pagina',
        'slug' => 'pagina',
        'template' => 'contact',
        'is_published' => true,
    ])->assertRedirect();

    $page->refresh();

    expect($page->template)->toBe('contact')
        ->and($page->sections()->pluck('type')->all())->toContain(
            StorefrontPageSection::TYPE_PAGE_HEADER,
            StorefrontPageSection::TYPE_CONTACT_INFO,
            StorefrontPageSection::TYPE_INQUIRY_FORM,
            StorefrontPageSection::TYPE_RICH_TEXT,
        )
        ->and($page->sections()->whereKey($richTextId)->exists())->toBeTrue();
});

test('rich text html is sanitized when saving', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Privacidad',
        'slug' => 'privacidad',
        'template' => 'legal',
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', 'privacidad')
        ->firstOrFail();
    $sections = $page->sections()->get()->keyBy('type');

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Privacidad',
        'slug' => 'privacidad',
        'template' => 'legal',
        'is_published' => true,
        'sections' => [
            ['id' => $sections[StorefrontPageSection::TYPE_PAGE_HEADER]->id, 'settings' => []],
            [
                'id' => $sections[StorefrontPageSection::TYPE_RICH_TEXT]->id,
                'settings' => [
                    'html' => '<p><span style="color: #ff0000; position: fixed;">Contenido</span>'
                        .'<mark style="background-color: #fef08a">resaltado</mark>'
                        .'<script>alert(1)</script></p>',
                ],
            ],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $html = $page->sections()
        ->whereKey($sections[StorefrontPageSection::TYPE_RICH_TEXT]->id)
        ->firstOrFail()->settings['html'];

    expect($html)->toContain('Contenido')
        ->and($html)->not->toContain('<script')
        // Color/highlight styles survive, dangerous declarations are stripped.
        ->and($html)->toContain('color: #ff0000')
        ->and($html)->toContain('background-color: #fef08a')
        ->and($html)->not->toContain('position');
});

test('hero can be saved with slides and strips resolved media objects', function () {
    $store = Store::factory()->create();
    $first = Media::factory()->create();
    $second = Media::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', StorefrontPage::HOME)
        ->firstOrFail();
    $sections = $page->sections()->get()->keyBy('type');

    $payload = collect(seededHomeSectionTypes())
        ->map(fn (string $type) => [
            'id' => $sections[$type]->id,
            'settings' => $sections[$type]->settings,
        ])
        ->all();

    $payload[0]['settings'] = [
        'slides' => [
            [
                'media_id' => $first->id,
                // A resolved media object as it arrives from the presenter; must not be persisted.
                'media' => ['id' => $first->id, 'url' => $first->url, 'alt' => null],
                'eyebrow' => 'Campaña',
                'title' => 'Primer slide',
                'subtitle' => 'Subtitulo uno',
                'overlay_enabled' => true,
                'overlay_color' => '#123456',
                'overlay_opacity' => 62,
                'buttons' => [
                    ['label' => 'Ver ofertas', 'url' => '/ofertas'],
                    ['label' => 'Catalogo', 'url' => '/catalogo'],
                ],
            ],
            [
                'media_id' => $second->id,
                'title' => 'Segundo slide',
            ],
        ],
    ];

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => $payload,
    ])->assertRedirect()->assertSessionHasNoErrors();

    $heroSettings = $sections[StorefrontPageSection::TYPE_HERO]->fresh()->settings;

    expect($heroSettings['slides'])->toHaveCount(2)
        ->and($heroSettings['slides'][0]['media_id'])->toBe($first->id)
        ->and($heroSettings['slides'][0]['title'])->toBe('Primer slide')
        ->and($heroSettings['slides'][0]['subtitle'])->toBe('Subtitulo uno')
        ->and($heroSettings['slides'][0]['overlay_enabled'])->toBeTrue()
        ->and($heroSettings['slides'][0]['overlay_color'])->toBe('#123456')
        ->and($heroSettings['slides'][0]['overlay_opacity'])->toBe(62)
        ->and($heroSettings['slides'][0]['buttons'][0]['label'])->toBe('Ver ofertas')
        ->and($heroSettings['slides'][0])->not->toHaveKey('media')
        ->and($heroSettings['slides'][1]['media_id'])->toBe($second->id)
        ->and($heroSettings['slides'][1]['title'])->toBe('Segundo slide');
});

test('hero rejects more than five slides', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', StorefrontPage::HOME)
        ->firstOrFail();
    $sections = $page->sections()->get()->keyBy('type');

    $payload = collect(seededHomeSectionTypes())
        ->map(fn (string $type) => [
            'id' => $sections[$type]->id,
            'settings' => $sections[$type]->settings,
        ])
        ->all();

    $payload[0]['settings'] = [
        'slides' => collect(range(1, 6))
            ->map(fn (int $index) => ['title' => "Slide {$index}"])
            ->all(),
    ];

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => $payload,
    ])->assertSessionHasErrors('sections.0.settings.slides');
});

test('home template sections can be reordered', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', StorefrontPage::HOME)
        ->firstOrFail();

    $sections = $page->sections()->get()->keyBy('type');
    $orderedTypes = [
        StorefrontPageSection::TYPE_INQUIRY_FORM,
        StorefrontPageSection::TYPE_HERO,
        StorefrontPageSection::TYPE_BRAND_STRIP,
        StorefrontPageSection::TYPE_SPECIALTY_GRID,
        StorefrontPageSection::TYPE_FEATURE_CARDS,
    ];

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => collect($orderedTypes)
            ->map(fn (string $type) => [
                'id' => $sections[$type]->id,
                'settings' => $sections[$type]->settings,
            ])
            ->all(),
    ])->assertRedirect();

    $freshSections = $page->fresh()->sections()->get()->keyBy('type');

    foreach ($orderedTypes as $index => $type) {
        expect($freshSections[$type]->settings['display_order'])->toBe($index);
    }
});

test('home sections can be deleted without being reinserted', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', StorefrontPage::HOME)
        ->firstOrFail();
    $sections = $page->sections()->get()->keyBy('type');

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => collect(seededHomeSectionTypes())
            ->reject(fn (string $type) => $type === StorefrontPageSection::TYPE_SPECIALTY_GRID)
            ->map(fn (string $type) => [
                'id' => $sections[$type]->id,
                'settings' => $sections[$type]->settings,
            ])
            ->values()
            ->all(),
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($page->fresh()->sections()->pluck('type')->all())
        ->not->toContain(StorefrontPageSection::TYPE_SPECIALTY_GRID);

    $this->get(route('admin.storefront.pages.edit', $page))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->missing('page.sections.4')
            ->where('template.fixedTypes', [])
            ->where('template.extraTypes.1', StorefrontPageSection::TYPE_SPECIALTY_GRID));

    expect($page->fresh()->sections()->pluck('type')->all())
        ->not->toContain(StorefrontPageSection::TYPE_SPECIALTY_GRID);
});

test('home can be saved with zero sections and add one back later', function () {
    $store = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', StorefrontPage::HOME)
        ->firstOrFail();

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => [],
    ])->assertRedirect()->assertSessionHasNoErrors();

    expect($page->fresh()->sections()->count())->toBe(0);

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => [
            [
                'type' => StorefrontPageSection::TYPE_SPECIALTY_GRID,
                'settings' => [
                    'title' => 'Especialidades sports',
                    'items' => [],
                ],
            ],
        ],
    ])->assertRedirect()->assertSessionHasNoErrors();

    $section = $page->fresh()->sections()->firstOrFail();

    expect($section->type)->toBe(StorefrontPageSection::TYPE_SPECIALTY_GRID)
        ->and($section->settings['title'])->toBe('Especialidades sports')
        ->and($section->settings['display_order'])->toBe(0);
});

test('home can add move and delete controlled extra sections', function () {
    $store = Store::factory()->create();
    $media = Media::factory()->create();
    $product = Product::factory()->create();
    ProductStore::factory()->create([
        'product_id' => $product->id,
        'store_id' => $store->id,
        'is_active' => true,
    ]);
    ProductPrice::factory()->create(['product_id' => $product->id]);

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', StorefrontPage::HOME)
        ->firstOrFail();
    $sections = $page->sections()->get()->keyBy('type');

    $payloadSections = [
        [
            'id' => $sections[StorefrontPageSection::TYPE_HERO]->id,
            'settings' => $sections[StorefrontPageSection::TYPE_HERO]->settings,
        ],
        [
            'type' => StorefrontPageSection::TYPE_IMAGE_BANNER,
            'settings' => [
                'background_color' => '#ffffff',
                'content_width' => 'container',
                'eyebrow' => 'Nuevo',
                'title' => 'Banner',
                'text' => 'Texto banner',
                'media_id' => $media->id,
                'button_label' => 'Ver',
                'button_url' => '/ver',
                'image_position' => 'right',
                'overlay_enabled' => false,
                'overlay_color' => '#123456',
                'overlay_opacity' => 45,
            ],
        ],
        [
            'id' => $sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->id,
            'settings' => $sections[StorefrontPageSection::TYPE_SPECIALTY_GRID]->settings,
        ],
        [
            'type' => StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS,
            'settings' => [
                'background_color' => '#ffffff',
                'content_width' => 'container',
                'title' => 'Recomendados',
                'product_ids' => [$product->id],
                'display_type' => 'carousel',
                'columns' => 4,
            ],
        ],
        [
            'id' => $sections[StorefrontPageSection::TYPE_FEATURE_CARDS]->id,
            'settings' => $sections[StorefrontPageSection::TYPE_FEATURE_CARDS]->settings,
        ],
        [
            'id' => $sections[StorefrontPageSection::TYPE_BRAND_STRIP]->id,
            'settings' => $sections[StorefrontPageSection::TYPE_BRAND_STRIP]->settings,
        ],
        [
            'id' => $sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->id,
            'settings' => $sections[StorefrontPageSection::TYPE_INQUIRY_FORM]->settings,
        ],
    ];

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => $payloadSections,
    ])->assertRedirect();

    $freshTypes = $page->fresh()->sections()
        ->get()
        ->sortBy(fn (StorefrontPageSection $section) => $section->settings['display_order'] ?? 99)
        ->pluck('type')
        ->values()
        ->all();

    expect($freshTypes)->toBe([
        StorefrontPageSection::TYPE_HERO,
        StorefrontPageSection::TYPE_IMAGE_BANNER,
        StorefrontPageSection::TYPE_SPECIALTY_GRID,
        StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS,
        StorefrontPageSection::TYPE_FEATURE_CARDS,
        StorefrontPageSection::TYPE_BRAND_STRIP,
        StorefrontPageSection::TYPE_INQUIRY_FORM,
    ]);

    expect($page->fresh()->sections()
        ->where('type', StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS)
        ->firstOrFail()
        ->settings['display_type'])->toBe('carousel');

    $bannerSettings = $page->fresh()->sections()
        ->where('type', StorefrontPageSection::TYPE_IMAGE_BANNER)
        ->firstOrFail()
        ->settings;

    expect($bannerSettings['overlay_enabled'])->toBeFalse()
        ->and($bannerSettings['overlay_color'])->toBe('#123456')
        ->and($bannerSettings['overlay_opacity'])->toBe(45);

    $extras = $page->fresh()->sections()
        ->whereIn('type', StorefrontPageSection::EXTRA_TYPES)
        ->get();

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => collect($payloadSections)
            ->filter(fn (array $section) => isset($section['id']))
            ->values()
            ->all(),
    ])->assertRedirect();

    foreach ($extras as $extra) {
        expect($extra->fresh())->toBeNull();
    }
});

test('recommended products must belong to the current store', function () {
    $store = Store::factory()->create();
    $otherStore = Store::factory()->create();
    $product = Product::factory()->create();
    ProductStore::factory()->create([
        'product_id' => $product->id,
        'store_id' => $otherStore->id,
        'is_active' => true,
    ]);

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('store_id', $store->id)
        ->where('slug', StorefrontPage::HOME)
        ->firstOrFail();
    $sections = $page->sections()->get()->keyBy('type');
    $fixedSectionsPayload = collect(seededHomeSectionTypes())
        ->map(fn (string $type) => [
            'id' => $sections[$type]->id,
            'settings' => $sections[$type]->settings,
        ])
        ->all();

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => [
            ...$fixedSectionsPayload,
            [
                'type' => StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS,
                'settings' => ['product_ids' => [$product->id]],
            ],
        ],
    ])->assertSessionHasErrors('sections.5.settings.product_ids');
});

test('duplicate section ids are rejected', function () {
    $store = Store::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $store->id,
        'slug' => StorefrontPage::HOME,
    ]);
    $section = StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'settings' => ['title' => 'Hero'],
    ]);

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'is_published' => true,
        'sections' => [
            ['id' => $section->id, 'settings' => ['title' => 'First']],
            ['id' => $section->id, 'settings' => ['title' => 'Second']],
        ],
    ])->assertSessionHasErrors('sections.1.id');
});

test('home slug cannot be changed through update payload', function () {
    $store = Store::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $store->id,
        'slug' => StorefrontPage::HOME,
    ]);

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => 'Home',
        'slug' => 'otro-home',
        'is_published' => true,
    ])->assertSessionHasErrors('slug');

    expect($page->fresh()->slug)->toBe(StorefrontPage::HOME);
});

test('section from another page is rejected and not updated', function () {
    $store = Store::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $store->id,
        'slug' => 'page-a',
    ]);
    $otherPage = StorefrontPage::factory()->create([
        'store_id' => $store->id,
        'slug' => 'page-b',
    ]);
    $section = StorefrontPageSection::factory()->create([
        'storefront_page_id' => $otherPage->id,
        'settings' => ['title' => 'Original'],
    ]);

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $store->id,
        'title' => $page->title,
        'slug' => $page->slug,
        'is_published' => true,
        'sections' => [
            ['id' => $section->id, 'settings' => ['title' => 'Changed']],
        ],
    ])->assertSessionHasErrors('sections.0.id');

    expect($section->fresh()->settings['title'])->toBe('Original');
});

test('an admin can create one page for multiple stores', function () {
    $primary = Store::factory()->create();
    $secondary = Store::factory()->create();

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $primary->id,
        'store_ids' => [$primary->id, $secondary->id],
        'title' => 'Bolsa de trabajo',
        'slug' => 'bolsa-de-trabajo',
        'template' => 'flexible',
        'is_published' => true,
    ])->assertRedirect();

    $page = StorefrontPage::where('slug', 'bolsa-de-trabajo')->firstOrFail();

    $this->assertDatabaseHas('storefront_page_store', [
        'storefront_page_id' => $page->id,
        'store_id' => $primary->id,
    ]);
    $this->assertDatabaseHas('storefront_page_store', [
        'storefront_page_id' => $page->id,
        'store_id' => $secondary->id,
    ]);
});

test('a shared slug cannot overlap any selected store', function () {
    $primary = Store::factory()->create();
    $secondary = Store::factory()->create();

    StorefrontPage::factory()->create([
        'store_id' => $primary->id,
        'slug' => 'vacantes',
    ]);

    $this->post(route('admin.storefront.pages.store'), [
        'store_id' => $secondary->id,
        'store_ids' => [$primary->id, $secondary->id],
        'title' => 'Otra pagina',
        'slug' => 'vacantes',
        'template' => 'flexible',
        'is_published' => true,
    ])->assertSessionHasErrors('slug');
});

test('an admin can unlink a shared page and its menu links from one store', function () {
    $primary = Store::factory()->create();
    $secondary = Store::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $primary->id,
        'slug' => 'vacantes',
    ]);
    $page->stores()->attach($secondary->id);

    HeaderMenuItem::create([
        'store_id' => $primary->id,
        'type' => HeaderMenuItem::TYPE_PAGE,
        'label' => 'Vacantes',
        'page_id' => $page->id,
        'is_active' => true,
        'sort_order' => 0,
    ]);

    $this->delete(route('admin.storefront.pages.unlink', [$page, $primary]))
        ->assertRedirect(route('admin.storefront.pages.index', ['store_id' => $primary->id]));

    expect($page->fresh()->store_id)->toBe($secondary->id);
    $this->assertDatabaseMissing('storefront_page_store', [
        'storefront_page_id' => $page->id,
        'store_id' => $primary->id,
    ]);
    $this->assertDatabaseMissing('store_header_menu_items', [
        'store_id' => $primary->id,
        'page_id' => $page->id,
    ]);
});

test('a page with recommended products cannot be shared', function () {
    $primary = Store::factory()->create();
    $secondary = Store::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $primary->id,
        'slug' => 'seleccion',
        'template' => 'flexible',
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS,
    ]);

    $this->put(route('admin.storefront.pages.update', $page), [
        'store_id' => $primary->id,
        'store_ids' => [$primary->id, $secondary->id],
        'title' => $page->title,
        'slug' => $page->slug,
        'template' => 'flexible',
        'is_published' => true,
    ])->assertSessionHasErrors('store_ids');
});

test('deleting a store preserves pages shared with another store', function () {
    $remaining = Store::factory()->create();
    $deleted = Store::factory()->create([
        'website_id' => $remaining->website_id,
        'is_default' => false,
    ]);
    $page = StorefrontPage::factory()->create([
        'store_id' => $deleted->id,
        'slug' => 'equipo',
    ]);
    $page->stores()->attach($remaining->id);

    $this->delete(route('admin.stores.destroy', $deleted))->assertRedirect();

    $page->refresh();

    expect($page->exists)->toBeTrue()
        ->and($page->store_id)->toBe($remaining->id);
});
