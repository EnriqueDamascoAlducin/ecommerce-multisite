<?php

namespace App\Domain\Payment\Contracts;

use App\Domain\Payment\PaymentResult;
use App\Domain\Payment\WebhookResult;
use App\Models\Order;
use Illuminate\Http\Request;

/**
 * Contrato común para todas las pasarelas de pago. El core (PaymentService)
 * sólo conoce esta interfaz; cada pasarela concreta implementa los detalles.
 */
interface PaymentGateway
{
    /**
     * Código corto y estable usado en rutas, config y la columna `gateway`.
     */
    public function code(): string;

    /**
     * Etiqueta legible para el checkout.
     */
    public function label(): string;

    /**
     * Indica si la pasarela está configurada y puede ofrecerse al cliente.
     */
    public function isAvailable(): bool;

    /**
     * Campos de credenciales que se configuran desde el admin (vacío si no usa).
     *
     * @return list<array{key: string, label: string, secret: bool}>
     */
    public function configFields(): array;

    /**
     * ¿La pasarela distingue entre modo sandbox y producción (live)?
     */
    public function supportsMode(): bool;

    /**
     * Inicia el cobro de una orden recién creada. Devuelve a dónde redirigir al
     * cliente (checkout alojado) o null para flujos sin redirección (offline).
     */
    public function start(Order $order): PaymentResult;

    /**
     * Interpreta una notificación entrante. Devuelve el resultado mapeado a una
     * orden, o null si la notificación no es relevante (otro tipo de evento).
     */
    public function parseWebhook(Request $request): ?WebhookResult;
}
