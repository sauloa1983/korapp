<?php

namespace App\Notifications;

use App\Models\Item;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LowStockAlert extends Notification
{
    use Queueable;

    public function __construct(public Item $item)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Inventario bajo: {$this->item->name}")
            ->greeting('Alerta de inventario')
            ->line("El artículo {$this->item->sku} — {$this->item->name} alcanzó un nivel bajo de existencia.")
            ->line('Existencia actual: '.(float) $this->item->stock.' '.$this->item->unit_of_measure)
            ->line('Existencia mínima: '.(float) $this->item->min_stock.' '.$this->item->unit_of_measure)
            ->action('Ver inventario', url('/admin/items'));
    }
}
