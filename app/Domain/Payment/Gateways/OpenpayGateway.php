<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\PaymentException;
use App\Domain\Payment\PaymentResult;
use App\Domain\Payment\PaymentStatus;
use App\Domain\Payment\WebhookResult;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pasarela Openpay (México): cobro en efectivo (Paynet/tiendas) mediante un
 * cargo. Devuelve la URL del recibo con la referencia/código de barras; el pago
 * se confirma por webhook cuando el cliente paga en tienda.
 */
class OpenpayGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'openpay';
    }

    public function label(): string
    {
        return 'Efectivo (Openpay / Paynet)';
    }

    public function isAvailable(): bool
    {
        return ! empty($this->config('merchant_id')) && ! empty($this->config('private_key'));
    }

    public function start(Order $order): PaymentResult
    {
        $order->loadMissing('shippingAddress');

        $payload = [
            'method' => 'store', // pago en efectivo (Paynet / tiendas)
            'amount' => (float) $order->total,
            'currency' => $order->currency,
            'description' => "Orden {$order->number}",
            'order_id' => (string) $order->id, // referencia que regresa en el webhook
            'due_date' => now()->addDays(3)->format('Y-m-d\TH:i:s'),
            'customer' => [
                'name' => $this->customerName($order),
                'email' => $order->email,
            ],
        ];

        $response = Http::withBasicAuth((string) $this->config('private_key'), '')
            ->acceptJson()
            ->asJson()
            ->post("{$this->chargesUrl()}", $payload);

        if ($response->failed()) {
            Log::warning('Openpay: fallo al crear el cargo.', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw PaymentException::startFailed($this->code(), 'No se pudo crear el cargo.');
        }

        $charge = $response->json();

        return new PaymentResult(
            status: $this->mapStatus($charge['status'] ?? 'in_progress'),
            // El cliente va al recibo con la referencia para pagar en tienda.
            redirectUrl: $charge['payment_method']['url'] ?? $charge['payment_method']['barcode_url'] ?? null,
            transactionId: isset($charge['id']) ? (string) $charge['id'] : null,
            reference: $charge['payment_method']['reference'] ?? null,
            payload: $charge,
        );
    }

    public function parseWebhook(Request $request): ?WebhookResult
    {
        if (! $this->verifyWebhook($request)) {
            return null;
        }

        $type = $request->input('type');

        // Openpay envía un evento de verificación al registrar el webhook.
        if ($type === 'verification') {
            return null;
        }

        $transaction = $request->input('transaction', []);
        $transactionId = $transaction['id'] ?? null;
        $orderId = (int) ($transaction['order_id'] ?? 0);

        if (! $transactionId || $orderId <= 0) {
            return null;
        }

        $status = $this->mapStatus($transaction['status'] ?? '');

        return new WebhookResult(
            // El estado forma parte del id de evento para procesar cada
            // transición (in_progress → completed) una sola vez.
            eventId: "{$transactionId}:{$status->value}",
            orderId: $orderId,
            status: $status,
            transactionId: (string) $transactionId,
            type: $type,
            payload: $transaction,
        );
    }

    private function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'completed' => PaymentStatus::Paid,
            'failed' => PaymentStatus::Failed,
            'cancelled' => PaymentStatus::Cancelled,
            'refunded' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending, // in_progress, charge_pending
        };
    }

    /**
     * Las notificaciones de Openpay se autentican por Basic Auth configurado en
     * la URL del webhook. Si no hay usuario configurado, no se exige (dev/pruebas).
     */
    private function verifyWebhook(Request $request): bool
    {
        $user = $this->config('webhook_user');

        if (empty($user)) {
            return true;
        }

        return hash_equals((string) $user, (string) $request->getUser())
            && hash_equals((string) $this->config('webhook_password'), (string) $request->getPassword());
    }

    private function customerName(Order $order): string
    {
        $address = $order->shippingAddress;

        if ($address) {
            return trim("{$address->first_name} {$address->last_name}") ?: 'Cliente';
        }

        return 'Cliente';
    }

    private function chargesUrl(): string
    {
        return "{$this->baseUrl()}/{$this->config('merchant_id')}/charges";
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->config('base_url', 'https://sandbox-api.openpay.mx/v1'), '/');
    }

    private function config(string $key, mixed $default = null): mixed
    {
        return config("payments.openpay.{$key}", $default);
    }
}
