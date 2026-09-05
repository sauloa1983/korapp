<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\DashboardStatsOverview;
use App\Filament\Widgets\DashboardSummaryCards;
use App\Filament\Widgets\LowStockItemsTable;
use App\Filament\Widgets\SalesTrendChart;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static string|UnitEnum|null $navigationGroup = 'General';

    protected static ?int $navigationSort = -1;

    public static function getNavigationLabel(): string
    {
        return 'Inicio';
    }

    public function getTitle(): string | Htmlable
    {
        return 'Inicio';
    }

    public function getHeading(): string | Htmlable
    {
        $name = auth()->user()?->name ?? 'Administrador';

        return "Bienvenido, {$name}";
    }

    public function getSubheading(): string | Htmlable | null
    {
        return 'Resumen claro de ventas, clientes, inventario y producción.';
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            DashboardStatsOverview::class,
            SalesTrendChart::class,
            DashboardSummaryCards::class,
            LowStockItemsTable::class,
        ];
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 2,
            'xl' => 3,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('period')
                    ->label('Periodo')
                    ->options([
                        '7' => 'Últimos 7 días',
                        '30' => 'Este mes',
                        '90' => 'Últimos 90 días',
                    ])
                    ->default('30')
                    ->selectablePlaceholder(false)
                    ->live()
                    ->extraAttributes(['class' => 'saas-period-filter']),
            ]);
    }
}
