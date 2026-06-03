<?php

use App\Domain\Store\ScopedConfigService;
use App\Models\Store;
use App\Models\Website;

beforeEach(function () {
    $this->config = app(ScopedConfigService::class);
});

test('reads a global value', function () {
    $this->config->set('global', 0, 'currency', 'MXN');

    expect($this->config->get('currency'))->toBe('MXN');
});

test('returns the default when the key is missing', function () {
    expect($this->config->get('missing', 'fallback'))->toBe('fallback');
});

test('website value overrides global', function () {
    $website = Website::factory()->create();
    $this->config->set('global', 0, 'currency', 'MXN');
    $this->config->set('website', $website->id, 'currency', 'USD');

    expect($this->config->get('currency', null, $website))->toBe('USD');
});

test('store value overrides website and global', function () {
    $website = Website::factory()->create();
    $store = Store::factory()->for($website)->create();
    $this->config->set('global', 0, 'currency', 'MXN');
    $this->config->set('website', $website->id, 'currency', 'USD');
    $this->config->set('store', $store->id, 'currency', 'EUR');

    expect($this->config->get('currency', null, $website, $store))->toBe('EUR');
});

test('store inherits the website value when it has none', function () {
    $website = Website::factory()->create();
    $store = Store::factory()->for($website)->create();
    $this->config->set('website', $website->id, 'currency', 'USD');

    expect($this->config->get('currency', null, $website, $store))->toBe('USD');
});

test('setting a value twice updates it', function () {
    $this->config->set('global', 0, 'locale', 'es');
    $this->config->set('global', 0, 'locale', 'en');

    expect($this->config->get('locale'))->toBe('en');
    $this->assertDatabaseCount('store_configurations', 1);
});
