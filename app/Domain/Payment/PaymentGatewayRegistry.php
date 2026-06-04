<?php

namespace App\Domain\Payment;

use App\Domain\Payment\Contracts\PaymentGateway;

/**
 * Registro de pasarelas de pago disponibles. Es la fuente única de verdad de
 * los métodos de pago ofrecidos en el checkout (sólo los configurados).
 */
class PaymentGatewayRegistry
{
    /** @var array<string, PaymentGateway> */
    private array $gateways = [];

    public function register(PaymentGateway $gateway): void
    {
        $this->gateways[$gateway->code()] = $gateway;
    }

    public function has(string $code): bool
    {
        return isset($this->gateways[$code]);
    }

    public function get(string $code): PaymentGateway
    {
        if (! $this->has($code)) {
            throw PaymentException::unknownGateway($code);
        }

        $gateway = $this->gateways[$code];

        if (! $gateway->isAvailable()) {
            throw PaymentException::gatewayUnavailable($code);
        }

        return $gateway;
    }

    /**
     * Todas las pasarelas registradas, estén o no configuradas (para el admin).
     *
     * @return list<PaymentGateway>
     */
    public function all(): array
    {
        return array_values($this->gateways);
    }

    /**
     * Pasarelas disponibles (configuradas) para mostrar en el checkout.
     *
     * @return list<PaymentGateway>
     */
    public function available(): array
    {
        return array_values(array_filter(
            $this->gateways,
            fn (PaymentGateway $gateway) => $gateway->isAvailable(),
        ));
    }

    /**
     * Métodos de pago disponibles como pares código/etiqueta.
     *
     * @return list<array{code: string, label: string}>
     */
    public function methods(): array
    {
        return array_map(
            fn (PaymentGateway $gateway) => ['code' => $gateway->code(), 'label' => $gateway->label()],
            $this->available(),
        );
    }

    /**
     * @return list<string>
     */
    public function availableCodes(): array
    {
        return array_map(fn (PaymentGateway $gateway) => $gateway->code(), $this->available());
    }
}
