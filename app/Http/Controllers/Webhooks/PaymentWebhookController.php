<?php

namespace App\Http\Controllers\Webhooks;

use App\Domain\Payment\PaymentException;
use App\Domain\Payment\PaymentGatewayRegistry;
use App\Domain\Payment\PaymentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Punto de entrada de las notificaciones de pago de cualquier pasarela.
 * Sin auth ni CSRF: la autenticidad la valida cada gateway (firma/consulta).
 * Siempre responde 200 cuando el gateway existe para evitar reintentos en bucle.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayRegistry $registry,
        private readonly PaymentService $payments,
    ) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        if (! $this->registry->has($gateway)) {
            return response()->json(['message' => 'Unknown gateway.'], 404);
        }

        try {
            $handled = $this->payments->handleWebhook($gateway, $request);
        } catch (PaymentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['handled' => $handled]);
    }
}
