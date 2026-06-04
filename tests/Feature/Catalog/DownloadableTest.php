<?php

use App\Domain\Catalog\DownloadGrantService;
use App\Models\Customer;
use App\Models\CustomerDownloadGrant;
use App\Models\DownloadableLink;
use App\Models\InventorySource;
use App\Models\Order;
use App\Models\Product;
use App\Models\Store;
use App\Models\Website;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    config()->set('payments.mercadopago.access_token', 'TEST-token');

    $this->website = Website::factory()->create(['is_default' => true, 'code' => 'demo']);
    $this->store = Store::factory()->create([
        'website_id' => $this->website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
    $this->source = InventorySource::factory()->default()->create();
});

function downloadableProduct(Store $store, float $price = 199): Product
{
    $product = Product::factory()->create([
        'type' => Product::TYPE_DOWNLOADABLE,
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);

    $product->prices()->create(['store_id' => null, 'price' => $price]);
    $product->storeLinks()->create(['store_id' => $store->id, 'is_active' => true]);
    $product->downloadableLinks()->create([
        'title' => 'Manual PDF',
        'file_path' => 'files/manual.pdf',
        'original_name' => 'manual.pdf',
    ]);

    return $product;
}

test('grant service creates a grant per downloadable link of a paid order', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);
    $product = downloadableProduct($this->store);

    $order = Order::factory()->create([
        'website_id' => $this->website->id,
        'store_id' => $this->store->id,
        'customer_id' => $customer->id,
        'status' => Order::STATUS_PAID,
    ]);
    $order->items()->create([
        'product_id' => $product->id,
        'sku' => $product->sku,
        'name' => $product->name,
        'quantity' => 1,
        'unit_price' => '199.00',
        'line_total' => '199.00',
    ]);

    app(DownloadGrantService::class)->grantForOrder($order);

    $this->assertDatabaseHas('customer_download_grants', [
        'order_id' => $order->id,
        'customer_id' => $customer->id,
        'product_id' => $product->id,
        'title' => 'Manual PDF',
    ]);
});

test('granting is idempotent across repeated calls', function () {
    $product = downloadableProduct($this->store);
    $order = Order::factory()->create(['store_id' => $this->store->id, 'status' => Order::STATUS_PAID]);
    $order->items()->create([
        'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
        'quantity' => 1, 'unit_price' => '199.00', 'line_total' => '199.00',
    ]);

    $service = app(DownloadGrantService::class);
    $service->grantForOrder($order);
    $service->grantForOrder($order);

    expect(CustomerDownloadGrant::where('order_id', $order->id)->count())->toBe(1);
});

test('an approved payment grants downloads through the payment service', function () {
    $product = downloadableProduct($this->store);
    $order = Order::factory()->create([
        'website_id' => $this->website->id,
        'store_id' => $this->store->id,
        'status' => Order::STATUS_PENDING_PAYMENT,
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'sku' => $product->sku, 'name' => $product->name,
        'quantity' => 1, 'unit_price' => '199.00', 'line_total' => '199.00',
    ]);

    Http::fake([
        '*/v1/payments/*' => Http::response(['id' => 'PAY-DL', 'status' => 'approved', 'external_reference' => (string) $order->id]),
    ]);

    $this->postJson(route('webhooks.payments', ['gateway' => 'mercadopago']), [
        'type' => 'payment', 'data' => ['id' => 'PAY-DL'],
    ])->assertOk();

    expect($order->fresh()->status)->toBe(Order::STATUS_PAID);
    $this->assertDatabaseHas('customer_download_grants', ['order_id' => $order->id, 'downloadable_link_id' => $product->downloadableLinks->first()->id]);
});

test('a customer can download a granted file and the counter increments', function () {
    Storage::fake('downloads');
    Storage::disk('downloads')->put('files/manual.pdf', 'contenido');

    $customer = Customer::factory()->create(['website_id' => $this->website->id]);
    $link = DownloadableLink::factory()->create(['file_path' => 'files/manual.pdf', 'original_name' => 'manual.pdf']);
    $grant = CustomerDownloadGrant::factory()->create([
        'customer_id' => $customer->id,
        'downloadable_link_id' => $link->id,
        'max_downloads' => 3,
        'downloads_used' => 0,
    ]);

    $this->actingAs($customer, 'customer')
        ->get(route('customer.downloads.file', $grant))
        ->assertOk();

    expect($grant->fresh()->downloads_used)->toBe(1);
});

test('a download is blocked once the limit is reached', function () {
    Storage::fake('downloads');
    Storage::disk('downloads')->put('files/manual.pdf', 'contenido');

    $customer = Customer::factory()->create(['website_id' => $this->website->id]);
    $link = DownloadableLink::factory()->create(['file_path' => 'files/manual.pdf']);
    $grant = CustomerDownloadGrant::factory()->create([
        'customer_id' => $customer->id,
        'downloadable_link_id' => $link->id,
        'max_downloads' => 1,
        'downloads_used' => 1,
    ]);

    $this->actingAs($customer, 'customer')
        ->get(route('customer.downloads.file', $grant))
        ->assertForbidden();
});

test('a customer cannot download another customer grant', function () {
    Storage::fake('downloads');
    Storage::disk('downloads')->put('files/manual.pdf', 'contenido');

    $owner = Customer::factory()->create(['website_id' => $this->website->id]);
    $intruder = Customer::factory()->create(['website_id' => $this->website->id]);
    $link = DownloadableLink::factory()->create(['file_path' => 'files/manual.pdf']);
    $grant = CustomerDownloadGrant::factory()->create([
        'customer_id' => $owner->id,
        'downloadable_link_id' => $link->id,
    ]);

    $this->actingAs($intruder, 'customer')
        ->get(route('customer.downloads.file', $grant))
        ->assertForbidden();
});

test('the downloads page lists the customer grants', function () {
    $customer = Customer::factory()->create(['website_id' => $this->website->id]);
    CustomerDownloadGrant::factory()->create(['customer_id' => $customer->id, 'title' => 'Mi ebook']);

    $this->actingAs($customer, 'customer')
        ->get(route('customer.downloads.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('storefront/account/downloads')
            ->has('downloads', 1)
            ->where('downloads.0.title', 'Mi ebook'));
});

test('a downloadable without files cannot be added to the cart', function () {
    $product = Product::factory()->create([
        'type' => Product::TYPE_DOWNLOADABLE,
        'status' => Product::STATUS_ACTIVE,
        'visibility' => 'both',
    ]);
    $product->prices()->create(['store_id' => null, 'price' => 99]);
    $product->storeLinks()->create(['store_id' => $this->store->id, 'is_active' => true]);

    $this->post(route('cart.store'), ['product_id' => $product->id])
        ->assertSessionHas('error');

    $this->assertDatabaseMissing('cart_items', ['product_id' => $product->id]);
});

test('a downloadable product can be added to the cart and has no stock limit', function () {
    $product = downloadableProduct($this->store);

    $this->post(route('cart.store'), ['product_id' => $product->id, 'quantity' => 5])
        ->assertRedirect()
        ->assertSessionHas('success');

    $this->assertDatabaseHas('cart_items', ['product_id' => $product->id, 'quantity' => 5, 'unit_price' => '199.00']);
});
