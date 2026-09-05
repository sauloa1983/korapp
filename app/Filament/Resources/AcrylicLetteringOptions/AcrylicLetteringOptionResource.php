<?php

namespace App\Filament\Resources\AcrylicLetteringOptions;

use App\Enums\AcrylicLetteringPricingMode;
use App\Enums\AcrylicLetteringType;
use App\Filament\Resources\AcrylicLetteringOptions\Pages\CreateAcrylicLetteringOption;
use App\Filament\Resources\AcrylicLetteringOptions\Pages\EditAcrylicLetteringOption;
use App\Filament\Resources\AcrylicLetteringOptions\Pages\ListAcrylicLetteringOptions;
use App\Filament\Support\MoneyFormat;
use App\Models\AcrylicLetteringOption;
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

class AcrylicLetteringOptionResource extends Resource
{
    protected static ?string $model = AcrylicLetteringOption::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-language';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración Acrílico';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Letras / logo';

    protected static ?string $modelLabel = 'Opción de letras / logo';

    protected static ?string $pluralModelLabel = 'Letras / logo acrílico';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            Select::make('type')
                ->label('Tipo')
                ->options(AcrylicLetteringType::class)
                ->required()
                ->native(false)
                ->live(),
            Select::make('pricing_mode')
                ->label('Modo de cobro')
                ->options(AcrylicLetteringPricingMode::class)
                ->required()
                ->native(false)
                ->live(),
            MoneyFormat::copInput('price_per_m2', 'Precio por m² de letras')
                ->visible(fn ($get): bool => in_array($get('pricing_mode'), [
                    AcrylicLetteringPricingMode::CoverageArea->value,
                    AcrylicLetteringPricingMode::CoverageAreaPlusCut->value,
                ], true)),
            MoneyFormat::copInput('cut_price_per_meter', 'Precio por metro de corte')
                ->visible(fn ($get): bool => $get('pricing_mode') === AcrylicLetteringPricingMode::CoverageAreaPlusCut->value),
            MoneyFormat::copInput('fixed_price', 'Precio fijo')
                ->visible(fn ($get): bool => $get('pricing_mode') === AcrylicLetteringPricingMode::Fixed->value),
            MoneyFormat::copInput('price_per_letter', 'Precio por letra')
                ->helperText('Se multiplica por la cantidad de letras del aviso (10 letras cuestan el doble que 5).'),
            TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->integer()
                ->default(0),
            Toggle::make('is_active')
                ->label('Activa')
                ->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('type')->label('Tipo')->badge(),
                TextColumn::make('pricing_mode')->label('Modo')->badge(),
                MoneyFormat::column('price_per_m2', '$/m²'),
                MoneyFormat::column('price_per_letter', '$/letra'),
                MoneyFormat::column('cut_price_per_meter', '$/ml corte'),
                IconColumn::make('is_active')->label('Activa')->boolean(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    \Filament\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAcrylicLetteringOptions::route('/'),
            'create' => CreateAcrylicLetteringOption::route('/create'),
            'edit' => EditAcrylicLetteringOption::route('/{record}/edit'),
        ];
    }
}
