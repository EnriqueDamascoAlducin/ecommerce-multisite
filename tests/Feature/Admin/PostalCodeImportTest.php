<?php

use App\Models\PostalCodeSettlement;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->seed(RolesAndPermissionsSeeder::class);

    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');
    $this->actingAs($admin);

    Storage::fake('local');
});

test('an admin can open the postal code import page', function () {
    $this->get(route('admin.postal-codes.import.create'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/postal-codes/import')
            ->where('result', null));
});

test('an admin can import sepomex postal codes', function () {
    $csv = implode("\n", [
        'd_codigo,d_asenta,d_tipo_asenta,D_mnpio,d_estado,d_ciudad,d_zona',
        '01000,San Ángel,Colonia,Álvaro Obregón,Ciudad de México,Ciudad de México,Urbano',
        '01000,Chimalistac,Colonia,Álvaro Obregón,Ciudad de México,Ciudad de México,Urbano',
    ]);

    $this->post(route('admin.postal-codes.import.store'), [
        'file' => UploadedFile::fake()->createWithContent('sepomex.csv', $csv),
    ])->assertRedirect(route('admin.postal-codes.import.create'))
        ->assertSessionHas('postal_code_import_result');

    expect(PostalCodeSettlement::query()->where('postal_code', '01000')->count())->toBe(2);
    $this->assertDatabaseHas('postal_code_settlements', [
        'postal_code' => '01000',
        'settlement' => 'San Ángel',
        'municipality' => 'Álvaro Obregón',
        'state' => 'Ciudad de México',
    ]);
    $this->assertDatabaseHas('audit_logs', ['action' => 'postal_codes.imported']);
});

test('the postal code import requires store settings permission', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('admin.postal-codes.import.create'))->assertForbidden();
});
