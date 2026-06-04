<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\Gateways\Concerns\InteractsWithGatewaySettings;
use App\Domain\Payment\PaymentException;
use App\Domain\Payment\PaymentResult;
use App\Domain\Payment\PaymentStatus;
use App\Domain\Payment\WebhookResult;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Pasarela Mercado Pago (Checkout Pro): crea una preferencia y redirige al
 * cliente al checkout alojado. El estado real del pago llega por webhook, que
 * se confirma consultando el pago contra la API.
 */
class MercadoPagoGateway implements PaymentGateway
{
    use InteractsWithGatewaySettings;

    public function code(): string
    {
        return 'mercadopago';
    }

    public function label(): string
    {
        return 'Tarjeta / Mercado Pago';
    }

    public function isAvailable(): bool
    {
        return $this->enabledInSettings() && ! empty($this->config('access_token'));
    }

    public function configFields(): array
    {
        return [
            ['key' => 'access_token', 'label' => 'Access token', 'secret' => true],
            ['key' => 'public_key', 'label' => 'Public key', 'secret' => false],
            ['key' => 'webhook_secret', 'label' => 'Webhook secret (firma)', 'secret' => true],
        ];
    }

    public function supportsMode(): bool
    {
        return true;
    }

    public function start(Order $order): PaymentResult
    {
        $order->loadMissing('items');

        $payload = [
            'external_reference' => (string) $order->id,
            'items' => $order->items->map(fn ($item) => [
                'title' => $item->name,
                'quantity' => $item->quantity,
                'unit_price' => (float) $item->unit_price,
                'currency_id' => $order->currency,
            ])->values()->all(),
            'payer' => ['email' => $order->email],
            'back_urls' => [
                'success' => route('checkout.success', $order),
                'pending' => route('checkout.pending', $order),
                'failure' => route('checkout.failure', $order),
            ],
            'auto_return' => 'approved',
            // El website va en la URL para que el webhook use sus credenciales.
            'notification_url' => route('webhooks.payments', ['gateway' => $this->code(), 'website' => $order->website_id]),
        ];

        $response = Http::withToken($this->config('access_token'))
            ->acceptJson()
            ->asJson()
            ->post("{$this->baseUrl()}/checkout/preferences", $payload);

        if ($response->failed()) {
            Log::warning('Mercado Pago: fallo al crear la preferencia.', [
                'order_id' => $order->id,
                'status' => $response->status(),
                'body' => $response->json(),
            ]);

            throw PaymentException::startFailed($this->code(), 'No se pudo crear la preferencia de pago.');
        }

        $data = $response->json();
        // En modo live se usa el init_point real; en sandbox, el de pruebas.
        $redirect = $this->isLive()
            ? ($data['init_point'] ?? $data['sandbox_init_point'] ?? null)
            : ($data['sandbox_init_point'] ?? $data['init_point'] ?? null);

        if (! $redirect) {
            throw PaymentException::startFailed($this->code(), 'La preferencia no devolvió una URL de pago.');
        }

        return new PaymentResult(
            status: PaymentStatus::Pending,
            redirectUrl: $redirect,
            transactionId: isset($data['id']) ? (string) $data['id'] : null,
            reference: isset($data['id']) ? (string) $data['id'] : null,
            payload: $data,
        );
    }

    public function parseWebhook(Request $request): ?WebhookResult
    {
        if (! $this->verifySignature($request)) {
            return null;
        }

        $type = $request->input('type', $request->input('topic'));

        if ($type !== 'payment') {
            return null;
        }

        $paymentId = $request->input('data.id', $request->input('id', $request->query('id')));

        if (! $paymentId) {
            return null;
        }

        $response = Http::withToken($this->config('access_token'))
            ->acceptJson()
            ->get("{$this->baseUrl()}/v1/payments/{$paymentId}");

        if ($response->failed()) {
            Log::warning('Mercado Pago: fallo al consultar el pago.', [
                'payment_id' => $paymentId,
                'status' => $response->status(),
            ]);

            return null;
        }

        $payment = $response->json();
        $orderId = (int) ($payment['external_reference'] ?? 0);

        if ($orderId <= 0) {
            return null;
        }

        $status = $this->mapStatus($payment['status'] ?? '');

        return new WebhookResult(
            // El estado forma parte del id de evento para procesar cada
            // transición (pending → approved) una sola vez.
            eventId: "{$paymentId}:{$status->value}",
            orderId: $orderId,
            status: $status,
            transactionId: (string) $paymentId,
            type: 'payment',
            payload: $payment,
        );
    }

    private function mapStatus(string $status): PaymentStatus
    {
        return match ($status) {
            'approved' => PaymentStatus::Paid,
            'rejected' => PaymentStatus::Failed,
            'cancelled' => PaymentStatus::Cancelled,
            'refunded', 'charged_back' => PaymentStatus::Refunded,
            default => PaymentStatus::Pending, // pending, in_process, authorized, in_mediation
        };
    }

    /**
     * Verifica la firma `x-signature` de Mercado Pago. Si no hay secreto
     * configurado, no se exige firma (útil en desarrollo y pruebas).
     */
    private function verifySignature(Request $request): bool
    {
        $secret = $this->config('webhook_secret');

        if (empty($secret)) {
            return true;
        }

        $signature = $request->header('x-signature');
        $requestId = $request->header('x-request-id');
        $dataId = $request->input('data.id', $request->query('id'));

        if (! $signature || ! $dataId) {
            return false;
        }

        $parts = [];
        foreach (explode(',', $signature) as $piece) {
            [$key, $value] = array_pad(explode('=', trim($piece), 2), 2, '');
            $parts[trim($key)] = trim($value);
        }

        if (empty($parts['ts']) || empty($parts['v1'])) {
            return false;
        }

        $manifest = "id:{$dataId};request-id:{$requestId};ts:{$parts['ts']};";
        $expected = hash_hmac('sha256', $manifest, $secret);

        return hash_equals($expected, $parts['v1']);
    }

    private function baseUrl(): string
    {
        return rtrim((string) $this->config('base_url', 'https://api.mercadopago.com'), '/');
    }
}
