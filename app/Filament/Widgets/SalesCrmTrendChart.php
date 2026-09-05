<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;

class SalesCrmTrendChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Ventas en el tiempo';

    protected ?string $description = 'Ingresos confirmados — últimos 30 días';

    protected int | string | array $columnSpan = [
        'default' => 1,
        'lg' => 2,
        'xl' => 2,
    ];

    protected ?string $maxHeight = '280px';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole('super_admin')
            || $user->can('ViewAny:Sale')
        );
    }

    protected function getData(): array
    {
        $start = now()->subDays(29)->startOfDay();

        $totals = Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$start, now()->endOfDay()])
            ->selectRaw('DATE(sold_at) as day, SUM(total) as aggregate')
            ->groupBy('day')
            ->pluck('aggregate', 'day');

        $labels = [];
        $data = [];

        for ($date = $start->copy(); $date->lte(now()); $date->addDay()) {
            $key = $date->toDateString();
            $labels[] = $date->format('d/m');
            $data[] = (float) ($totals[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.12)',
                    'borderColor' => '#3B82F6',
                    'pointBackgroundColor' => '#3B82F6',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 3,
                    'fill' => true,
                    'tension' => 0.35,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
