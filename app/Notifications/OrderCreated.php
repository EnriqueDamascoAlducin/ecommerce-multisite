<?php

namespace App\Notifications;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OrderCreated extends Notification implements ShouldQueue
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
            ->subject("¡Gracias por tu compra! — {$storeName}")
            ->greeting("Hola, {$this->order->email}")
            ->line("Tu orden **#{$this->order->number}** ha sido recibida y está siendo procesada.")
            ->line('Estos son los detalles de tu compra:')
            ->line('');

        foreach ($this->order->items as $item) {
            $mail->line("- {$item->name} x{$item->quantity} — \$".number_format($item->line_total, 2));
        }

        $mail->line('')
            ->line('**Subtotal:** $'.number_format($this->order->subtotal, 2));

        if ((float) $this->order->shipping_amount > 0) {
            $mail->line('**Envío:** $'.number_format($this->order->shipping_amount, 2));
        }

        $mail->line('**Total:** $'.number_format($this->order->total, 2))
            ->line('')
            ->action('Ver mi orden', $url)
            ->line('¡Gracias por confiar en nosotros!');

        return $mail;
    }
}
