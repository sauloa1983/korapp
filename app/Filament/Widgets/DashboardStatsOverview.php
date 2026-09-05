<?php

namespace App\Filament\Widgets;

use App\Enums\ItemType;
use App\Enums\ProductionOrderStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\Sale;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Resumen del inicio';

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole('super_admin')
            || $user->can('ViewAny:Sale')
            || $user->can('ViewAny:Customer')
        );
    }

    protected function periodDays(): int
    {
        return (int) ($this->pageFilters['period'] ?? 30);
    }

    protected function getStats(): array
    {
        $days = $this->periodDays();
        $from = now()->subDays($days - 1)->startOfDay();
        $previousFrom = now()->subDays(($days * 2) - 1)->startOfDay();
        $previousTo = now()->subDays($days)->endOfDay();

        $salesTotal = (float) Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$from, now()->endOfDay()])
            ->sum('total');

        $previousSales = (float) Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$previousFrom, $previousTo])
            ->sum('total');

        $salesTrend = $previousSales > 0
            ? (int) round((($salesTotal - $previousSales) / $previousSales) * 100)
            : ($salesTotal > 0 ? 100 : 0);

        $newCustomers = Customer::query()
            ->where('created_at', '>=', $from)
            ->count();

        $previousCustomers = Customer::query()
            ->whereBetween('created_at', [$previousFrom, $previousTo])
            ->count();

        $customersTrend = $previousCustomers > 0
            ? (int) round((($newCustomers - $previousCustomers) / $previousCustomers) * 100)
            : ($newCustomers > 0 ? 100 : 0);

        $openOrders = ProductionOrder::query()
            ->whereIn('status', [
                ProductionOrderStatus::Pendiente->value,
                ProductionOrderStatus::EnProgreso->value,
            ])
            ->count();

        $lowStock = Item::query()
            ->whereColumn('stock', '<=', 'min_stock')
            ->where('type', '!=', ItemType::ProductoTerminado->value)
            ->count();

        return [
            Stat::make('Ventas', money($salesTotal))
                ->description(($salesTrend >= 0 ? '↑ ' : '↓ ') . abs($salesTrend) . '% vs periodo anterior')
                ->descriptionIcon($salesTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($salesTrend >= 0 ? 'success' : 'danger')
                ->color('primary')
                ->icon('heroicon-o-banknotes')
                ->extraAttributes(['class' => 'saas-stat saas-stat--primary']),
            Stat::make('Clientes nuevos', number_format($newCustomers))
                ->description(($customersTrend >= 0 ? '↑ ' : '↓ ') . abs($customersTrend) . '% vs periodo anterior')
                ->descriptionIcon($customersTrend >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($customersTrend >= 0 ? 'success' : 'danger')
                ->color('success')
                ->icon('heroicon-o-user-plus')
                ->extraAttributes(['class' => 'saas-stat saas-stat--success']),
            Stat::make('Órdenes abiertas', number_format($openOrders))
                ->description('Producción en curso')
                ->descriptionIcon('heroicon-m-cog-6-tooth')
                ->descriptionColor('info')
                ->color('info')
                ->icon('heroicon-o-clipboard-document-list')
                ->extraAttributes(['class' => 'saas-stat saas-stat--info']),
            Stat::make('Inventario bajo', number_format($lowStock))
                ->description($lowStock > 0 ? 'Requiere atención' : 'Inventario saludable')
                ->descriptionIcon($lowStock > 0 ? 'heroicon-m-exclamation-triangle' : 'heroicon-m-check-circle')
                ->descriptionColor($lowStock > 0 ? 'warning' : 'success')
                ->color('warning')
                ->icon('heroicon-o-archive-box')
                ->extraAttributes(['class' => 'saas-stat saas-stat--warning']),
        ];
    }
}
