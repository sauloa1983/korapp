<?php

namespace App\Filament\Resources\AcrylicFinishOptions;

use App\Enums\AcrylicFinishPricingMode;
use App\Enums\AcrylicFinishType;
use App\Filament\Resources\AcrylicFinishOptions\Pages\CreateAcrylicFinishOption;
use App\Filament\Resources\AcrylicFinishOptions\Pages\EditAcrylicFinishOption;
use App\Filament\Resources\AcrylicFinishOptions\Pages\ListAcrylicFinishOptions;
use App\Filament\Support\MoneyFormat;
use App\Models\AcrylicFinishOption;
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

class AcrylicFinishOptionResource extends Resource
{
    protected static ?string $model = AcrylicFinishOption::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|UnitEnum|null $navigationGroup = 'Configuración Acrílico';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Acabados';

    protected static ?string $modelLabel = 'Acabado acrílico';

    protected static ?string $pluralModelLabel = 'Acabados acrílico';

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
                ->options(AcrylicFinishType::class)
                ->required()
                ->native(false),
            Select::make('pricing_mode')
                ->label('Modo de cobro')
                ->options(AcrylicFinishPricingMode::class)
                ->required()
                ->native(false),
            MoneyFormat::copInput('unit_price', 'Precio')->required(),
            TextInput::make('sort_order')
                ->label('Orden')
                ->numeric()
                ->integer()
                ->default(0),
            Toggle::make('is_active')
                ->label('Activo')
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
                MoneyFormat::column('unit_price', 'Precio'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
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
            'index' => ListAcrylicFinishOptions::route('/'),
            'create' => CreateAcrylicFinishOption::route('/create'),
            'edit' => EditAcrylicFinishOption::route('/{record}/edit'),
        ];
    }
}
