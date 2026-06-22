<?php

use App\Domain\Inventory\StockCsvImportService;
use App\Models\InventorySource;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    $this->source = InventorySource::factory()->default()->create();
});

test('a super admin can list inventory', function () {
    Product::factory()->create();

    $this->get(route('admin.inventory.index'))->assertOk();
});

test('a super admin can adjust product stock', function () {
    $product = Product::factory()->create();

    $this->put(route('admin.inventory.update', $product), [
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 50,
        'manage_stock' => '1',
        'low_stock_threshold' => 5,
        'reason' => 'Carga inicial',
    ])->assertRedirect(route('admin.inventory.edit', $product));

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 50,
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $product->id,
        'type' => 'adjustment',
        'balance_after' => 50,
    ]);
});

test('the inventory edit page loads with sources and movements', function () {
    $product = Product::factory()->create();

    $this->get(route('admin.inventory.edit', $product))->assertOk();
});

test('a super admin can create an inventory source', function () {
    $this->post(route('admin.inventory-sources.store'), [
        'code' => 'bodega_norte',
        'name' => 'Bodega Norte',
        'is_active' => '1',
    ])->assertRedirect(route('admin.inventory-sources.index'));

    $this->assertDatabaseHas('inventory_sources', ['code' => 'bodega_norte']);
});

test('creating a default source unsets other defaults', function () {
    $this->post(route('admin.inventory-sources.store'), [
        'code' => 'nuevo_default',
        'name' => 'Nuevo Default',
        'is_default' => '1',
    ])->assertRedirect();

    expect(InventorySource::where('is_default', true)->count())->toBe(1);
    expect(InventorySource::where('code', 'nuevo_default')->value('is_default'))->toBeTrue();
});

test('the default source cannot be deleted', function () {
    $this->delete(route('admin.inventory-sources.destroy', $this->source))
        ->assertRedirect();

    $this->assertDatabaseHas('inventory_sources', ['id' => $this->source->id]);
});

test('a user without inventory permission is forbidden', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.inventory.index'))->assertForbidden();
});

test('a viewer cannot adjust stock', function () {
    $viewer = User::factory()->create();
    $viewer->assignRole('Solo lectura');
    $this->actingAs($viewer);

    $product = Product::factory()->create();

    $this->put(route('admin.inventory.update', $product), [
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 10,
    ])->assertForbidden();
});

test('stock csv preview does not mutate stock', function () {
    $product = Product::factory()->create(['sku' => 'CSV-1']);
    $path = stockCsvPath([
        ['source_code' => 'inter', 'sku' => $product->sku, 'status' => '1', 'quantity' => '25.0000'],
    ]);

    $result = app(StockCsvImportService::class)->preview($path);

    expect($result['summary']['valid_rows'])->toBe(1)
        ->and($result['summary']['creates'])->toBe(1)
        ->and($result['rows'][0]['target_source_code'])->toBe('default');

    $this->assertDatabaseMissing('inventory_stocks', [
        'product_id' => $product->id,
        'inventory_source_id' => $this->source->id,
    ]);
});

test('stock csv import creates and updates stock with movements', function () {
    $newProduct = Product::factory()->create(['sku' => 'CSV-NEW']);
    $existingProduct = Product::factory()->create(['sku' => 'CSV-OLD']);
    $existingProduct->inventoryStocks()->create([
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 5,
        'reserved_qty' => 2,
        'manage_stock' => true,
        'allow_backorders' => true,
        'low_stock_threshold' => 3,
    ]);

    $path = stockCsvPath([
        ['source_code' => 'inter', 'sku' => $newProduct->sku, 'status' => '1', 'quantity' => '10.0000'],
        ['source_code' => 'inter', 'sku' => $existingProduct->sku, 'status' => '0', 'quantity' => '8.0000'],
    ]);

    $result = app(StockCsvImportService::class)->apply($path, auth()->user());

    expect($result['summary']['applied'])->toBe(2);

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $newProduct->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 10,
    ]);
    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $existingProduct->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 8,
        'reserved_qty' => 2,
        'allow_backorders' => true,
        'low_stock_threshold' => 3,
    ]);
    $this->assertDatabaseHas('stock_movements', [
        'product_id' => $existingProduct->id,
        'type' => StockMovement::TYPE_ADJUSTMENT,
        'quantity' => 3,
        'balance_after' => 8,
    ]);
});

test('stock csv reports missing sku unknown source fractional quantity and duplicate rows', function () {
    $product = Product::factory()->create(['sku' => 'CSV-DUP']);
    $path = stockCsvPath([
        ['source_code' => 'inter', 'sku' => $product->sku, 'status' => '1', 'quantity' => '4.0000'],
        ['source_code' => 'inter', 'sku' => $product->sku, 'status' => '1', 'quantity' => '5.0000'],
        ['source_code' => 'other', 'sku' => 'NO-SKU', 'status' => '1', 'quantity' => '1.0000'],
        ['source_code' => 'inter', 'sku' => $product->sku.'-F', 'status' => '1', 'quantity' => '1.5000'],
    ]);

    $result = app(StockCsvImportService::class)->preview($path);

    expect($result['summary']['valid_rows'])->toBe(1)
        ->and($result['summary']['error_rows'])->toBe(3)
        ->and(collect($result['rows'])->pluck('errors')->flatten()->all())->toContain(
            'SKU duplicado para la misma fuente.',
            'Producto no encontrado.',
            'Fuente other no existe.',
            'Cantidad debe ser entero mayor o igual a 0.',
        );
});

test('a super admin can validate and confirm stock import', function () {
    $product = Product::factory()->create(['sku' => 'CSV-WEB']);
    $content = stockCsvContent([
        ['source_code' => 'inter', 'sku' => $product->sku, 'status' => '1', 'quantity' => '12.0000'],
    ]);

    $this->post(route('admin.inventory.import.validate'), [
        'file' => UploadedFile::fake()->createWithContent('stock.csv', $content),
    ])->assertRedirect(route('admin.inventory.import.create'));

    $token = session('stock_import_token');
    expect($token)->toBeString();

    $this->post(route('admin.inventory.import.confirm'), ['token' => $token])
        ->assertRedirect(route('admin.inventory.import.create'));

    $this->assertDatabaseHas('inventory_stocks', [
        'product_id' => $product->id,
        'inventory_source_id' => $this->source->id,
        'physical_qty' => 12,
    ]);
});

/**
 * @param  list<array{source_code: string, sku: string, status: string, quantity: string}>  $rows
 */
function stockCsvPath(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'stock-import-');
    file_put_contents($path, stockCsvContent($rows));

    return $path;
}

/**
 * @param  list<array{source_code: string, sku: string, status: string, quantity: string}>  $rows
 */
function stockCsvContent(array $rows): string
{
    $lines = ['source_code,sku,status,quantity'];

    foreach ($rows as $row) {
        $lines[] = implode(',', [$row['source_code'], $row['sku'], $row['status'], $row['quantity']]);
    }

    return implode("\n", $lines)."\n";
}
