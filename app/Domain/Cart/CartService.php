<?php

namespace App\Domain\Cart;

use App\Domain\Catalog\BundleService;
use App\Domain\Catalog\ProductPricingService;
use App\Domain\Inventory\InsufficientStockException;
use App\Domain\Inventory\StockAvailabilityChecker;
use App\Domain\Shipping\ShippingService;
use App\Domain\Store\ScopedConfigService;
use App\Domain\Store\StoreContext;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\CartPriceRule;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Store;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Punto de entrada del carrito: resuelve el carrito de la tienda actual (invitado
 * o cliente), agrega/actualiza/elimina ítems validando stock y precio vigente.
 */
class CartService
{
    private const SESSION_KEY = 'cart_token';

    public function __construct(
        private readonly StoreContext $context,
        private readonly ProductPricingService $pricing,
        private readonly StockAvailabilityChecker $availability,
        private readonly ScopedConfigService $config,
        private readonly CartMerger $merger,
        private readonly CartTotalsCalculator $totals,
        private readonly ShippingService $shipping,
        private readonly BundleService $bundles,
    ) {}

    /**
     * Carrito activo de la tienda y dueño actuales (con precios refrescados).
     */
    public function current(bool $create = true): ?Cart
    {
        $store = $this->context->store();

        if (! $store) {
            return null;
        }

        $cart = $this->findCart($store);

        if (! $cart && $create) {
            $cart = $this->createCart($store);
        }

        if ($cart) {
            $cart->load('items.product.inventoryStocks', 'items.product.prices');
            $this->refreshPrices($cart);
            $this->pruneShippingMethod($cart);
        }

        return $cart;
    }

    /**
     * Fija el método de envío seleccionado para el carrito (si está disponible).
     */
    public function setShippingMethod(?string $code): void
    {
        $cart = $this->current();

        if ($code === null || $code === '') {
            $cart->update(['shipping_method_code' => null]);

            return;
        }

        if (! $this->shipping->isAvailable($cart, $code)) {
            throw CartException::shippingUnavailable();
        }

        $cart->update(['shipping_method_code' => $code]);
    }

    /**
     * Aplica un cupón al carrito tras validar que la regla existe, está vigente
     * y el subtotal cumple el mínimo.
     */
    public function applyCoupon(string $code): void
    {
        $cart = $this->current();
        $cart->loadMissing('store');

        $rule = CartPriceRule::active()
            ->where('coupon_code', $code)
            ->where(fn (Builder $q) => $q->whereNull('website_id')->orWhere('website_id', $cart->store?->website_id))
            ->first();

        if (! $rule || ! $rule->isUsable()) {
            throw CartException::invalidCoupon();
        }

        $subtotal = $cart->items->reduce(
            fn (float $carry, CartItem $item) => $carry + ((float) $item->unit_price * $item->quantity),
            0.0,
        );

        if (! $rule->meetsMinimum($subtotal)) {
            throw CartException::couponMinimumNotMet(number_format((float) $rule->min_subtotal, 2, '.', ''));
        }

        $cart->update(['coupon_code' => $code]);
    }

    /**
     * Quita el cupón aplicado al carrito.
     */
    public function removeCoupon(): void
    {
        $cart = $this->current(false);

        $cart?->update(['coupon_code' => null]);
    }

    /**
     * Limpia el método de envío si dejó de estar disponible (p. ej. cambió el subtotal).
     */
    private function pruneShippingMethod(Cart $cart): void
    {
        if ($cart->shipping_method_code && ! $this->shipping->isAvailable($cart, $cart->shipping_method_code)) {
            $cart->update(['shipping_method_code' => null]);
        }
    }

