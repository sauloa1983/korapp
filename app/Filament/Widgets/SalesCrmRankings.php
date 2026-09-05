<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Sale;
use Filament\Widgets\Widget;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class SalesCrmRankings extends Widget
{
    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.sales-crm-rankings';

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

    /** @return Collection<int, object{name: string, revenue: float, deals: int}> */
    public function getTopClients(): Collection
    {
        return Sale::query()
            ->select('customer_id', DB::raw('SUM(total) as revenue'), DB::raw('COUNT(*) as deals'))
            ->where('status', SaleStatus::Confirmada)
            ->whereNotNull('customer_id')
            ->where('sold_at', '>=', now()->subDays(90)->startOfDay())
            ->groupBy('customer_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(function ($row) {
                $customer = Customer::find($row->customer_id);

                return (object) [
                    'name' => $customer?->name ?? 'Cliente',
                    'revenue' => (float) $row->revenue,
                    'deals' => (int) $row->deals,
                ];
            });
    }

    /** @return Collection<int, object{name: string, deals: int, revenue: float}> */
    public function getTopSellers(): Collection
    {
        return Sale::query()
            ->select(
                'user_id',
                DB::raw('COUNT(*) as deals'),
                DB::raw('SUM(total) as revenue')
            )
            ->where('status', SaleStatus::Confirmada)
            ->where('sold_at', '>=', now()->subDays(90)->startOfDay())
            ->groupBy('user_id')
            ->orderByDesc('revenue')
            ->limit(5)
            ->with('user')
            ->get()
            ->map(fn ($row) => (object) [
                'name' => $row->user?->name ?? 'Sin vendedor',
                'deals' => (int) $row->deals,
                'revenue' => (float) $row->revenue,
            ]);
    }
}
