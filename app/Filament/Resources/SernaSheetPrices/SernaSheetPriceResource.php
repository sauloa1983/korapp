<?php

namespace App\Filament\Resources\SernaSheetPrices;

use App\Enums\SernaSheetFinish;
use App\Filament\Concerns\RestrictsOperarioAccess;
use App\Filament\Resources\SernaSheetPrices\Pages\CreateSernaSheetPrice;
use App\Filament\Resources\SernaSheetPrices\Pages\EditSernaSheetPrice;
use App\Filament\Resources\SernaSheetPrices\Pages\ListSernaSheetPrices;
use App\Filament\Support\MoneyFormat;
use App\Models\SernaSheetPrice;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SernaSheetPriceResource extends Resource
{
    use RestrictsOperarioAccess;

    protected static ?string $model = SernaSheetPrice::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string|UnitEnum|null $navigationGroup = 'Lista de precios';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Láminas';

    protected static ?string $modelLabel = 'Lámina Serna';

    protected static ?string $pluralModelLabel = 'Láminas Serna';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('format')->label('Formato')->required()->placeholder('120x180'),
            TextInput::make('width_cm')->label('Ancho (cm)')->numeric()->required(),
            TextInput::make('height_cm')->label('Alto (cm)')->numeric()->required(),
            TextInput::make('thickness_mm')->label('Calibre (mm)')->numeric()->required(),
            Select::make('finish')->label('Acabado')->options(SernaSheetFinish::class)->required()->native(false),
            MoneyFormat::copInput('price', 'Precio')->required(),
            TextInput::make('price_source')->label('Fuente')->default('official')->maxLength(64),
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
                TextColumn::make('format')->label('Formato')->searchable()->sortable(),
                TextColumn::make('thickness_mm')->label('mm')->sortable(),
                TextColumn::make('finish')->label('Acabado'),
                MoneyFormat::column('price', 'Precio'),
                TextColumn::make('price_source')->label('Fuente')->toggleable(),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSernaSheetPrices::route('/'),
            'create' => CreateSernaSheetPrice::route('/create'),
            'edit' => EditSernaSheetPrice::route('/{record}/edit'),
        ];
    }
}
