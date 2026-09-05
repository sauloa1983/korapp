<?php

namespace App\Filament\Resources\Items\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class WarehouseStockRelationManager extends RelationManager
{
    protected static string $relationship = 'warehouses';

    protected static ?string $title = 'Existencias por bodega';

    public function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código'),
                TextColumn::make('name')
                    ->label('Bodega')
                    ->searchable(),
                TextColumn::make('pivot.stock')
                    ->label('Existencia')
                    ->numeric(decimalPlaces: 2)
                    ->weight('bold'),
            ]);
    }
}
