<?php

namespace App\Filament\Pages;

use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Models\Quote;
use App\Models\Sale;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class Reportes extends Page
{
    protected string $view = 'filament.pages.reportes';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar';

    protected static string|UnitEnum|null $navigationGroup = 'Reportes';

    protected static ?string $title = 'Reportes';

    protected static ?string $navigationLabel = 'Reportes';

    public ?string $from = null;

    public ?string $to = null;

    /** Solo administradores acceden a los reportes. */
    public static function canAccess(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        if ($user === null) {
            return false;
        }

        return $user->hasRole('super_admin')
            || $user->can('View:Reportes');
    }

    public function mount(): void
    {
        $this->from = now()->startOfMonth()->toDateString();
        $this->to = now()->toDateString();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportSales')
                ->label('Exportar ventas (CSV)')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(fn (): string => route('reports.export.sales', ['from' => $this->from, 'to' => $this->to]))
                ->openUrlInNewTab(),
        ];
    }

    // -----------------------------------------------------------------
    // Rango de fechas
    // -----------------------------------------------------------------

    private function fromDate(): Carbon
    {
        return Carbon::parse($this->from ?: now()->startOfMonth())->startOfDay();
    }

    private function toDate(): Carbon
    {
        return Carbon::parse($this->to ?: now())->endOfDay();
    }

    /**
     * Periodo anterior de la misma duración (para % crecimiento).
     *
     * @return array{0: Carbon, 1: Carbon}
     */
    private function previousPeriod(): array
    {
        $from = $this->fromDate();
        $to = $this->toDate();
        $days = max(1, (int) $from->diffInDays($to) + 1);

        $prevTo = $from->copy()->subDay()->endOfDay();
        $prevFrom = $prevTo->copy()->subDays($days - 1)->startOfDay();

        return [$prevFrom, $prevTo];
    }

    // -----------------------------------------------------------------
    // Datos de reporte
    // -----------------------------------------------------------------

    /**
     * @return array{
     *     count: int,
     *     total: float,
     *     avg_ticket: float,
     *     iva: float,
     *     customers: int,
     *     previous_total: float,
     *     growth_percent: float|null,
     *     quotes_accepted: int,
     *     quote_to_sale_percent: float|null
     * }
     */
    public function getSummary(): array
    {
        $from = $this->fromDate();
        $to = $this->toDate();

        $sales = Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$from, $to])
            ->get();

        $total = (float) $sales->sum('total');
        $count = $sales->count();

        [$prevFrom, $prevTo] = $this->previousPeriod();
        $previousTotal = (float) Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$prevFrom, $prevTo])
            ->sum('total');

        $growthPercent = null;
        if ($previousTotal > 0) {
            $growthPercent = round((($total - $previousTotal) / $previousTotal) * 100, 1);
        } elseif ($total > 0) {
            $growthPercent = 100.0;
        }

        $quotesAccepted = Quote::query()
            ->where('status', QuoteStatus::Accepted)
            ->whereBetween('updated_at', [$from, $to])
            ->pluck('id');

        $quotesAcceptedCount = $quotesAccepted->count();

        $quotesConverted = $quotesAcceptedCount > 0
            ? Sale::query()
                ->where('status', SaleStatus::Confirmada)
                ->whereIn('quote_id', $quotesAccepted)
                ->pluck('quote_id')
                ->unique()
                ->count()
            : 0;

        $quoteToSalePercent = $quotesAcceptedCount > 0
            ? round(($quotesConverted / $quotesAcceptedCount) * 100, 1)
            : null;

        return [
            'count' => $count,
            'total' => $total,
            'avg_ticket' => $count > 0 ? round($total / $count, 2) : 0.0,
            'iva' => (float) $sales->sum('iva_amount'),
            'customers' => $sales->whereNotNull('customer_id')->pluck('customer_id')->unique()->count(),
            'previous_total' => $previousTotal,
            'growth_percent' => $growthPercent,
            'quotes_accepted' => $quotesAcceptedCount,
            'quotes_converted' => $quotesConverted,
            'quote_to_sale_percent' => $quoteToSalePercent,
        ];
    }

    public function getSalesByDay(): Collection
    {
        return Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$this->fromDate(), $this->toDate()])
            ->get()
            ->groupBy(fn (Sale $sale): string => optional($sale->sold_at)->toDateString() ?? '—')
            ->map(fn (Collection $group, string $date): array => [
                'date' => $date,
                'count' => $group->count(),
                'total' => (float) $group->sum('total'),
            ])
            ->sortKeys()
            ->values();
    }

    /** Resumen de ventas confirmadas agrupadas por vendedor. */
    public function getSalesBySeller(): Collection
    {
        return Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$this->fromDate(), $this->toDate()])
            ->with('user')
            ->get()
            ->groupBy(fn (Sale $sale): int|string => $sale->user_id ?: 'none')
            ->map(function (Collection $group): array {
                $count = $group->count();
                $total = (float) $group->sum('total');

                return [
                    'seller' => $group->first()->user?->name ?? 'Sin vendedor',
                    'count' => $count,
                    'total' => $total,
                    'avg_ticket' => $count > 0 ? round($total / $count, 2) : 0.0,
                ];
            })
            ->sortByDesc('total')
            ->values();
    }
}
