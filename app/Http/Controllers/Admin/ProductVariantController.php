<?php

namespace App\Http\Controllers\Admin;

use App\Domain\Catalog\ConfigurableProductService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AttachProductVariantRequest;
use App\Models\Product;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductVariantController extends Controller
{
    public function __construct(
        private readonly ConfigurableProductService $configurable,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function attach(AttachProductVariantRequest $request, Product $product): RedirectResponse
    {
        $child = Product::findOrFail((int) $request->validated()['product_id']);

        DB::transaction(fn () => $this->configurable->attachExistingVariant($product, $child));

        $this->auditLogger->log('product.variant.attached', $product, "Variante {$child->sku} vinculada a {$product->sku}");

        return to_route('admin.products.edit', $product)->with('success', 'Variante vinculada.');
    }

    public function detach(Product $product, Product $variant): RedirectResponse
    {
        if ($variant->parent_id !== $product->id) {
            throw new NotFoundHttpException('La variante no pertenece a este producto.');
        }

        DB::transaction(fn () => $this->configurable->detachVariant($variant));

        $this->auditLogger->log('product.variant.detached', $product, "Variante {$variant->sku} desvinculada de {$product->sku}");

        return to_route('admin.products.edit', $product)->with('success', 'Variante desvinculada.');
    }
}
