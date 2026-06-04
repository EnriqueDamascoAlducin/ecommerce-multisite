<?php

namespace App\Http\Controllers\Storefront;

use App\Domain\Cart\CartException;
use App\Domain\Checkout\CheckoutException;
use App\Domain\Checkout\CheckoutService;
use App\Domain\Inventory\InsufficientStockException;
use App\Domain\Payment\PaymentException;
use App\Domain\Payment\PaymentService;
use App\Http\Controllers\Controller;
use App\Http\Requests\Storefront\PlaceOrderRequest;
use App\Models\Order;
use App\Notifications\OrderCreated;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class CheckoutController extends Controller
{
    private const RECENT_ORDERS_KEY = 'recent_orders';

    public function __construct(
        private readonly CheckoutService $checkout,
        private readonly PaymentService $payments,
    ) {}

    public function index(): RedirectResponse|Response
    {
        $summary = $this->checkout->summary();

        if (! $summary) {
            return to_route('cart.index')->with('error', 'Tu carrito está vacío.');
        }

        return Inertia::render('storefront/checkout', $summary);
    }

    public function store(PlaceOrderRequest $request): RedirectResponse|SymfonyResponse
    {
        $data = $request->validated();

        if (! empty($data['billing_same'])) {
            unset($data['billing']);
        }

        try {
            $order = $this->checkout->place($data);
        } catch (CheckoutException|CartException|InsufficientStockException $e) {
            return to_route('checkout.index')->with('error', $e->getMessage());
        }

        // Permite al invitado ver su orden recién creada.
        $request->session()->push(self::RECENT_ORDERS_KEY, $order->id);

        Notification::route('mail', $order->email)->notify(new OrderCreated($order));

        // Inicia el cobro con la pasarela elegida. Si requiere checkout alojado
        // (p. ej. Mercado Pago), redirige al cliente fuera del sitio.
        try {
            $result = $this->payments->start($order, $data['payment_method']);
        } catch (PaymentException $e) {
            return to_route('checkout.pending', $order)
                ->with('error', 'No se pudo iniciar el pago. Te contactaremos para completarlo.');
        }

        if ($result->requiresRedirect()) {
            return Inertia::location($result->redirectUrl);
        }

        return to_route('checkout.success', $order);
    }

    public function success(Request $request, Order $order): RedirectResponse|Response
    {
        if (! $this->canView($request, $order)) {
            return to_route('home');
        }

        return Inertia::render('storefront/checkout-success', [
            'order' => $this->orderSummary($order),
        ]);
    }

    public function pending(Request $request, Order $order): RedirectResponse|Response
    {
        if (! $this->canView($request, $order)) {
            return to_route('home');
        }

        return Inertia::render('storefront/checkout-pending', [
            'order' => $this->orderSummary($order),
        ]);
    }

    public function failure(Request $request, Order $order): RedirectResponse|Response
    {
        if (! $this->canView($request, $order)) {
            return to_route('home');
        }

        return Inertia::render('storefront/checkout-failure', [
            'order' => $this->orderSummary($order),
        ]);
    }

    private function canView(Request $request, Order $order): bool
    {
        $customerId = auth('customer')->id();

        if ($customerId && $order->customer_id === $customerId) {
            return true;
        }

        return in_array($order->id, $request->session()->get(self::RECENT_ORDERS_KEY, []), true);
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSummary(Order $order): array
    {
        $order->load('items', 'shippingAddress');

        return [
            'number' => $order->number,
            'status' => $order->status,
            'email' => $order->email,
            'total' => (string) $order->total,
            'shipping_amount' => (string) $order->shipping_amount,
            'subtotal' => (string) $order->subtotal,
            'payment_method' => $order->payment_method,
            'items' => $order->items->map(fn ($item) => [
                'name' => $item->name,
                'quantity' => $item->quantity,
                'line_total' => (string) $item->line_total,
            ])->values(),
            'shipping_address' => $order->shippingAddress?->only([
                'first_name', 'last_name', 'line1', 'line2', 'city', 'state', 'postal_code', 'country',
            ]),
        ];
    }
}
