<?php

use App\Domain\Catalog\ProductCsvImportService;
use App\Jobs\ProcessProductImport;
use App\Models\Attribute;
use App\Models\Category;
use App\Models\InventorySource;
use App\Models\Product;
use App\Models\ProductImport;
use App\Models\ProductPrice;
use App\Models\Store;
use App\Models\User;
use App\Models\Website;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    Storage::fake('local');
    Storage::fake('public');
    Queue::fake();
});

test('csv validation does not mutate products', function () {
    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload("sku,name,price\nIMP-001,Producto Importado,199.90\n"),
    ]);

    $response->assertRedirect(route('admin.products.import.create'));
    $response->assertSessionHas('product_import_result');

    $result = $response->baseResponse->getSession()->get('product_import_result');

    expect($result['summary']['valid_rows'])->toBe(1)
        ->and($result['summary']['creates'])->toBe(1);

    $this->assertDatabaseMissing('products', ['sku' => 'IMP-001']);
});

test('confirming an import queues background processing', function () {
    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload("sku,name,price\nIMP-ASYNC,Producto Async,99\n"),
    ]);

    $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    $import = ProductImport::query()->latest()->firstOrFail();

    expect($import->status)->toBe(ProductImport::STATUS_PENDING)
        ->and($import->total_products)->toBe(1);
    $this->assertDatabaseMissing('products', ['sku' => 'IMP-ASYNC']);
    Queue::assertPushed(
        ProcessProductImport::class,
        fn (ProcessProductImport $job): bool => $job->productImportId === $import->id,
    );
});
test('a valid csv can create a simple product', function () {
    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload("sku,name,price,status,visibility\nIMP-002,Producto Nuevo,88.50,active,both\n"),
    ]);

    $token = $response->baseResponse->getSession()->get('product_import_token');

    $this->post(route('admin.products.import.confirm'), ['token' => $token])
        ->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $product = Product::where('sku', 'IMP-002')->firstOrFail();

    expect($product->name)->toBe('Producto Nuevo')
        ->and($product->status)->toBe(Product::STATUS_ACTIVE)
        ->and($product->visibility)->toBe('both');

    $this->assertDatabaseHas('product_prices', [
        'product_id' => $product->id,
        'store_id' => null,
        'price' => '88.50',
    ]);
});

test('csv import updates an existing product by sku', function () {
    $product = Product::factory()->create([
        'sku' => 'IMP-UPD',
        'name' => 'Nombre anterior',
        'status' => Product::STATUS_ACTIVE,
    ]);

    ProductPrice::create([
        'product_id' => $product->id,
        'store_id' => null,
        'price' => '10.00',
    ]);

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload("sku,name,price,status,visibility\nIMP-UPD,Nombre nuevo,25.00,inactive,search\n"),
    ]);

    $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    expect($product->fresh()->name)->toBe('Nombre nuevo')
        ->and($product->fresh()->status)->toBe(Product::STATUS_INACTIVE)
        ->and($product->fresh()->visibility)->toBe('search');

    $this->assertDatabaseHas('product_prices', [
        'product_id' => $product->id,
        'store_id' => null,
        'price' => '25.00',
    ]);
});

test('csv validation reports missing store category and attribute references', function () {
    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload("sku,name,price,store_codes,category_ids,attribute:missing\nIMP-BAD,Producto,10,NOPE,999,valor\n"),
    ]);

    $result = $response->baseResponse->getSession()->get('product_import_result');

    expect($result['summary']['error_rows'])->toBe(1)
        ->and($result['rows'][0]['errors'])->toContain('La tienda NOPE no existe.')
        ->and($result['rows'][0]['errors'])->toContain('La categoria 999 no existe.')
        ->and($result['rows'][0]['errors'])->toContain('El atributo missing no existe.');
});

test('csv import applies stock to the selected inventory source', function () {
    $source = InventorySource::factory()->create(['code' => 'main']);

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload("sku,name,price,stock_qty,inventory_source_code\nIMP-STOCK,Producto Stock,15,7,main\n"),
    ]);

    $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $product = Product::where('sku', 'IMP-STOCK')->firstOrFail();

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'inventory_source_id' => $source->id,
        'physical_qty' => 7,
    ]);
});

test('csv validation rejects unsupported product types', function (string $type) {
    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload("sku,name,price,product_type\nIMP-{$type},Producto,10,{$type}\n"),
    ]);

    $result = $response->baseResponse->getSession()->get('product_import_result');

    expect($result['summary']['error_rows'])->toBe(1)
        ->and($result['rows'][0]['errors'])->toContain('Solo se soportan productos simple en esta version.');
})->with(['configurable', 'bundle', 'downloadable']);

