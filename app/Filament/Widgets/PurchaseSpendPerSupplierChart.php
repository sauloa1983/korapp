<?php

namespace App\Filament\Widgets;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class PurchaseSpendPerSupplierChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Gasto por proveedor (compras recibidas)';

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        /** @var \App\Models\User|null $user */
        $user = Auth::user();

        return $user !== null && $user->hasRole('super_admin');
    }

    protected function getData(): array
    {
        $rows = Purchase::query()
            ->where('status', PurchaseStatus::Recibida)
            ->with('supplier')
            ->get()
            ->groupBy('supplier_id')
            ->map(fn ($group): array => [
                'supplier' => $group->first()->supplier?->name ?? 'Sin proveedor',
                'total' => (float) $group->sum('total'),
            ])
            ->sortByDesc('total')
            ->take(10)
            ->values();

        return [
            'datasets' => [
                [
                    'label' => 'Gasto',
                    'data' => $rows->pluck('total')->all(),
                    'backgroundColor' => '#f59e0b',
                    'borderColor' => '#d97706',
                ],
            ],
            'labels' => $rows->pluck('supplier')->all(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
