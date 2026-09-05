<?php

namespace App\Filament\Resources\Items\RelationManagers;

use App\Enums\StockMovementType;
use App\Support\ModelLabels;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsRelationManager extends RelationManager
{
    protected static string $relationship = 'stockMovements';

    protected static ?string $title = 'Kardex (movimientos)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('balance_after')
                    ->label('Saldo')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->placeholder('—')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('source_type')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => ModelLabels::forType($state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->placeholder('—'),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema'),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(StockMovementType::class),
            ]);
    }
}
