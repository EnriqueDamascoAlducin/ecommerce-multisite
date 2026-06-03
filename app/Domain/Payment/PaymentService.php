<?php

namespace App\Domain\Payment;

use App\Domain\Inventory\StockReservationService;
use App\Models\Order;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookEvent;
use App\Notifications\PaymentApproved;
use App\Notifications\PaymentFailed;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Núcleo de pagos: inicia cobros a través de la pasarela elegida y procesa las
 * notificaciones entrantes de forma idempotente, transicionando la orden.
 * No conoce ninguna pasarela concreta; sólo el contrato y el registro.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly StockReservationService $reservations,
    ) {}

    /**
     * Métodos de pago disponibles para el checkout.
     *
     * @return list<array{code: string, label: string}>
     */
    public function methods(): array
    {
        return $this->registry->methods();
    }

    /**
     * Inicia el cobro de una orden. Registra la transacción y, si la pasarela
     * resuelve el pago de inmediato, transiciona la orden. Devuelve el resultado
     * para que el controlador redirija al checkout alojado cuando aplique.
     */
    public function start(Order $order, string $gatewayCode): PaymentResult
    {
        $gateway = $this->registry->get($gatewayCode);
        $result = $gateway->start($order);

        $this->recordTransaction($order, $gatewayCode, $result->status, [
            'gateway_transaction_id' => $result->transactionId,
            'reference' => $result->reference,
            'payload' => $result->payload,
        ]);

        // Un pago "pendiente" deja la orden esperando confirmación (webhook); no
        // transiciona. Cualquier estado liquidado al iniciar sí se aplica.
        if ($result->status !== PaymentStatus::Pending) {
            $this->applyToOrder($order, $result->status, $gatewayCode);
        }

        return $result;
    }

    /**
     * Procesa una notificación entrante de un gateway de forma idempotente.
     * Devuelve true si se procesó (o ya estaba procesada), false si se ignoró.
     */
    public function handleWebhook(string $gatewayCode, Request $request): bool
    {
        $gateway = $this->registry->get($gatewayCode);
        $result = $gateway->parseWebhook($request);

        if (! $result instanceof WebhookResult) {
            return false;
        }

        return DB::transaction(function () use ($gatewayCode, $result) {
            $event = PaymentWebhookEvent::firstOrCreate(
                ['gateway' => $gatewayCode, 'event_id' => $result->eventId],
                [
                    'type' => $result->type,
                    'status' => PaymentWebhookEvent::STATUS_RECEIVED,
                    'payload' => $result->payload,
                ],
            );

            // Idempotencia: una notificación ya procesada no se reprocesa.
            if ($event->status === PaymentWebhookEvent::STATUS_PROCESSED) {
                return true;
            }

            $order = Order::find($result->orderId);

            if (! $order) {
                $event->update(['status' => PaymentWebhookEvent::STATUS_FAILED, 'processed_at' => now()]);

                return false;
            }

            $this->recordTransaction($order, $gatewayCode, $result->status, [
                'gateway_transaction_id' => $result->transactionId,
                'payload' => $result->payload,
            ]);

            $this->applyToOrder($order, $result->status, $gatewayCode);

            $event->update(['status' => PaymentWebhookEvent::STATUS_PROCESSED, 'processed_at' => now()]);

            return true;
        });
    }

    /**
     * @param  array{gateway_transaction_id?: ?string, reference?: ?string, payload?: ?array<string, mixed>}  $attrs
     */
    private function recordTransaction(Order $order, string $gateway, PaymentStatus $status, array $attrs = []): PaymentTransaction
    {
        return $order->transactions()->create([
            'gateway' => $gateway,
            'type' => PaymentTransaction::TYPE_PAYMENT,
            'status' => $status->value,
            'amount' => $order->total,
            'currency' => $order->currency,
            'gateway_transaction_id' => $attrs['gateway_transaction_id'] ?? null,
            'reference' => $attrs['reference'] ?? null,
            'payload' => $attrs['payload'] ?? null,
            'processed_at' => $status === PaymentStatus::Pending ? null : now(),
        ]);
    }

    /**
     * Transiciona la orden según el estado de pago, sin regresar desde un estado
     * ya liquidado por notificaciones tardías. Libera stock en cancelación/reembolso.
     */
    private function applyToOrder(Order $order, PaymentStatus $status, string $gateway): void
    {
        $target = $status->orderStatus();

        if ($order->status === $target) {
            return;
        }

        if ($this->isSettled($order->status) && in_array($status, [PaymentStatus::Pending, PaymentStatus::Failed], true)) {
            return;
        }

        $order->transitionTo($target, "Pago: {$status->value} ({$gateway}).");

        if ($target === Order::STATUS_PAID) {
            Notification::route('mail', $order->email)->notify(new PaymentApproved($order));
        } elseif ($target === Order::STATUS_FAILED) {
            Notification::route('mail', $order->email)->notify(new PaymentFailed($order));
        }

        if ($status->releasesStock()) {
            $this->reservations->releaseByReference("order:{$order->id}");
        }
    }

    private function isSettled(string $status): bool
    {
        return in_array($status, [
            Order::STATUS_PAID,
            Order::STATUS_INVOICED,
            Order::STATUS_PARTIALLY_SHIPPED,
            Order::STATUS_SHIPPED,
            Order::STATUS_COMPLETE,
        ], true);
    }
}
