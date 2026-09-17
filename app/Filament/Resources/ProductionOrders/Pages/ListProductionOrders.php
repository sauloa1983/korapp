<?php

namespace App\Filament\Resources\ProductionOrders\Pages;

use App\Enums\ProductionOrderStatus;
use App\Filament\Resources\ProductionOrders\ProductionOrderResource;
use App\Models\ProductionOrder;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;

class ListProductionOrders extends ListRecords
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Fabricación y entrega. Usa las pestañas para ver listas para entregar o atrasadas.';
    }

    /**
     * @return array<string, Tab>
     */
    public function getTabs(): array
    {
        $base = fn (): Builder => ProductionOrder::query();

        return [
            'all' => Tab::make('Todas')
                ->badge(fn (): int => $base()->count()),
            'plant' => Tab::make('En planta')
                ->icon('heroicon-o-cog-6-tooth')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->whereIn('status', [
                    ProductionOrderStatus::Pendiente,
                    ProductionOrderStatus::EnProgreso,
                ]))
                ->badge(fn (): int => $base()->whereIn('status', [
                    ProductionOrderStatus::Pendiente,
                    ProductionOrderStatus::EnProgreso,
                ])->count())
                ->badgeColor('warning'),
            'ready' => Tab::make('Listas para entregar')
                ->icon('heroicon-o-truck')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->readyForDelivery())
                ->badge(fn (): int => $base()->readyForDelivery()->count())
                ->badgeColor('success'),
            'overdue' => Tab::make('Atrasadas')
                ->icon('heroicon-o-exclamation-triangle')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->deliveryOverdue())
                ->badge(fn (): int => $base()->deliveryOverdue()->count())
                ->badgeColor('danger'),
            'delivered' => Tab::make('Entregadas')
                ->icon('heroicon-o-check-badge')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query
                    ->where('status', ProductionOrderStatus::Entregado)
                    ->latest('delivered_at'))
                ->badge(fn (): int => $base()->where('status', ProductionOrderStatus::Entregado)->count())
                ->badgeColor('primary'),
        ];
    }
}
