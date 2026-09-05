<?php

namespace App\Filament\Widgets;

use App\Enums\ItemType;
use App\Enums\ProductionOrderStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ProductionOrder;
use App\Models\Purchase;
use App\Models\Sale;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\Widget;

class DashboardSummaryCards extends Widget
{
    use InteractsWithPageFilters;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.widgets.dashboard-summary-cards';

    public static function canView(): bool
    {
        $user = auth()->user();

        return $user !== null && (
            $user->hasRole('super_admin')
            || $user->can('ViewAny:Sale')
            || $user->can('ViewAny:Customer')
        );
    }

    protected int | string | array $columnSpan = [
        'default' => 1,
        'lg' => 1,
        'xl' => 1,
    ];

    /**
     * @return array<int, array{label: string, value: string, hint: string, tone: string, icon: string}>
     */
    public function getCards(): array
    {
        $days = (int) ($this->pageFilters['period'] ?? 30);
        $from = now()->subDays($days - 1)->startOfDay();

        $activeCustomers = Customer::query()->where('is_active', true)->count();
        $salesCount = Sale::query()
            ->where('status', SaleStatus::Confirmada)
            ->whereBetween('sold_at', [$from, now()->endOfDay()])
            ->count();
        $purchaseSpend = (float) Purchase::query()
            ->whereBetween('ordered_at', [$from, now()->endOfDay()])
            ->sum('total');
        $completedOrders = ProductionOrder::query()
            ->where('status', ProductionOrderStatus::Completado->value)
            ->where('updated_at', '>=', $from)
            ->count();
        $healthyStock = Item::query()
            ->whereColumn('stock', '>', 'min_stock')
            ->where('type', '!=', ItemType::ProductoTerminado->value)
            ->count();

        return [
            [
                'label' => 'Clientes activos',
                'value' => number_format($activeCustomers),
                'hint' => 'En directorio',
                'tone' => 'indigo',
                'icon' => 'heroicon-o-users',
            ],
            [
                'label' => 'Ventas del periodo',
                'value' => number_format($salesCount),
                'hint' => 'Confirmadas',
                'tone' => 'green',
                'icon' => 'heroicon-o-shopping-bag',
            ],
            [
                'label' => 'Compras',
                'value' => money($purchaseSpend),
                'hint' => 'Gasto acumulado',
                'tone' => 'orange',
                'icon' => 'heroicon-o-truck',
            ],
            [
                'label' => 'Órdenes completadas',
                'value' => number_format($completedOrders),
                'hint' => $healthyStock . ' artículos en nivel adecuado',
                'tone' => 'blue',
                'icon' => 'heroicon-o-check-badge',
            ],
        ];
    }
}
