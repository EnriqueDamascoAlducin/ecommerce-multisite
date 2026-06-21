<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Store\StoreContext;
use App\Domain\Storefront\StorefrontSeoService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class SeoController extends Controller
{
    public function __construct(
        private readonly StoreContext $context,
        private readonly StorefrontSeoService $seo,
    ) {}

    public function sitemap(): Response
    {
        abort_unless($this->context->hasStore(), 404);

        return response($this->seo->sitemapXml($this->context->store()), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
        ]);
    }

    public function robots(): Response
    {
        abort_unless($this->context->hasStore(), 404);

        return response($this->seo->robotsText($this->context->store()), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
        ]);
    }
}
