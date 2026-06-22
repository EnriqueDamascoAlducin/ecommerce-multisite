<?php

use App\Domain\Storefront\StorefrontHomeTemplate;
use App\Models\Category;
use App\Models\InventorySource;
use App\Models\Media;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductStore;
use App\Models\Store;
use App\Models\StorefrontPage;
use App\Models\StorefrontPageSection;
use App\Models\Website;

beforeEach(function () {
    $this->website = Website::factory()->create(['is_default' => true]);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
});

test('home renders published template sections for the resolved store', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    foreach (StorefrontHomeTemplate::sections() as $section) {
        StorefrontPageSection::factory()->create([
            'storefront_page_id' => $page->id,
            'type' => $section['type'],
            'settings' => $section['settings'],
        ]);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->component('storefront/home')
            ->has('contentPage.sections', 5)
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_HERO)
            ->where('contentPage.sections.1.type', StorefrontPageSection::TYPE_SPECIALTY_GRID)
            ->where('contentPage.sections.2.type', StorefrontPageSection::TYPE_FEATURE_CARDS)
            ->where('contentPage.sections.3.type', StorefrontPageSection::TYPE_BRAND_STRIP)
            ->where('contentPage.sections.4.type', StorefrontPageSection::TYPE_INQUIRY_FORM));
});

test('home renders only saved sections', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'settings' => ['title' => 'Home sin especialidades'],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('contentPage.sections', 1)
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_HERO)
            ->where('contentPage.sections.0.settings.title', 'Home sin especialidades'));
});

test('home renders template sections using saved display order', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    $displayOrderByType = [
        StorefrontPageSection::TYPE_INQUIRY_FORM => 0,
        StorefrontPageSection::TYPE_BRAND_STRIP => 1,
        StorefrontPageSection::TYPE_HERO => 2,
        StorefrontPageSection::TYPE_FEATURE_CARDS => 3,
        StorefrontPageSection::TYPE_SPECIALTY_GRID => 4,
    ];

    foreach (StorefrontHomeTemplate::sections() as $section) {
        StorefrontPageSection::factory()->create([
            'storefront_page_id' => $page->id,
            'type' => $section['type'],
            'settings' => [
                ...$section['settings'],
                'display_order' => $displayOrderByType[$section['type']],
            ],
        ]);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_INQUIRY_FORM)
            ->where('contentPage.sections.1.type', StorefrontPageSection::TYPE_BRAND_STRIP)
            ->where('contentPage.sections.2.type', StorefrontPageSection::TYPE_HERO)
            ->where('contentPage.sections.3.type', StorefrontPageSection::TYPE_FEATURE_CARDS)
            ->where('contentPage.sections.4.type', StorefrontPageSection::TYPE_SPECIALTY_GRID));
});

test('home sections without display order fall back to template order', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    foreach (array_reverse(StorefrontHomeTemplate::sections()) as $section) {
        $settings = $section['settings'];
        unset($settings['display_order']);

        StorefrontPageSection::factory()->create([
            'storefront_page_id' => $page->id,
            'type' => $section['type'],
            'settings' => $settings,
        ]);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_HERO)
            ->where('contentPage.sections.1.type', StorefrontPageSection::TYPE_SPECIALTY_GRID)
            ->where('contentPage.sections.2.type', StorefrontPageSection::TYPE_FEATURE_CARDS)
            ->where('contentPage.sections.3.type', StorefrontPageSection::TYPE_BRAND_STRIP)
            ->where('contentPage.sections.4.type', StorefrontPageSection::TYPE_INQUIRY_FORM));
});

test('home falls back when no cms page exists', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('contentPage', null));
});

test('media ids are resolved in template section settings', function () {
    $media = Media::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_SPECIALTY_GRID,
        'settings' => [
            'title' => 'Especialidades',
            'title_color' => '#1f2937',
            'items' => [
                [
                    'title' => 'Hidroterapia',
                    'media_id' => $media->id,
                ],
            ],
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.settings.title_color', '#1f2937')
            ->where('contentPage.sections.0.settings.items.0.media.id', $media->id)
            ->where('contentPage.sections.0.settings.items.0.media.url', $media->url));
});

