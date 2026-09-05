<?php

namespace App\Filament\Resources\Purchases\RelationManagers;

use App\Enums\ItemType;
use App\Enums\PurchaseStatus;
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

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Líneas de compra';

    /** No se editan líneas de una compra ya recibida. */
    protected function canModify(): bool
    {
        return $this->getOwnerRecord()->status === PurchaseStatus::Borrador;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->label('Artículo')
                    ->relationship(
                        name: 'item',
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => $query->whereIn('type', ItemType::consumableValues()),
                    )
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->required()
                    ->step(0.0001),
                TextInput::make('unit_cost')
                    ->label('Costo unitario')
                    ->numeric()
                    ->required()
                    ->prefix('$')
                    ->inputMode('decimal')
                    ->step(0.01),
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
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('unit_cost')
                    ->label('Costo unit.')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => money($state)),
                TextColumn::make('subtotal')
                    ->label('Subtotal')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => money($state))
                    ->state(fn ($record): float => $record->subtotal),
            ])
            ->headerActions([
                CreateAction::make()
                    ->visible(fn (): bool => $this->canModify()),
            ])
            ->recordActions([
                EditAction::make()
                    ->visible(fn (): bool => $this->canModify()),
                DeleteAction::make()
                    ->visible(fn (): bool => $this->canModify()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
