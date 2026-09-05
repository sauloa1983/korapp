<?php

namespace App\Filament\Widgets;

use App\Enums\ItemType;
use App\Models\Item;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class LowStockItemsTable extends TableWidget
{
    protected static ?string $heading = 'Inventario bajo mínimo';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Inventario bajo mínimo')
            ->description('Materia prima e insumos que requieren reabastecimiento')
            ->query(
                fn (): Builder => Item::query()
                    ->whereColumn('stock', '<=', 'min_stock')
                    ->where('type', '!=', ItemType::ProductoTerminado->value)
            )
            ->emptyStateHeading('No hay datos para mostrar')
            ->emptyStateDescription('Todo el inventario está por encima del mínimo. ¡Buen trabajo!')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->columns([
                TextColumn::make('sku')
                    ->label('SKU')
                    ->color('gray'),
                TextColumn::make('name')
                    ->label('Artículo')
                    ->searchable()
                    ->weight('medium'),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('stock')
                    ->label('Existencia')
                    ->numeric(decimalPlaces: 2)
                    ->color('warning')
                    ->weight('bold'),
                TextColumn::make('min_stock')
                    ->label('Mínimo')
                    ->numeric(decimalPlaces: 2)
                    ->color('gray'),
                TextColumn::make('unit_of_measure')
                    ->label('Unidad')
                    ->color('gray'),
            ])
            ->paginated([5, 10]);
    }
}
