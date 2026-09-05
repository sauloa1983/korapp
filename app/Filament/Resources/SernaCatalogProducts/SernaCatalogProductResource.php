<?php

namespace App\Filament\Resources\SernaCatalogProducts;

use App\Filament\Concerns\RestrictsOperarioAccess;
use App\Filament\Resources\SernaCatalogProducts\Pages\CreateSernaCatalogProduct;
use App\Filament\Resources\SernaCatalogProducts\Pages\EditSernaCatalogProduct;
use App\Filament\Resources\SernaCatalogProducts\Pages\ListSernaCatalogProducts;
use App\Filament\Support\MoneyFormat;
use App\Models\SernaCatalogProduct;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SernaCatalogProductResource extends Resource
{
    use RestrictsOperarioAccess;

    protected static ?string $model = SernaCatalogProduct::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|UnitEnum|null $navigationGroup = 'Lista de precios';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Productos';

    protected static ?string $modelLabel = 'Producto Serna';

    protected static ?string $pluralModelLabel = 'Productos Serna';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('sku')->label('SKU')->required()->maxLength(64),
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('category')->label('Categoría')->required()->maxLength(64),
            MoneyFormat::copInput('unit_price', 'Precio unitario')->required(),
            TextInput::make('pricing_mode')->label('Modo')->default('fixed')->required(),
            TextInput::make('year')->label('Año')->numeric()->default(2026)->required(),
            TextInput::make('sort_order')->label('Orden')->numeric()->integer()->default(0),
            Toggle::make('is_active')->label('Activo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sku')->label('SKU')->searchable(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('category')->label('Categoría')->badge(),
                MoneyFormat::column('unit_price', 'Precio'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSernaCatalogProducts::route('/'),
            'create' => CreateSernaCatalogProduct::route('/create'),
            'edit' => EditSernaCatalogProduct::route('/{record}/edit'),
        ];
    }
}