    public function addProduct(Product $product, int $quantity = 1): CartItem
    {
        $quantity = max(1, $quantity);
        $cart = $this->current();
        $store = $this->context->store();

        if (! $this->isPurchasable($product, $store->id)) {
            throw CartException::notPurchasable($product->name);
        }

        $unitPrice = $this->effectiveUnitPrice($product, $store->id);

        if ($unitPrice === null) {
            throw CartException::notPurchasable($product->name);
        }

        $existing = $cart->items()->where('product_id', $product->id)->first();
        $desired = ($existing?->quantity ?? 0) + $quantity;

        $this->assertStock($product, $desired);

        $item = $cart->items()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'sku' => $product->sku,
                'name' => $product->name,
                'quantity' => $desired,
                'unit_price' => $unitPrice,
            ],
        );

        $this->touch($cart);

        return $item;
    }

    public function updateQuantity(CartItem $item, int $quantity): void
    {
        if ($quantity <= 0) {
            $this->removeItem($item);

            return;
        }

        $this->assertStock($item->product, $quantity);

        $item->update(['quantity' => $quantity]);
        $this->touch($item->cart);
    }

    public function removeItem(CartItem $item): void
    {
        $cart = $item->cart;
        $item->delete();
        $this->touch($cart);
    }

    public function clear(Cart $cart): void
    {
        $cart->items()->delete();
    }

    /**
     * Resumen ligero para el badge del header.
     *
     * @return array{count: int, total: string}
     */
    public function summary(): array
    {
        $cart = $this->current(false);

        if (! $cart) {
            return ['count' => 0, 'total' => '0.00'];
        }

        $totals = $this->totals->totals($cart);

        return ['count' => $totals['items_count'], 'total' => $totals['total']];
    }

    /**
     * Fusiona el carrito de invitado en el del cliente tras iniciar sesión.
     */
    public function mergeForCustomer(Customer $customer): void
    {
        $store = $this->context->store();

        if (! $store) {
            return;
        }

        $token = session(self::SESSION_KEY);

        $guest = $token
            ? Cart::active()->where('store_id', $store->id)->whereNull('customer_id')->where('session_token', $token)->first()
            : null;

        if (! $guest) {
            return;
        }

        $target = Cart::active()->where('store_id', $store->id)->where('customer_id', $customer->id)->first()
            ?? Cart::create($this->newCartAttributes($store, $customer->id));

        $this->merger->merge($guest, $target);
        session()->forget(self::SESSION_KEY);
    }

    private function findCart(Store $store): ?Cart
    {
        if (auth('customer')->check()) {
            return Cart::active()
                ->where('store_id', $store->id)
                ->where('customer_id', auth('customer')->id())
                ->first();
        }

        $token = session(self::SESSION_KEY);

        if (! $token) {
            return null;
        }

        return Cart::active()
            ->where('store_id', $store->id)
            ->whereNull('customer_id')
            ->where('session_token', $token)
            ->first();
    }

    private function createCart(Store $store): Cart
    {
        if (auth('customer')->check()) {
            return Cart::create($this->newCartAttributes($store, auth('customer')->id()));
        }

        return Cart::create($this->newCartAttributes($store, null, $this->guestToken()));
    }

    /**
     * @return array<string, mixed>
     */
    private function newCartAttributes(Store $store, ?int $customerId, ?string $token = null): array
    {
        return [
            'store_id' => $store->id,
            'customer_id' => $customerId,
            'session_token' => $token,
            'status' => Cart::STATUS_ACTIVE,
            'currency' => $this->config->getForContext($this->context, 'currency', 'MXN'),
            'expires_at' => now()->addDays(30),
        ];
    }

    private function guestToken(): string
    {
        $token = session(self::SESSION_KEY);

        if (! $token) {
            $token = Str::random(40);
            session([self::SESSION_KEY => $token]);
        }

        return $token;
    }

    /**
     * Sincroniza el precio unitario de cada ítem con el precio vigente por tienda.
     */
    private function refreshPrices(Cart $cart): void
    {
        foreach ($cart->items as $item) {
            $product = $item->product;

            if (! $product) {
                continue;
            }

            $current = $this->effectiveUnitPrice($product, $cart->store_id);

            if ($current !== null && (float) $item->unit_price !== $current) {
                $item->update(['unit_price' => $current]);
            }
        }
    }

    /**
     * Precio unitario efectivo según el tipo de producto (bundle suma componentes).
     */
    private function effectiveUnitPrice(Product $product, int $storeId): ?float
    {
        $effective = $product->isBundle()
            ? $this->bundles->priceFor($product, $storeId)['effective_price']
            : $this->pricing->priceFor($product, $storeId)['effective_price'];

        return $effective !== null ? (float) $effective : null;
    }

    private function isPurchasable(Product $product, int $storeId): bool
    {
        if ($product->status !== Product::STATUS_ACTIVE || $product->visibility === 'hidden') {
            return false;
        }

        return $product->storeLinks()
            ->where('store_id', $storeId)
            ->where('is_active', true)
            ->exists();
    }

    private function assertStock(Product $product, int $quantity): void
    {
        $canFulfill = $product->isBundle()
            ? $this->bundles->canFulfill($product, $quantity)
            : $this->availability->canFulfill($product, $quantity);

        if (! $canFulfill) {
            throw InsufficientStockException::for($product->sku, $quantity, $this->availability->totalAvailable($product));
        }
    }

    private function touch(Cart $cart): void
    {
        $cart->update(['expires_at' => now()->addDays(30)]);
    }
}