test('csv import can assign existing stores categories and attributes', function () {
    $store = Store::factory()->create(['code' => 'mx']);
    $category = Category::factory()->create();
    $attribute = Attribute::factory()->select()->create(['code' => 'color']);
    $attribute->options()->create(['label' => 'Rojo', 'value' => 'red', 'sort_order' => 0]);

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload("sku,name,price,store_codes,category_ids,attribute:color,store_price:mx\nIMP-REL,Producto Relacionado,50,mx,{$category->id},Rojo,45\n"),
    ]);

    $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $product = Product::where('sku', 'IMP-REL')->firstOrFail();

    $this->assertDatabaseHas('product_stores', [
        'product_id' => $product->id,
        'store_id' => $store->id,
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('category_product', [
        'product_id' => $product->id,
        'category_id' => $category->id,
    ]);
    $this->assertDatabaseHas('product_attribute_values', [
        'product_id' => $product->id,
        'attribute_id' => $attribute->id,
        'value' => 'red',
    ]);
    $this->assertDatabaseHas('product_prices', [
        'product_id' => $product->id,
        'store_id' => $store->id,
        'price' => '45.00',
    ]);
});

test('magento csv imports only base simple products', function () {
    $website = Website::factory()->create(['name' => 'Interferenciales']);
    $store = Store::factory()->create([
        'website_id' => $website->id,
        'code' => 'main',
        'name' => 'Interferenciales',
        'is_default' => true,
    ]);

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload(implode("\n", [
            magentoHeader(),
            magentoRow('MAG-001', '', 'simple', 'Producto Magento', '1', 'Catalog, Search', '100.500000', '90.000000', '3/4/24', '12/1/25', '5.0000', 'Default Inte/Catálogo/Inexistente'),
            magentoRow('MAG-001', 'view_inte', 'simple', '', '', '', '', '', '', '', '', ''),
            magentoRow('MAG-CONF', '', 'configurable', 'Configurable', '1', 'Catalog, Search', '', '', '', '', '0.0000', ''),
        ])."\n"),
    ]);

    $result = $response->baseResponse->getSession()->get('product_import_result');

    expect($result['summary']['total_rows'])->toBe(3)
        ->and($result['summary']['valid_rows'])->toBe(1)
        ->and($result['summary']['skipped_rows'])->toBe(1)
        ->and($result['summary']['assigned_store_views'])->toBe(1)
        ->and($result['summary']['omitted_store_views'])->toBe(0)
        ->and($result['summary']['omitted_unsupported_types'])->toBe(1)
        ->and($result['summary']['missing_categories'])->toBe(1)
        ->and($result['rows'][0]['warnings'])->toContain('1 store view(s) asociada(s) a tiendas.')
        ->and($result['rows'][0]['warnings'])->toContain('1 categoria(s) no encontrada(s); no se asignaron.');

    $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $product = Product::where('sku', 'MAG-001')->firstOrFail();

    expect($product->status)->toBe(Product::STATUS_ACTIVE)
        ->and($product->visibility)->toBe('both');

    $this->assertDatabaseHas('product_prices', [
        'product_id' => $product->id,
        'store_id' => null,
        'price' => '100.50',
        'special_price' => '90.00',
        'special_price_from' => '2024-03-04 00:00:00',
        'special_price_to' => '2025-12-01 00:00:00',
    ]);
    $this->assertDatabaseHas('product_stores', [
        'product_id' => $product->id,
        'store_id' => $store->id,
        'is_active' => true,
    ]);
    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'physical_qty' => 5,
    ]);
});

test('magento csv can assign an existing category path without creating missing categories', function () {
    $website = Website::factory()->create(['name' => 'Interferenciales']);
    Store::factory()->create([
        'website_id' => $website->id,
        'code' => 'main',
        'name' => 'Interferenciales',
        'is_default' => true,
    ]);
    $root = Category::factory()->create([
        'website_id' => $website->id,
        'store_id' => null,
        'parent_id' => null,
        'name' => 'Default Inte',
        'slug' => 'default-inte',
    ]);
    $catalog = Category::factory()->create([
        'website_id' => $website->id,
        'store_id' => null,
        'parent_id' => $root->id,
        'name' => 'Catálogo',
        'slug' => 'catalogo',
    ]);

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload(implode("\n", [
            magentoHeader(),
            magentoRow('MAG-CAT', '', 'simple', 'Producto Categoria', '1', 'Catalog', '50', '', '', '', '2.0000', 'Default Inte/Catálogo,Default Inte/No Existe'),
        ])."\n"),
    ]);

    $result = $response->baseResponse->getSession()->get('product_import_result');

    expect($result['summary']['valid_rows'])->toBe(1)
        ->and($result['summary']['missing_categories'])->toBe(1);

    $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $product = Product::where('sku', 'MAG-CAT')->firstOrFail();

    expect($product->visibility)->toBe('catalog');
    $this->assertDatabaseHas('category_product', [
        'product_id' => $product->id,
        'category_id' => $catalog->id,
    ]);
    $this->assertDatabaseMissing('categories', [
        'name' => 'No Existe',
    ]);
});

