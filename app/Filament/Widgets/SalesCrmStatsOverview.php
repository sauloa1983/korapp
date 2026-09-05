<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStage;
use App\Enums\SaleStatus;
use App\Models\Lead;
use App\Models\Sale;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalesCrmStatsOverview extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Estadísticas CRM';

    protected ?string $pollingInterval = null;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole('super_admin')
            || $user->can('ViewAny:Sale')
            || $user->can('ViewAny:Lead')
        );
    }

    protected function getStats(): array
    {
        $from = now()->subDays(29)->startOfDay();

        $revenue = (float) Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$from, now()->endOfDay()])
            ->sum('total');

        $salesCount = Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$from, now()->endOfDay()])
            ->count();

        $avgTicket = $salesCount > 0 ? round($revenue / $salesCount, 2) : 0.0;

        $iva = (float) Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$from, now()->endOfDay()])
            ->sum('iva_amount');

        $dealsOpen = Lead::query()
            ->whereNotIn('stage', [LeadStage::Won, LeadStage::Lost])
            ->count();

        $won = Lead::query()
            ->where('stage', LeadStage::Won)
            ->where('updated_at', '>=', $from)
            ->count();

        $closed = Lead::query()
            ->whereIn('stage', [LeadStage::Won, LeadStage::Lost])
            ->where('updated_at', '>=', $from)
            ->count();

        $conversion = $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0;

        $pipelineValue = (float) Lead::query()
            ->whereNotIn('stage', [LeadStage::Won, LeadStage::Lost])
            ->sum('value');

        return [
            Stat::make('Ingresos (30 días)', money($revenue))
                ->description($salesCount.' venta'.($salesCount === 1 ? '' : 's').' · ticket '.money($avgTicket))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('IVA cobrado (30 días)', money($iva))
                ->description('En ventas confirmadas')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('warning'),
            Stat::make('Oportunidades abiertas', (string) $dealsOpen)
                ->description(money($pipelineValue).' en embudo')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary'),
            Stat::make('Tasa de conversión', $conversion.'%')
                ->description("{$won} ganadas / {$closed} cerradas")
                ->descriptionIcon('heroicon-m-arrow-trending-up')
                ->color('info'),
        ];
    }
}
