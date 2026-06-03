<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentFailed extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Order $order,
    ) {
        $this->order->loadMissing('store', 'items');
    }

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $storeName = $this->order->store?->name ?? config('app.name');
        $url = url('/checkout');

        return (new MailMessage)
            ->subject("Hubo un problema con tu pago — {$storeName}")
            ->greeting("Hola, {$this->order->email}")
            ->line("El pago de tu orden **#{$this->order->number}** no pudo ser procesado.")
            ->line('')
            ->line('Posibles causas:')
            ->line('- Fondos insuficientes')
            ->line('- Datos de la tarjeta incorrectos')
            ->line('- Límite de la tarjeta excedido')
            ->line('- La transacción fue rechazada por el banco')
            ->line('')
            ->line('Puedes intentar pagar nuevamente desde tu carrito de compras.')
            ->action('Intentar de nuevo', $url)
            ->line('Si el problema persiste, contacta a tu banco o elige otro método de pago.');
    }
}
