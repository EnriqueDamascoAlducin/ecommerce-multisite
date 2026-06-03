<?php

namespace App\Domain\Payment\Gateways;

use App\Domain\Payment\Contracts\PaymentGateway;
use App\Domain\Payment\PaymentResult;
use App\Domain\Payment\PaymentStatus;
use App\Domain\Payment\WebhookResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Pago offline (transferencia / depósito). No cobra nada: deja la orden
 * pendiente de pago para que un administrador la confirme manualmente.
 * Siempre disponible; no recibe notificaciones.
 */
class OfflineGateway implements PaymentGateway
{
    public function code(): string
    {
        return 'offline';
    }

    public function label(): string
    {
        return 'Transferencia / pago pendiente';
    }

    public function isAvailable(): bool
    {
        return true;
    }

    public function start(Order $order): PaymentResult
    {
        // Sin redirección: la orden queda pendiente de pago.
        return new PaymentResult(status: PaymentStatus::Pending);
    }

    public function parseWebhook(Request $request): ?WebhookResult
    {
        return null;
    }
}
