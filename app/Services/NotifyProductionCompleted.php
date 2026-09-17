<?php

namespace App\Services;

use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\ProductionOrder;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa a ventas / gerencia / admin cuando una OP queda Completada en planta.
 */
class NotifyProductionCompleted
{
    public function handle(ProductionOrder $order): void
    {
        try {
            $order->loadMissing(['item', 'quote', 'quotedBy', 'user']);

            $recipients = $this->recipients($order);

            if ($recipients->isEmpty()) {
                return;
            }

            $code = $order->code;
            $job = trim((string) ($order->item?->name ?: 'Trabajo'));
            $quote = $order->quote?->code;
            $body = $quote
                ? "La OP {$code} ({$job}) quedó Completada. Cotización {$quote}. Ya puedes crear la venta."
                : "La OP {$code} ({$job}) quedó Completada en planta.";

            $url = rescue(
                fn (): string => ProductionOrderResource::getUrl('edit', ['record' => $order]),
                null,
                false,
            );

            foreach ($recipients as $user) {
                $notification = FilamentNotification::make()
                    ->title('Trabajo terminado en planta')
                    ->icon('heroicon-o-check-badge')
                    ->success()
                    ->body($body);

                if (filled($url)) {
                    $notification->actions([
                        Action::make('ver')
                            ->label('Ver OP')
                            ->button()
                            ->url($url),
                    ]);
                }

                $notification->sendToDatabase($user);
            }
        } catch (Throwable $e) {
            Log::warning('No se pudo notificar OP completada: '.$e->getMessage(), [
                'production_order_id' => $order->id,
            ]);
        }
    }

    /** @return Collection<int, User> */
    protected function recipients(ProductionOrder $order): Collection
    {
        $roles = config('korapp.production_completed_alert_roles', [
            'super_admin',
            'Vendedor',
            'Gerencia',
        ]);

        $byRole = User::query()
            ->role($roles)
            ->get();

        $preferredIds = array_filter([
            $order->quoted_by_user_id,
            $order->quote?->user_id,
            $order->user_id,
        ]);

        $preferred = User::query()
            ->whereIn('id', $preferredIds)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Operario'))
            ->get();

        return $byRole
            ->merge($preferred)
            ->unique('id')
            ->values();
    }
}
