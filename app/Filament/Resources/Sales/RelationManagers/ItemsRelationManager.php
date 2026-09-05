<?php

namespace App\Filament\Resources\Sales\RelationManagers;

use App\Enums\SaleStatus;
use App\Models\Item;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ItemsRelationManager extends RelationManager
{
    protected static string $relationship = 'items';

    protected static ?string $title = 'Líneas de venta';

    protected function canModify(): bool
    {
        $sale = $this->getOwnerRecord();

        return method_exists($sale, 'isEditable')
            ? $sale->isEditable()
            : $sale->status === SaleStatus::Borrador;
    }

    public function isReadOnly(): bool
    {
        return ! $this->canModify();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('item_id')
                    ->label('Artículo')
                    ->relationship('item', 'name')
                    ->searchable()
                    ->preload()
                    ->required()
                    // Autocompleta el precio de venta del item elegido.
                    ->afterStateUpdated(function ($state, callable $set): void {
                        if ($state && $item = Item::find($state)) {
                            $set('unit_price', $item->price);
                        }
                    })
                    ->live(),
                Textarea::make('description')
                    ->label('Descripción')
                    ->rows(2)
                    ->columnSpanFull(),
                TextInput::make('quantity')
                    ->label('Cantidad')
                    ->numeric()
                    ->required()
                    ->default(1)
                    ->step(0.0001),
                TextInput::make('unit_price')
                    ->label('Precio unitario')
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
            ->selectable(fn (): bool => $this->canModify())
            ->columns([
                TextColumn::make('display_sku')
                    ->label('Tipo')
                    ->state(fn ($record): string => $record->displaySku())
                    ->wrap()
                    ->limit(40),
                TextColumn::make('display_description')
                    ->label('Descripción')
                    ->state(fn ($record): string => $record->displayDescription())
                    ->wrap()
                    ->searchable(query: function ($query, string $search): void {
                        $query->where('description', 'like', "%{$search}%")
                            ->orWhereHas('item', fn ($q) => $q->where('name', 'like', "%{$search}%")
                                ->orWhere('sku', 'like', "%{$search}%"));
                    }),
                TextColumn::make('quantity')
                    ->label('Cantidad')
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('unit_price')
                    ->label('Precio unit.')
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
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => $this->canModify()),
                ]),
            ]);
    }
}
