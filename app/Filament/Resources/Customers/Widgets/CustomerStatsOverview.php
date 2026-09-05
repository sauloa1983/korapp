<?php

namespace App\Filament\Resources\Customers\Widgets;

use App\Models\Customer;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class CustomerStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<int, int>
     */
    protected function sparklineForDays(int $days = 7): array
    {
        $start = now()->subDays($days - 1)->startOfDay();

        $counts = Customer::query()
            ->where('created_at', '>=', $start)
            ->selectRaw('DATE(created_at) as day, COUNT(*) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $points = [];

        for ($date = $start->copy(); $date->lte(now()); $date->addDay()) {
            $points[] = (int) ($counts[$date->toDateString()] ?? 0);
        }

        return $points;
    }

    protected function getStats(): array
    {
        $total = Customer::query()->count();
        $active = Customer::query()->where('is_active', true)->count();
        $inactive = Customer::query()->where('is_active', false)->count();

        $newThisMonth = Customer::query()
            ->where('created_at', '>=', now()->startOfMonth())
            ->count();

        $newLastMonth = Customer::query()
            ->whereBetween('created_at', [
                now()->subMonth()->startOfMonth(),
                now()->subMonth()->endOfMonth(),
            ])
            ->count();

        $monthTrend = $newLastMonth > 0
            ? (int) round((($newThisMonth - $newLastMonth) / $newLastMonth) * 100)
            : ($newThisMonth > 0 ? 100 : 0);

        $activeShare = $total > 0 ? (int) round(($active / $total) * 100) : 0;
        $withSales = Customer::query()->has('sales')->count();
        $sparkline = $this->sparklineForDays(7);

        return [
            Stat::make('Total clientes', number_format($total))
                ->description($total > 0 ? 'Directorio completo' : 'Sin registros aún')
                ->descriptionIcon('heroicon-m-users')
                ->descriptionColor('primary')
                ->chart($sparkline)
                ->chartColor('primary')
                ->color('primary')
                ->icon('heroicon-o-user-group')
                ->extraAttributes(['class' => 'saas-stat saas-stat--primary']),
            Stat::make('Activos', number_format($active))
                ->description($activeShare . '% del total')
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->descriptionColor('success')
                ->chart($sparkline)
                ->chartColor('success')
                ->color('success')
                ->icon('heroicon-o-check-badge')
                ->extraAttributes(['class' => 'saas-stat saas-stat--success']),
            Stat::make('Nuevos este mes', number_format($newThisMonth))
                ->description(
                    ($monthTrend >= 0 ? '+' : '') . $monthTrend . '% vs mes anterior'
                )
                ->descriptionIcon($monthTrend >= 0
                    ? 'heroicon-m-arrow-trending-up'
                    : 'heroicon-m-arrow-trending-down')
                ->descriptionColor($monthTrend >= 0 ? 'success' : 'danger')
                ->chart($sparkline)
                ->chartColor('info')
                ->color('info')
                ->icon('heroicon-o-calendar-days')
                ->extraAttributes(['class' => 'saas-stat saas-stat--info']),
            Stat::make('Con ventas', number_format($withSales))
                ->description($inactive . ' inactivos')
                ->descriptionIcon('heroicon-m-shopping-bag')
                ->descriptionColor('warning')
                ->chart($sparkline)
                ->chartColor('warning')
                ->color('warning')
                ->icon('heroicon-o-banknotes')
                ->extraAttributes(['class' => 'saas-stat saas-stat--violet']),
        ];
    }
}
