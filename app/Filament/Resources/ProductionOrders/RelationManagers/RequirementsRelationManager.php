<?php

namespace App\Filament\Resources\ProductionOrders\RelationManagers;

use App\Enums\ItemType;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class RequirementsRelationManager extends RelationManager
{
    protected static string $relationship = 'requirements';

    protected static ?string $title = 'Requerimientos (consumo de inventario)';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->label('Materia prima / Insumo')
                    ->relationship(
                        name: 'item',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn('type', ItemType::consumableValues()),
                    )
                    ->searchable()
                    ->preload()
                    ->required()
                    ->helperText('Usa materia prima comprada o insumos fabricados/comprados por la empresa.'),
                TextInput::make('quantity_required')
                    ->label('Cantidad requerida')
                    ->numeric()
                    ->required()
                    ->step(0.0001),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('item.sku')
                    ->label('SKU'),
                TextColumn::make('item.name')
                    ->label('Artículo')
                    ->searchable(),
                TextColumn::make('item.type')
                    ->label('Tipo')
                    ->badge(),
                TextColumn::make('quantity_required')
                    ->label('Requerido')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('quantity_consumed')
                    ->label('Consumido')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('item.stock')
                    ->label('Existencia disponible')
                    ->numeric(decimalPlaces: 2)
                    ->color(fn ($record): string => (float) $record->item?->stock < (float) $record->quantity_required ? 'danger' : 'success'),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
