<?php

namespace App\Filament\Widgets;

use App\Enums\LeadStage;
use App\Enums\SaleStatus;
use App\Models\Lead;
use App\Models\Sale;
use App\Support\CommercialScope;
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

    public function getHeading(): ?string
    {
        return CommercialScope::seesOnlyOwnData()
            ? 'Tu resumen comercial'
            : 'Estadísticas CRM';
    }

    protected function getStats(): array
    {
        $from = now()->subDays(29)->startOfDay();

        $sales = CommercialScope::constrain(
            Sale::query()
                ->where('status', SaleStatus::Confirmada)
                ->whereBetween('sold_at', [$from, now()->endOfDay()])
        );

        $revenue = (float) (clone $sales)->sum('total');
        $salesCount = (clone $sales)->count();
        $avgTicket = $salesCount > 0 ? round($revenue / $salesCount, 2) : 0.0;
        $iva = (float) (clone $sales)->sum('iva_amount');

        $leadsOpen = CommercialScope::constrain(
            Lead::query()->whereNotIn('stage', [LeadStage::Won, LeadStage::Lost])
        );

        $dealsOpen = (clone $leadsOpen)->count();
        $pipelineValue = (float) (clone $leadsOpen)->sum('value');

        $won = CommercialScope::constrain(
            Lead::query()
                ->where('stage', LeadStage::Won)
                ->where('updated_at', '>=', $from)
        )->count();

        $closed = CommercialScope::constrain(
            Lead::query()
                ->whereIn('stage', [LeadStage::Won, LeadStage::Lost])
                ->where('updated_at', '>=', $from)
        )->count();

        $conversion = $closed > 0 ? round(($won / $closed) * 100, 1) : 0.0;

        $own = CommercialScope::seesOnlyOwnData();

        return [
            Stat::make($own ? 'Tus ingresos (30 días)' : 'Ingresos (30 días)', money($revenue))
                ->description($salesCount.' venta'.($salesCount === 1 ? '' : 's').' · ticket '.money($avgTicket))
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make($own ? 'Tu IVA (30 días)' : 'IVA cobrado (30 días)', money($iva))
                ->description('En ventas confirmadas')
                ->descriptionIcon('heroicon-m-receipt-percent')
                ->color('warning'),
            Stat::make($own ? 'Tus oportunidades' : 'Oportunidades abiertas', (string) $dealsOpen)
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
