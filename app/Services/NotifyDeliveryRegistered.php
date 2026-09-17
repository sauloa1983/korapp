<?php

namespace App\Services;

use App\Filament\Pages\Entregas;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\ProductionOrder;
use App\Models\Sale;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Avisa cuando se registra una entrega al cliente (por OP o por factura).
 */
class NotifyDeliveryRegistered
{
    public function handle(ProductionOrder $order): void
    {
        try {
            $order->loadMissing(['item', 'quote', 'quotedBy', 'user', 'sale.customer', 'sale.user']);

            $recipients = $this->recipients(
                preferredIds: array_filter([
                    $order->quoted_by_user_id,
                    $order->quote?->user_id,
                    $order->sale?->user_id,
                    $order->user_id,
                ]),
            );

            if ($recipients->isEmpty()) {
                return;
            }

            $code = $order->code;
            $job = trim((string) ($order->item?->name ?: 'Trabajo'));
            $customer = $order->sale?->customer?->name
                ?? $order->quote?->customer?->name
                ?? $order->contact_name
                ?? 'cliente';
            $saleCode = $order->sale?->code;

            $body = $saleCode
                ? "Factura {$saleCode}: la OP {$code} ({$job}) se entregó a {$customer}."
                : "La OP {$code} ({$job}) se entregó a {$customer}.";

            $this->send($recipients, $body, $this->urlForOrder($order));
        } catch (Throwable $e) {
            Log::warning('No se pudo notificar entrega: '.$e->getMessage(), [
                'production_order_id' => $order->id,
            ]);
        }
    }

    public function handleSale(Sale $sale, int $ordersCount): void
    {
        try {
            $sale->loadMissing(['customer', 'user', 'quote', 'productionOrders']);

            $recipients = $this->recipients(
                preferredIds: array_filter([
                    $sale->user_id,
                    $sale->quote?->user_id,
                ]),
            );

            if ($recipients->isEmpty()) {
                return;
            }

            $customer = $sale->customer?->name ?? 'cliente';
            $detail = match (true) {
                $ordersCount === 0 => 'stock / sin OP',
                $ordersCount === 1 => '1 OP',
                default => "{$ordersCount} OPs",
            };
            $body = "Factura {$sale->code}: se registró la entrega ({$detail}) a {$customer}.";

            $url = rescue(
                fn (): string => Entregas::getUrl(['tab' => 'delivered']),
                rescue(fn (): string => SaleResource::getUrl('edit', ['record' => $sale]), null, false),
                false,
            );

            $this->send($recipients, $body, $url);
        } catch (Throwable $e) {
            Log::warning('No se pudo notificar entrega de venta: '.$e->getMessage(), [
                'sale_id' => $sale->id,
            ]);
        }
    }

    /** @param  Collection<int, User>  $recipients */
    protected function send(Collection $recipients, string $body, ?string $url): void
    {
        foreach ($recipients as $user) {
            $notification = FilamentNotification::make()
                ->title('Entrega registrada')
                ->icon('heroicon-o-truck')
                ->success()
                ->body($body);

            if (filled($url)) {
                $notification->actions([
                    Action::make('ver')
                        ->label('Ver entregas')
                        ->button()
                        ->url($url),
                ]);
            }

            $notification->sendToDatabase($user);
        }
    }

    protected function urlForOrder(ProductionOrder $order): ?string
    {
        return rescue(
            fn (): string => Entregas::getUrl(['tab' => 'delivered']),
            rescue(
                fn (): string => ProductionOrderResource::getUrl('edit', ['record' => $order]),
                null,
                false,
            ),
            false,
        );
    }

    /**
     * @param  array<int, int|string|null>  $preferredIds
     * @return Collection<int, User>
     */
    protected function recipients(array $preferredIds): Collection
    {
        $roles = config('korapp.delivery_alert_roles', [
            'super_admin',
            'Vendedor',
            'Gerencia',
        ]);

        $byRole = User::query()
            ->role($roles)
            ->get();

        $preferred = User::query()
            ->whereIn('id', array_filter($preferredIds))
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'Operario'))
            ->get();

        return $byRole
            ->merge($preferred)
            ->unique('id')
            ->values();
    }
}
