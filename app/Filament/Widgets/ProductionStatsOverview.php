<?php

namespace App\Filament\Widgets;

use App\Enums\ItemType;
use App\Enums\ProductionOrderStatus;
use App\Models\Item;
use App\Models\ProductionLog;
use App\Models\ProductionOrder;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class ProductionStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Estadísticas de producción';

    protected function getStats(): array
    {
        $open = ProductionOrder::query()
            ->whereIn('status', [
                ProductionOrderStatus::Pendiente->value,
                ProductionOrderStatus::EnProgreso->value,
            ])
            ->count();

        $completed = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completado->value)
            ->count();

        $lowStock = Item::query()
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('type', '!=', ItemType::ProductoTerminado->value)
            ->count();

        $avgMinutes = round((float) ProductionLog::query()->avg('duration_seconds') / 60, 1);

        return [
            Stat::make('Órdenes abiertas', $open)
                ->description($completed . ' completadas')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('warning'),
            Stat::make('Materia prima bajo mínimo', $lowStock)
                ->description($lowStock > 0 ? 'Requiere reabastecer' : 'Inventario saludable')
                ->descriptionIcon($lowStock > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->color($lowStock > 0 ? 'danger' : 'success'),
            Stat::make('Tiempo prom. por etapa', $avgMinutes . ' min')
                ->description('Sobre etapas finalizadas')
                ->descriptionIcon('heroicon-m-clock')
                ->color('info'),
        ];
    }
}
