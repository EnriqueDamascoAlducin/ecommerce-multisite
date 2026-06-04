<?php

use App\Models\Product;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $this->admin = User::factory()->create();
    $this->admin->assignRole('Super Admin');
    $this->actingAs($this->admin);
});

test('a super admin can create a downloadable product with links', function () {
    $this->post(route('admin.products.store'), [
        'type' => 'downloadable',
        'sku' => 'EBOOK-1',
        'name' => 'Ebook de Laravel',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '149.00',
        'downloadable_links' => [
            ['title' => 'PDF completo', 'file_path' => 'files/ebook.pdf', 'original_name' => 'ebook.pdf', 'max_downloads' => 5],
        ],
    ])->assertRedirect(route('admin.products.index'));

    $product = Product::where('sku', 'EBOOK-1')->firstOrFail();

    expect($product->type)->toBe('downloadable');
    $this->assertDatabaseHas('downloadable_links', [
        'product_id' => $product->id,
        'title' => 'PDF completo',
        'file_path' => 'files/ebook.pdf',
        'max_downloads' => 5,
    ]);
    $this->assertDatabaseHas('product_prices', ['product_id' => $product->id, 'store_id' => null, 'price' => '149.00']);
});

test('a downloadable requires at least one file', function () {
    $this->post(route('admin.products.store'), [
        'type' => 'downloadable',
        'sku' => 'EBOOK-EMPTY',
        'name' => 'Sin archivos',
        'status' => 'active',
        'visibility' => 'both',
        'price' => '10.00',
    ])->assertSessionHasErrors('downloadable_links');
});

test('updating a downloadable replaces its links', function () {
    $product = Product::factory()->create(['type' => 'downloadable']);
    $product->prices()->create(['store_id' => null, 'price' => 100]);
    $old = $product->downloadableLinks()->create(['title' => 'Viejo', 'file_path' => 'files/old.pdf']);

    $this->put(route('admin.products.update', $product), [
        'sku' => $product->sku,
        'name' => $product->name,
        'status' => 'active',
        'visibility' => 'both',
        'price' => '100',
        'downloadable_links' => [
            ['title' => 'Nuevo', 'file_path' => 'files/new.pdf', 'original_name' => 'new.pdf'],
        ],
    ])->assertRedirect();

    $this->assertDatabaseMissing('downloadable_links', ['id' => $old->id]);
    $this->assertDatabaseHas('downloadable_links', ['product_id' => $product->id, 'title' => 'Nuevo', 'file_path' => 'files/new.pdf']);
});

test('the upload endpoint stores a file and returns its path', function () {
    Storage::fake('downloads');

    $response = $this->post(route('admin.downloadable.upload'), [
        'file' => UploadedFile::fake()->create('guia.pdf', 120, 'application/pdf'),
    ]);

    $response->assertOk()->assertJsonStructure(['file_path', 'original_name']);
    expect($response->json('original_name'))->toBe('guia.pdf');

    Storage::disk('downloads')->assertExists($response->json('file_path'));
});

test('the Soporte role cannot upload downloadable files', function () {
    $support = User::factory()->create();
    $support->assignRole('Soporte');

    $this->actingAs($support)
        ->post(route('admin.downloadable.upload'), [
            'file' => UploadedFile::fake()->create('guia.pdf', 10),
        ])->assertForbidden();
});
