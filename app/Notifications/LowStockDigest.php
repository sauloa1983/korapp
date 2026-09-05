<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;

class LowStockDigest extends Notification
{
    use Queueable;

    /** @param Collection<int, \App\Models\Item> $items */
    public function __construct(public Collection $items)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('Resumen de inventario: '.$this->items->count().' artículo(s) con existencia baja')
            ->greeting('Alerta de inventario')
            ->line('Los siguientes artículos están en o por debajo de su existencia mínima:');

        foreach ($this->items as $item) {
            $mail->line("• {$item->sku} — {$item->name}: ".(float) $item->stock.' / mín '.(float) $item->min_stock.' '.$item->unit_of_measure);
        }

        return $mail->line('Revisa el panel para generar las órdenes de compra necesarias.');
    }
}
