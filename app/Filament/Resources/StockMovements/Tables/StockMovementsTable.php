<?php

namespace App\Filament\Resources\StockMovements\Tables;

use App\Enums\StockMovementType;
use App\Support\ModelLabels;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StockMovementsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('item.name')
                    ->label('Artículo')
                    ->description(fn ($record): ?string => $record->item?->sku)
                    ->searchable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->sortable(),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('balance_after')
                    ->label('Saldo')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('source_type')
                    ->label('Origen')
                    ->formatStateUsing(fn (?string $state): string => ModelLabels::forType($state))
                    ->badge()
                    ->color('gray'),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Usuario')
                    ->placeholder('Sistema')
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(StockMovementType::class),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
