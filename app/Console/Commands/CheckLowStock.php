<?php

namespace App\Console\Commands;

use App\Models\Item;
use App\Models\User;
use App\Notifications\LowStockDigest;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Notification;

class CheckLowStock extends Command
{
    protected $signature = 'inventory:check-low-stock';

    protected $description = 'Revisa el inventario y alerta a los administradores sobre artículos con existencia baja';

    public function handle(): int
    {
        /** @var Collection<int, Item> $items */
        $items = Item::query()
            ->where('is_active', true)
            ->where('min_stock', '>', 0)
            ->whereColumn('stock', '<=', 'min_stock')
            ->orderBy('name')
            ->get();

        if ($items->isEmpty()) {
            $this->info('Inventario sano: no hay artículos con existencia baja.');

            return self::SUCCESS;
        }

        $admins = User::role('super_admin')->get();

        if ($admins->isEmpty()) {
            $this->warn('Hay artículos con existencia baja, pero no existen administradores para notificar.');

            return self::SUCCESS;
        }

        Notification::send($admins, new LowStockDigest($items));

        foreach ($admins as $admin) {
            FilamentNotification::make()
                ->title('Inventario: '.$items->count().' artículo(s) con existencia baja')
                ->icon('heroicon-o-exclamation-triangle')
                ->warning()
                ->body($items->take(5)->map(fn (Item $i): string => "{$i->sku} ({$i->stock})")->implode(', ').($items->count() > 5 ? '…' : ''))
                ->sendToDatabase($admin);
        }

        $this->info("Se alertó a {$admins->count()} administrador(es) sobre {$items->count()} artículo(s) con existencia baja.");

        return self::SUCCESS;
    }
}
