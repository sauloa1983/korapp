<?php

namespace App\Filament\Widgets;

use App\Enums\ProductionLogStatus;
use App\Enums\ProductionOrderStatus;
use App\Filament\Pages\EscaneoOperario;
use App\Models\ProductionLog;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class OperatorStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Tu jornada';

    protected ?string $pollingInterval = '30s';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->user()?->isOperario() === true;
    }

    protected function getStats(): array
    {
        $userId = Auth::id();
        $todayStart = now()->startOfDay();

        $pending = ProductionLog::query()
            ->where('status', ProductionLogStatus::EnEspera)
            ->whereHas('productionOrder', fn ($q) => $q->whereIn('status', [
                ProductionOrderStatus::Pendiente->value,
                ProductionOrderStatus::EnProgreso->value,
            ]))
            ->count();

        $inProgress = ProductionLog::query()
            ->where('status', ProductionLogStatus::Procesando)
            ->where(function ($q) use ($userId): void {
                $q->where('user_id', $userId)->orWhereNull('user_id');
            })
            ->whereHas('productionOrder', fn ($q) => $q->whereIn('status', [
                ProductionOrderStatus::Pendiente->value,
                ProductionOrderStatus::EnProgreso->value,
            ]))
            ->count();

        $doneToday = ProductionLog::query()
            ->where('user_id', $userId)
            ->where('status', ProductionLogStatus::Terminado)
            ->where('ended_at', '>=', $todayStart)
            ->count();

        $avgSeconds = ProductionLog::query()
            ->where('user_id', $userId)
            ->where('status', ProductionLogStatus::Terminado)
            ->where('ended_at', '>=', $todayStart)
            ->whereNotNull('duration_seconds')
            ->avg('duration_seconds');

        $avgLabel = $avgSeconds === null
            ? '—'
            : (round(((float) $avgSeconds) / 60, 1).' min');

        $doneYesterday = ProductionLog::query()
            ->where('user_id', $userId)
            ->where('status', ProductionLogStatus::Terminado)
            ->whereBetween('ended_at', [now()->subDay()->startOfDay(), now()->subDay()->endOfDay()])
            ->count();

        $delta = $doneToday - $doneYesterday;
        $rateHint = $doneYesterday === 0 && $doneToday === 0
            ? 'Sin actividad aún'
            : ($delta >= 0
                ? '+'.$delta.' vs ayer'
                : $delta.' vs ayer');

        return [
            Stat::make('Pendientes en planta', number_format($pending))
                ->description('Etapas en espera')
                ->descriptionIcon('heroicon-m-clock')
                ->color('gray')
                ->url(EscaneoOperario::getUrl())
                ->extraAttributes(['class' => 'saas-stat']),
            Stat::make('En proceso', number_format($inProgress))
                ->description('Tus etapas activas')
                ->descriptionIcon('heroicon-m-play')
                ->color('warning')
                ->url(EscaneoOperario::getUrl())
                ->extraAttributes(['class' => 'saas-stat saas-stat--warning']),
            Stat::make('Terminadas hoy', number_format($doneToday))
                ->description($rateHint)
                ->descriptionIcon($delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color('success')
                ->extraAttributes(['class' => 'saas-stat saas-stat--success']),
            Stat::make('Tiempo prom. hoy', $avgLabel)
                ->description('Productividad por etapa')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color('info')
                ->extraAttributes(['class' => 'saas-stat saas-stat--info']),
        ];
    }
}