test('magento csv maps known store views to product stores', function () {
    [$interferenciales, $sports, $veterinaria] = createMagentoStores();

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload(implode("\n", [
            magentoHeader(),
            magentoRow('MAG-STORES', '', 'simple', 'Producto Multitienda', '1', 'Catalog, Search', '75', '', '', '', '4.0000', ''),
            magentoRow('MAG-STORES', 'view_inte', 'simple', '', '', '', '', '', '', '', '', ''),
            magentoRow('MAG-STORES', 'view_soph', 'simple', '', '', '', '', '', '', '', '', ''),
            magentoRow('MAG-STORES', 'view_reah', 'simple', '', '', '', '', '', '', '', '', ''),
        ])."\n"),
    ]);

    $result = $response->baseResponse->getSession()->get('product_import_result');

    expect($result['summary']['valid_rows'])->toBe(1)
        ->and($result['summary']['assigned_store_views'])->toBe(3)
        ->and($result['summary']['omitted_store_views'])->toBe(0)
        ->and($result['rows'][0]['warnings'])->toContain('3 store view(s) asociada(s) a tiendas.');

    $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $product = Product::where('sku', 'MAG-STORES')->firstOrFail();

    foreach ([$interferenciales, $sports, $veterinaria] as $store) {
        $this->assertDatabaseHas('product_stores', [
            'product_id' => $product->id,
            'store_id' => $store->id,
            'is_active' => true,
        ]);
    }
});

test('magento csv omits unknown store views without failing the base product', function () {
    createMagentoStores();

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload(implode("\n", [
            magentoHeader(),
            magentoRow('MAG-STIM', '', 'simple', 'Producto Stim', '1', 'Catalog, Search', '75', '', '', '', '4.0000', ''),
            magentoRow('MAG-STIM', 'view_stim', 'simple', '', '', '', '', '', '', '', '', ''),
        ])."\n"),
    ]);

    $result = $response->baseResponse->getSession()->get('product_import_result');

    expect($result['summary']['valid_rows'])->toBe(1)
        ->and($result['summary']['assigned_store_views'])->toBe(0)
        ->and($result['summary']['omitted_store_views'])->toBe(1)
        ->and($result['summary']['skipped_rows'])->toBe(1)
        ->and($result['rows'][0]['warnings'])->toContain('1 store view(s) sin match omitida(s).')
        ->and($result['summary']['error_rows'])->toBe(0);

    $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $product = Product::where('sku', 'MAG-STIM')->firstOrFail();

    expect($product->storeLinks()->count())->toBe(1);
});

test('magento store views without a simple base product are omitted', function () {
    createMagentoStores();

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload(implode("\n", [
            magentoHeader(),
            magentoRow('MAG-ONLY-VIEW', 'view_soph', 'simple', '', '', '', '', '', '', '', '', ''),
        ])."\n"),
    ]);

    $result = $response->baseResponse->getSession()->get('product_import_result');

    expect($result['summary']['valid_rows'])->toBe(0)
        ->and($result['summary']['skipped_rows'])->toBe(1)
        ->and($result['summary']['omitted_store_views'])->toBe(1);

    $this->assertDatabaseMissing('products', ['sku' => 'MAG-ONLY-VIEW']);
});