test('brand strip exposes resolved media and text-only brands', function () {
    $media = Media::factory()->create();
    $category = Category::factory()->create(['store_id' => $this->store->id]);
    $product = Product::factory()->create(['slug' => 'air-brand']);
    ProductStore::factory()->create([
        'product_id' => $product->id,
        'store_id' => $this->store->id,
        'is_active' => true,
    ]);
    $linkedPage = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => 'marcas',
        'is_published' => true,
    ]);
    $linkedPage->stores()->sync([$this->store->id]);
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_BRAND_STRIP,
        'settings' => [
            'title' => 'Marcas',
            'display_type' => 'carousel',
            'logo_size' => 'large',
            'logo_radius' => 'full',
            'brands' => [
                [
                    'name' => 'BTL',
                    'media_id' => $media->id,
                    'link_type' => 'category',
                    'category_id' => $category->id,
                ],
                [
                    'name' => 'DJO',
                    'media_id' => null,
                    'link_type' => 'product',
                    'product_id' => $product->id,
                ],
                [
                    'name' => 'Airex',
                    'media_id' => null,
                    'link_type' => 'page',
                    'page_id' => $linkedPage->id,
                ],
                [
                    'name' => 'Custom',
                    'media_id' => null,
                    'link_type' => 'custom',
                    'url' => 'https://example.com/marca',
                ],
            ],
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_BRAND_STRIP)
            ->where('contentPage.sections.0.settings.display_type', 'carousel')
            ->where('contentPage.sections.0.settings.logo_size', 'large')
            ->where('contentPage.sections.0.settings.logo_radius', 'full')
            ->where('contentPage.sections.0.settings.brands.0.name', 'BTL')
            ->where('contentPage.sections.0.settings.brands.0.media.url', $media->url)
            ->where('contentPage.sections.0.settings.brands.0.url', "/c/{$category->slug}")
            ->where('contentPage.sections.0.settings.brands.1.name', 'DJO')
            ->where('contentPage.sections.0.settings.brands.1.media_id', null)
            ->where('contentPage.sections.0.settings.brands.1.url', '/p/air-brand')
            ->where('contentPage.sections.0.settings.brands.2.url', '/marcas')
            ->where('contentPage.sections.0.settings.brands.3.url', 'https://example.com/marca'));
});

test('hero slides expose resolved media for each slide', function () {
    $first = Media::factory()->create();
    $second = Media::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'settings' => [
            'slides' => [
                [
                    'eyebrow' => 'Campaña',
                    'title' => 'Primer slide',
                    'media_id' => $first->id,
                    'buttons' => [['label' => 'Ver', 'url' => '/uno']],
                ],
                [
                    'title' => 'Segundo slide',
                    'media_id' => $second->id,
                ],
            ],
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_HERO)
            ->has('contentPage.sections.0.settings.slides', 2)
            ->where('contentPage.sections.0.settings.slides.0.title', 'Primer slide')
            ->where('contentPage.sections.0.settings.slides.0.media.id', $first->id)
            ->where('contentPage.sections.0.settings.slides.0.media.url', $first->url)
            ->where('contentPage.sections.0.settings.slides.1.media.id', $second->id));
});

test('legacy hero without slides still resolves its background media', function () {
    $media = Media::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'settings' => [
            'title' => 'Hero antiguo',
            'media_id' => $media->id,
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_HERO)
            ->where('contentPage.sections.0.settings.title', 'Hero antiguo')
            ->where('contentPage.sections.0.settings.media.id', $media->id));
});

test('a legal page renders its rich text content by slug', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => 'privacidad',
        'template' => 'legal',
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_PAGE_HEADER,
        'settings' => ['display_order' => 0, 'title' => 'Aviso de privacidad'],
    ]);
    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_RICH_TEXT,
        'settings' => ['display_order' => 1, 'html' => '<p>Tu privacidad importa.</p>'],
    ]);

    $this->get('/privacidad')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.slug', 'privacidad')
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_PAGE_HEADER)
            ->where('contentPage.sections.0.settings.title', 'Aviso de privacidad')
            ->where('contentPage.sections.1.type', StorefrontPageSection::TYPE_RICH_TEXT)
            ->where('contentPage.sections.1.settings.html', '<p>Tu privacidad importa.</p>'));
});

test('a contact page exposes contact info and the inquiry form', function () {
    $media = Media::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => 'contacto',
        'template' => 'contact',
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_CONTACT_INFO,
        'settings' => [
            'display_order' => 0,
            'email' => 'hola@tienda.com',
            'phone' => '555-1234',
        ],
    ]);
    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_INQUIRY_FORM,
        'settings' => [
            'display_order' => 1,
            'title' => 'Escríbenos',
            'media_id' => $media->id,
        ],
    ]);

    $this->get('/contacto')
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_CONTACT_INFO)
            ->where('contentPage.sections.0.settings.email', 'hola@tienda.com')
            ->where('contentPage.sections.1.type', StorefrontPageSection::TYPE_INQUIRY_FORM)
            ->where('contentPage.sections.1.settings.media.url', $media->url));
});

test('home renders controlled extra blocks in saved order', function () {
    $media = Media::factory()->create();
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_IMAGE_BANNER,
        'settings' => [
            'display_order' => 0,
            'title' => 'Banner nuevo',
            'media_id' => $media->id,
            'image_position' => 'right',
            'overlay_enabled' => false,
            'overlay_color' => '#123456',
            'overlay_opacity' => 45,
        ],
    ]);

    foreach (StorefrontHomeTemplate::sections() as $index => $section) {
        StorefrontPageSection::factory()->create([
            'storefront_page_id' => $page->id,
            'type' => $section['type'],
            'settings' => [
                ...$section['settings'],
                'display_order' => $index + 1,
            ],
        ]);
    }

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->has('contentPage.sections', 6)
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_IMAGE_BANNER)
            ->where('contentPage.sections.0.settings.media.id', $media->id)
            ->where('contentPage.sections.0.settings.overlay_enabled', false)
            ->where('contentPage.sections.0.settings.overlay_color', '#123456')
            ->where('contentPage.sections.0.settings.overlay_opacity', 45)
            ->where('contentPage.sections.1.type', StorefrontPageSection::TYPE_HERO));
});

