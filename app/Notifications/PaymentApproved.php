<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PaymentApproved extends Notification implements ShouldQueue
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
        $url = url('/checkout/exito/'.$this->order->id);

        $mail = (new MailMessage)
            ->subject("Pago confirmado — {$storeName}")
            ->greeting("¡Hola, {$this->order->email}!")
            ->line("El pago de tu orden **#{$this->order->number}** ha sido confirmado.")
            ->line('')
            ->line('Resumen de tu compra:')
            ->line('');

        foreach ($this->order->items as $item) {
            $mail->line("- {$item->name} x{$item->quantity} — \$".number_format($item->line_total, 2));
        }

        $mail->line('')
            ->line('**Total pagado:** $'.number_format($this->order->total, 2))
            ->line('')
            ->action('Ver mi orden', $url)
            ->line('Estamos preparando tu pedido. Te notificaremos cuando esté en camino.');

        return $mail;
    }
}