test('magento csv downloads and attaches base and additional images', function () {
    app()->detectEnvironment(fn (): string => 'local');
    createMagentoStores();
    Http::preventStrayRequests();
    Http::fake([
        'https://interferenciales.com.mx/media/catalog/product/*' => Http::response(
            'fake-jpeg-contents',
            200,
            ['Content-Type' => 'image/jpeg'],
        ),
    ]);

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload(implode("\n", [
            magentoHeader(),
            magentoRow(
                'MAG-IMG',
                '',
                'simple',
                'Producto Con Imagenes',
                '1',
                'Catalog, Search',
                '125',
                '',
                '',
                '',
                '3.0000',
                '',
                '/p/r/principal.jpg',
                'Imagen principal',
                '/a/d/adicional-1.jpg',
                'Imagen adicional',
            ),
        ])."\n"),
    ]);

    $preview = $response->baseResponse->getSession()->get('product_import_result');

    expect($preview['summary']['images_detected'])->toBe(2)
        ->and($preview['summary']['images_downloaded'])->toBe(0);

    $confirm = $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $result = ProductImport::query()->latest()->firstOrFail()->result;
    $product = Product::where('sku', 'MAG-IMG')->firstOrFail();
    $media = $product->media()->wherePivot('collection', 'gallery')->get();

    expect($result['summary']['images_downloaded'])->toBe(2)
        ->and($result['summary']['images_failed'])->toBe(0)
        ->and($media)->toHaveCount(2)
        ->and($media->where('pivot.is_primary', true)->first()?->name)->toBe('principal.jpg');

    foreach ($media as $item) {
        Storage::disk('public')->assertExists($item->path);
    }

    Http::assertSentCount(2);
});

test('image download failures do not block magento product import', function () {
    createMagentoStores();
    Http::preventStrayRequests();
    Http::fake([
        'https://interferenciales.com.mx/media/catalog/product/*' => Http::response('missing', 404),
    ]);

    $response = $this->post(route('admin.products.import.validate'), [
        'file' => csvUpload(implode("\n", [
            magentoHeader(),
            magentoRow(
                'MAG-IMG-FAIL',
                '',
                'simple',
                'Producto Sin Imagen Remota',
                '1',
                'Catalog, Search',
                '50',
                '',
                '',
                '',
                '1.0000',
                '',
                '/m/i/missing.jpg',
            ),
        ])."\n"),
    ]);

    $confirm = $this->post(route('admin.products.import.confirm'), [
        'token' => $response->baseResponse->getSession()->get('product_import_token'),
    ])->assertRedirect(route('admin.products.import.create'));

    runLatestProductImport();

    $result = ProductImport::query()->latest()->firstOrFail()->result;

    $this->assertDatabaseHas('products', ['sku' => 'MAG-IMG-FAIL']);
    expect($result['summary']['images_downloaded'])->toBe(0)
        ->and($result['summary']['images_failed'])->toBe(1)
        ->and($result['rows'][0]['warnings'])->toContain('No se pudo descargar /m/i/missing.jpg.');
});
function runLatestProductImport(): ProductImport
{
    $import = ProductImport::query()->latest()->firstOrFail();

    Queue::assertPushed(
        ProcessProductImport::class,
        fn (ProcessProductImport $job): bool => $job->productImportId === $import->id,
    );

    (new ProcessProductImport($import->id))->handle(app(ProductCsvImportService::class));

    return $import->fresh();
}
function csvUpload(string $contents): UploadedFile
{
    return UploadedFile::fake()->createWithContent('products.csv', $contents);
}

/**
 * @return array{Store, Store, Store}
 */
function createMagentoStores(): array
{
    $interferencialesWebsite = Website::factory()->create(['name' => 'Interferenciales']);
    $veterinariaWebsite = Website::factory()->create(['name' => 'Veterinaria']);

    return [
        Store::factory()->create([
            'website_id' => $interferencialesWebsite->id,
            'code' => 'main',
            'name' => 'Interferenciales',
            'is_default' => true,
        ]),
        Store::factory()->create([
            'website_id' => $interferencialesWebsite->id,
            'code' => 'sports',
            'name' => 'Interferenciales Sports',
        ]),
        Store::factory()->create([
            'website_id' => $veterinariaWebsite->id,
            'code' => 'main',
            'name' => 'Veterinaria',
            'is_default' => true,
        ]),
    ];
}

function magentoHeader(): string
{
    return 'sku,store_view_code,product_type,name,product_online,visibility,price,special_price,special_price_from_date,special_price_to_date,qty,categories,base_image,base_image_label,additional_images,additional_image_labels';
}

function magentoRow(
    string $sku,
    string $storeView,
    string $type,
    string $name,
    string $online,
    string $visibility,
    string $price,
    string $specialPrice,
    string $specialFrom,
    string $specialTo,
    string $qty,
    string $categories,
    string $baseImage = '',
    string $baseImageLabel = '',
    string $additionalImages = '',
    string $additionalImageLabels = '',
): string {
    return implode(',', array_map(fn (string $value) => '"'.str_replace('"', '""', $value).'"', [
        $sku,
        $storeView,
        $type,
        $name,
        $online,
        $visibility,
        $price,
        $specialPrice,
        $specialFrom,
        $specialTo,
        $qty,
        $categories,
        $baseImage,
        $baseImageLabel,
        $additionalImages,
        $additionalImageLabels,
    ]));
}
