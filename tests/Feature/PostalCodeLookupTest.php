<?php

use App\Models\PostalCodeSettlement;
use App\Models\Store;
use App\Models\Website;

beforeEach(function () {
    $website = Website::factory()->create(['is_default' => true, 'code' => 'demo']);
    Store::factory()->create([
        'website_id' => $website->id,
        'is_default' => true,
        'is_active' => true,
    ]);
});

test('postal code import creates settlements from sepomex csv', function () {
    $file = tempnam(sys_get_temp_dir(), 'sepomex');

    file_put_contents($file, implode("\n", [
        'd_codigo,d_asenta,d_tipo_asenta,D_mnpio,d_estado,d_ciudad,d_zona',
        '01000,San Ángel,Colonia,Álvaro Obregón,Ciudad de México,Ciudad de México,Urbano',
        '01000,Chimalistac,Colonia,Álvaro Obregón,Ciudad de México,Ciudad de México,Urbano',
    ]));

    $this->artisan('postal-codes:import', ['file' => $file])->assertSuccessful();

    $this->assertDatabaseHas('postal_code_settlements', [
        'postal_code' => '01000',
        'settlement' => 'San Ángel',
        'municipality' => 'Álvaro Obregón',
        'state' => 'Ciudad de México',
    ]);

    expect(PostalCodeSettlement::where('postal_code', '01000')->count())->toBe(2);
});

test('postal code endpoint returns state city and settlements', function () {
    PostalCodeSettlement::create([
        'postal_code' => '01000',
        'settlement' => 'San Ángel',
        'settlement_type' => 'Colonia',
        'municipality' => 'Álvaro Obregón',
        'state' => 'Ciudad de México',
        'city' => 'Ciudad de México',
        'zone' => 'Urbano',
    ]);

    PostalCodeSettlement::create([
        'postal_code' => '01000',
        'settlement' => 'Chimalistac',
        'settlement_type' => 'Colonia',
        'municipality' => 'Álvaro Obregón',
        'state' => 'Ciudad de México',
        'city' => 'Ciudad de México',
        'zone' => 'Urbano',
    ]);

    $this->getJson(route('checkout.postal-code.show', '01000'))
        ->assertOk()
        ->assertJsonPath('postal_code', '01000')
        ->assertJsonPath('state', 'Ciudad de México')
        ->assertJsonPath('city', 'Álvaro Obregón')
        ->assertJsonPath('settlements.0.name', 'Chimalistac')
        ->assertJsonPath('settlements.1.name', 'San Ángel');
});

test('postal code endpoint returns not found for unknown postal code', function () {
    $this->getJson(route('checkout.postal-code.show', '99999'))->assertNotFound();
});