test('recommended products resolve active products for the current store in manual order', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);
    $first = Product::factory()->create(['name' => 'First product']);
    $second = Product::factory()->create(['name' => 'Second product']);
    $inactive = Product::factory()->inactive()->create(['name' => 'Inactive product']);
    $otherStoreProduct = Product::factory()->create(['name' => 'Other store product']);
    $otherStore = Store::factory()->create(['website_id' => $this->website->id]);

    foreach ([$first, $second, $inactive] as $product) {
        ProductStore::factory()->create([
            'product_id' => $product->id,
            'store_id' => $this->store->id,
            'is_active' => true,
        ]);
        ProductPrice::factory()->create(['product_id' => $product->id]);
    }

    ProductStore::factory()->create([
        'product_id' => $otherStoreProduct->id,
        'store_id' => $otherStore->id,
        'is_active' => true,
    ]);
    ProductPrice::factory()->create(['product_id' => $otherStoreProduct->id]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS,
        'settings' => [
            'title' => 'Recomendados',
            'display_type' => 'carousel',
            'product_ids' => [
                $second->id,
                $inactive->id,
                $otherStoreProduct->id,
                $first->id,
            ],
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.type', StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS)
            ->where('contentPage.sections.0.settings.display_type', 'carousel')
            ->has('contentPage.sections.0.settings.products', 2)
            ->where('contentPage.sections.0.settings.products.0.name', 'Second product')
            ->where('contentPage.sections.0.settings.products.1.name', 'First product'));
});

test('recommended products expose dynamic bundle prices', function () {
    $source = InventorySource::factory()->default()->create();
    $shirt = sellableProduct($this->store, $source, 120);
    $cap = sellableProduct($this->store, $source, 80);
    $bundle = bundleProduct($this->store, [[$shirt, 1], [$cap, 2]]);
    $bundle->update(['name' => 'Kit Deportivo']);

    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_RECOMMENDED_PRODUCTS,
        'settings' => [
            'title' => 'Recomendados',
            'display_type' => 'carousel',
            'product_ids' => [$bundle->id],
        ],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia
            ->where('contentPage.sections.0.settings.products.0.name', 'Kit Deportivo')
            ->where('contentPage.sections.0.settings.products.0.price.effective_price', '280.00'));
});

test('obsolete pagebuilder section types are not rendered', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => StorefrontPage::HOME,
        'is_published' => true,
    ]);

    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => 'featured_products',
        'settings' => ['title' => 'Old builder'],
    ]);

    $this->get(route('home'))
        ->assertOk()
        ->assertInertia(fn ($inertia) => $inertia->has('contentPage.sections', 0));
});

test('published cms page renders by slug without automatic template sections', function () {
    $page = StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => 'nosotros',
        'title' => 'Nosotros',
        'is_published' => true,
    ]);
    StorefrontPageSection::factory()->create([
        'storefront_page_id' => $page->id,
        'type' => StorefrontPageSection::TYPE_HERO,
        'settings' => ['title' => 'Equipo experto'],
    ]);

    $this->get('/nosotros')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/home')
            ->where('contentPage.slug', 'nosotros')
            ->where('contentPage.sections.0.settings.title', 'Equipo experto'));
});

test('unpublished cms page returns not found', function () {
    StorefrontPage::factory()->create([
        'store_id' => $this->store->id,
        'slug' => 'oculta',
        'is_published' => false,
    ]);

    $this->get('/oculta')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/error')
            ->where('status', 404)
            ->where('store.store.id', $this->store->id)
            ->where('store.pathPrefix', ''));
});

test('missing cms page renders the storefront not found page', function () {
    $this->get('/nosotros')
        ->assertNotFound()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/error')
            ->where('status', 404)
            ->where('store.store.id', $this->store->id)
            ->where('store.pathPrefix', ''));
});

test('admin not found responses do not render the storefront error page', function () {
    $response = $this->get('/admin/no-existe');

    $response->assertNotFound();

    expect($response->getContent())->not->toContain('storefront/error');
});

test('category route still wins over cms catch all route', function () {
    $this->get('/c/no-existe')->assertNotFound();
});

test('inquiry form stores leads for the resolved store', function () {
    $this->post(route('storefront.inquiries.store'), [
        'name' => 'John Doe',
        'email' => 'john@example.com',
        'interest_area' => 'Electrotherapy Equipment',
        'message' => 'Need a quote.',
    ])->assertRedirect();

    $this->assertDatabaseHas('store_inquiries', [
        'store_id' => $this->store->id,
        'email' => 'john@example.com',
        'status' => 'new',
    ]);
});
