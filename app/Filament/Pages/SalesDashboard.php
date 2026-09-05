<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasSalesAccess;
use App\Filament\Widgets\SalesCrmRankings;
use App\Filament\Widgets\SalesCrmStatsOverview;
use App\Filament\Widgets\SalesCrmTrendChart;
use App\Filament\Widgets\UpcomingVisitsTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Widgets\Widget;
use Filament\Widgets\WidgetConfiguration;
use UnitEnum;

class SalesDashboard extends Page
{
    use HasSalesAccess;

    protected string $view = 'filament.pages.sales-dashboard';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chart-bar-square';

    protected static string|UnitEnum|null $navigationGroup = 'Ventas';

    protected static ?string $navigationLabel = 'Panel de ventas';

    protected static ?string $title = 'Panel de ventas';

    protected static ?string $slug = 'dashboard-ventas';

    protected static ?int $navigationSort = 1;

    public static function canAccess(): bool
    {
        return static::canAccessSalesModule();
    }

    public function getHeading(): string
    {
        return 'Panel de ventas';
    }

    public function getSubheading(): ?string
    {
        return 'Ingresos, oportunidades y conversión del embudo comercial.';
    }

    /**
     * @return array<class-string<Widget> | WidgetConfiguration>
     */
    public function getWidgets(): array
    {
        return [
            SalesCrmStatsOverview::class,
            SalesCrmTrendChart::class,
            UpcomingVisitsTable::class,
            SalesCrmRankings::class,
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
            'xl' => 2,
        ];
    }
}
