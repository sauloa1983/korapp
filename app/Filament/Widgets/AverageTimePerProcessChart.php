<?php

namespace App\Filament\Widgets;

use App\Enums\ProductionLogStatus;
use App\Models\ProductionLog;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AverageTimePerProcessChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Tiempo promedio por proceso (minutos)';

    protected function getData(): array
    {
        $rows = ProductionLog::query()
            ->select('process_id', DB::raw('AVG(duration_seconds) as avg_seconds'))
            ->where('status', ProductionLogStatus::Terminado->value)
            ->whereNotNull('duration_seconds')
            ->groupBy('process_id')
            ->with('process')
            ->get();

        return [
            'datasets' => [
                [
                    'label' => 'Minutos promedio',
                    'data' => $rows->map(fn ($row): float => round((float) $row->avg_seconds / 60, 2))->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                ],
            ],
            'labels' => $rows->map(fn ($row): string => $row->process?->name ?? '—')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
