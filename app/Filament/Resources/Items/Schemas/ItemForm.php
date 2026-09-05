<?php

namespace App\Filament\Resources\Items\Schemas;

use App\Enums\ItemType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ItemForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->columns(2)
                    ->schema([
                        TextInput::make('sku')
                            ->label('SKU')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Select::make('type')
                            ->label('Tipo')
                            ->options(ItemType::class)
                            ->default(ItemType::MateriaPrima->value)
                            ->required()
                            ->helperText('Materia prima: se compra. Insumo: se compra o se fabrica en planta. Producto terminado: se fabrica y se vende.'),
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                        Select::make('item_category_id')
                            ->label('Categoría')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload(),
                        TextInput::make('unit_of_measure')
                            ->label('Unidad de medida')
                            ->required()
                            ->default('unidad'),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->default(true),
                    ]),
                Section::make('Inventario y costos')
                    ->columns(2)
                    ->schema([
                        TextInput::make('stock')
                            ->label('Existencia actual')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->step(0.0001),
                        TextInput::make('min_stock')
                            ->label('Existencia mínima')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->step(0.0001),
                        TextInput::make('cost')
                            ->label('Costo')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('$')
                            ->inputMode('decimal')
                            ->step(0.01),
                        TextInput::make('price')
                            ->label('Precio de venta')
                            ->numeric()
                            ->required()
                            ->default(0)
                            ->prefix('$')
                            ->inputMode('decimal')
                            ->step(0.01),
                    ]),
            ]);
    }
}
