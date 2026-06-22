<?php

use App\Domain\Storefront\StorefrontHomeTemplate;
use App\Models\StorefrontPageSection;

test('home template does not include legacy product builder sections', function () {
    expect(collect(StorefrontHomeTemplate::sections())->pluck('type')->all())
        ->toContain(StorefrontPageSection::TYPE_HERO)
        ->not->toContain('featured_products');
});
