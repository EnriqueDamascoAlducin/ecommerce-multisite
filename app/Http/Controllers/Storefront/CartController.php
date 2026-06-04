<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\CartException;
use App\Domain\Cart\CartService;
use App\Domain\Cart\CartTotalsCalculator;
use App\Domain\Inventory\InsufficientStockException;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Domain\Shipping\ShippingService;
use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class CartController extends Controller
{
    public function __construct(
        private readonly CartService $cart,
        private readonly CartTotalsCalculator $totals,
        private readonly StockAvailabilityChecker $availability,
        private readonly ShippingService $shipping,
    ) {}

    public function index(): Response
    {
        $cart = $this->cart->current();

        if (! $cart) {
            throw new NotFoundHttpException('Tienda no resuelta.');
        }

        return Inertia::render('storefront/cart', [
            'items' => $cart->items->map(fn (CartItem $item) => [
                'id' => $item->id,
                'product_slug' => $item->product?->slug,
                'sku' => $item->sku,
                'name' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => (string) $item->unit_price,
                'line_total' => $item->line_total,
                'thumbnail' => $item->product?->primaryMedia('gallery')?->url,
                'in_stock' => $item->product ? $this->availability->canFulfill($item->product, $item->quantity) : false,
            ])->values(),
            'totals' => $this->totals->totals($cart),
            'currency' => $cart->currency,
            'shippingOptions' => $this->shipping->optionsForCart($cart),
            'selectedShipping' => $cart->shipping_method_code,
            'coupon' => $cart->coupon_code,
        ]);
    }

    public function applyCoupon(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:50']]);

        try {
            $this->cart->applyCoupon(trim($data['code']));
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Cupón aplicado.');
    }

    public function removeCoupon(): RedirectResponse
    {
        $this->cart->removeCoupon();

        return back()->with('success', 'Cupón eliminado.');
    }

    public function shipping(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'shipping_method_code' => ['nullable', 'string'],
        ]);

        try {
            $this->cart->setShippingMethod($data['shipping_method_code'] ?? null);
        } catch (CartException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Método de envío actualizado.');
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'integer', 'exists:products,id'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:999'],
        ]);

        $product = Product::findOrFail($data['product_id']);

        try {
            $this->cart->addProduct($product, $data['quantity'] ?? 1);
        } catch (CartException|InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Producto agregado al carrito.');
    }

    public function update(Request $request, CartItem $item): RedirectResponse
    {
        $this->authorizeItem($item);

        $data = $request->validate([
            'quantity' => ['required', 'integer', 'min:0', 'max:999'],
        ]);

        try {
            $this->cart->updateQuantity($item, $data['quantity']);
        } catch (InsufficientStockException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Carrito actualizado.');
    }

    public function destroy(CartItem $item): RedirectResponse
    {
        $this->authorizeItem($item);

        $this->cart->removeItem($item);

        return back()->with('success', 'Producto eliminado del carrito.');
    }

    /**
     * El ítem debe pertenecer al carrito activo del visitante actual.
     */
    private function authorizeItem(CartItem $item): void
    {
        $cart = $this->cart->current(false);

        abort_unless($cart instanceof Cart && $item->cart_id === $cart->id, 403);
    }
}
