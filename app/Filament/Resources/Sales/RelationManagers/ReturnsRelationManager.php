<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ReturnsRelationManager extends RelationManager
{
    protected static string $relationship = 'returns';

    protected static ?string $title = 'Devoluciones (notas crédito)';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')
                    ->label('Nota crédito')
                    ->weight('bold'),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i'),
                TextColumn::make('items_count')
                    ->label('Líneas')
                    ->counts('items')
                    ->badge(),
                TextColumn::make('total')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => money($state)),
                TextColumn::make('warehouse.name')
                    ->label('Bodega')
                    ->placeholder('—'),
                TextColumn::make('reason')
                    ->label('Motivo')
                    ->placeholder('—'),
            ]);
    }
}
