<?php

namespace App\Notifications;

use App\Models\Customer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CustomerRegistered extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Customer $customer,
    ) {
        $this->customer->loadMissing('website');
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
        $storeName = $this->customer->website?->name ?? config('app.name');

        return (new MailMessage)
            ->subject("¡Bienvenido a {$storeName}!")
            ->greeting("¡Hola, {$this->customer->name}!")
            ->line('Tu cuenta ha sido creada exitosamente.')
            ->line('')
            ->line('Ahora puedes:')
            ->line('- Comprar más rápido con tus datos guardados')
            ->line('- Consultar el historial de tus pedidos')
            ->line('- Administrar tus direcciones de envío')
            ->line('')
            ->action('Ir a la tienda', url('/'))
            ->line('¡Esperamos que disfrutes tu experiencia de compra!');
    }
}
