<?php

namespace App\Domain\Promotion;

use App\Models\Cart;
use App\Models\CartPriceRule;
use Illuminate\Database\Eloquent\Builder;

/**
 * Evalúa las reglas de carrito aplicables a un carrito: reglas automáticas (sin
 * cupón) + la regla del cupón aplicado. Devuelve el descuento total y si otorga
 * envío gratis. No persiste nada (la fuente de verdad de totales lo consume).
 */
class CartRuleEvaluator
{
    /**
     * @return array{discount: float, free_shipping: bool, applied: list<array{name: string, code: ?string, amount: float, free_shipping: bool}>}
     */
    public function evaluate(Cart $cart, float $subtotal): array
    {
        $cart->loadMissing('store');
        $websiteId = $cart->store?->website_id;

        $rules = CartPriceRule::query()
            ->active()
            ->where(fn (Builder $q) => $q->whereNull('website_id')->when($websiteId, fn (Builder $w, $id) => $w->orWhere('website_id', $id)))
            ->where(fn (Builder $q) => $q->whereNull('coupon_code')->when($cart->coupon_code, fn (Builder $w, $code) => $w->orWhere('coupon_code', $code)))
            ->get()
            ->filter(fn (CartPriceRule $rule) => $rule->isWithinWindow() && $rule->hasUsesLeft() && $rule->meetsMinimum($subtotal));

        $discount = 0.0;
        $freeShipping = false;
        $applied = [];

        foreach ($rules as $rule) {
            $amount = $rule->discountAmount($subtotal);
            $discount += $amount;
            $freeShipping = $freeShipping || $rule->grantsFreeShipping();

            $applied[] = [
                'name' => $rule->name,
                'code' => $rule->coupon_code,
                'amount' => $amount,
                'free_shipping' => $rule->grantsFreeShipping(),
            ];
        }

        return [
            'discount' => min($discount, $subtotal),
            'free_shipping' => $freeShipping,
            'applied' => $applied,
        ];
    }
}
