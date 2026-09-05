<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Sale;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

class SalesTrendChart extends ChartWidget
{
    use InteractsWithPageFilters;

    protected ?string $heading = 'Tendencia de ventas';

    protected ?string $description = 'Ingresos confirmados en el periodo seleccionado';

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

    protected function getFilters(): ?array
    {
        return [
            '7' => 'Últimos 7 días',
            '30' => 'Este mes',
            '90' => 'Últimos 90 días',
        ];
    }

    protected function periodDays(): int
    {
        if (filled($this->filter)) {
            return (int) $this->filter;
        }

        return (int) ($this->pageFilters['period'] ?? 30);
    }

    protected function getData(): array
    {
        $days = $this->periodDays();
        $start = now()->subDays($days - 1)->startOfDay();

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
            $labels[] = $days <= 14
                ? $date->translatedFormat('D d')
                : $date->format('d/m');
            $data[] = (float) ($totals[$key] ?? 0);
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos',
                    'data' => $data,
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'borderColor' => '#3B82F6',
                    'pointBackgroundColor' => '#3B82F6',
                    'pointBorderColor' => '#ffffff',
                    'pointBorderWidth' => 2,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                    'fill' => true,
                    'tension' => 0.4,
                    'borderWidth' => 2.5,
                ],
            ],
            'labels' => $labels,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => false,
                ],
                'tooltip' => [
                    'mode' => 'index',
                    'intersect' => false,
                ],
            ],
            'scales' => [
                'x' => [
                    'grid' => [
                        'display' => false,
                    ],
                    'ticks' => [
                        'color' => '#94A3B8',
                        'maxRotation' => 0,
                        'autoSkipPadding' => 12,
                    ],
                    'border' => [
                        'display' => false,
                    ],
                ],
                'y' => [
                    'grid' => [
                        'color' => 'rgba(148, 163, 184, 0.18)',
                        'drawBorder' => false,
                    ],
                    'ticks' => [
                        'color' => '#94A3B8',
                        'padding' => 8,
                    ],
                    'border' => [
                        'display' => false,
                        'dash' => [4, 4],
                    ],
                ],
            ],
            'maintainAspectRatio' => false,
        ];
    }
}
