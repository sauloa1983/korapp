<?php

namespace App\Filament\Resources\AcrylicLightingOptions;

use App\Enums\AcrylicLightingPricingMode;
use App\Filament\Concerns\RestrictsOperarioAccess;
use App\Filament\Resources\AcrylicLightingOptions\Pages\CreateAcrylicLightingOption;
use App\Filament\Resources\AcrylicLightingOptions\Pages\EditAcrylicLightingOption;
use App\Filament\Resources\AcrylicLightingOptions\Pages\ListAcrylicLightingOptions;
use App\Filament\Support\MoneyFormat;
use App\Models\AcrylicLightingOption;
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

class AcrylicLightingOptionResource extends Resource
{
    use RestrictsOperarioAccess;

    protected static ?string $model = AcrylicLightingOption::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string|UnitEnum|null $navigationGroup = 'Lista de precios';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Iluminación / LED';

    protected static ?string $modelLabel = 'Opción de iluminación';

    protected static ?string $pluralModelLabel = 'Iluminación / LED';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            Select::make('pricing_mode')
                ->label('Modo de cobro')
                ->options(AcrylicLightingPricingMode::class)
                ->required()
                ->native(false)
                ->live()
                ->helperText(fn ($get): string => match ($get('pricing_mode')) {
                    AcrylicLightingPricingMode::None->value,
                    AcrylicLightingPricingMode::None => 'No cobra iluminación ni fuente de alimentación (PSU).',
                    AcrylicLightingPricingMode::PerMeter->value,
                    AcrylicLightingPricingMode::PerMeter => 'Perímetro (m) × precio. Ej. 4 m × $28.000 = $112.000. La PSU se suma aparte.',
                    AcrylicLightingPricingMode::PerSquareMeter->value,
                    AcrylicLightingPricingMode::PerSquareMeter => 'Área (m²) × precio. Ej. 1,2 m² × $95.000 = $114.000. La PSU se suma aparte.',
                    AcrylicLightingPricingMode::Fixed->value,
                    AcrylicLightingPricingMode::Fixed => 'Precio fijo por aviso, sin medir. Ej. $180.000. La PSU se suma aparte.',
                    default => 'Elige cómo se aplica el precio unitario en la cotización.',
                }),
            MoneyFormat::copInput('unit_price', 'Precio unitario')->required(),
            MoneyFormat::copInput('power_supply_cost', 'Fuente de alimentación')
                ->required()
                ->helperText('Costo de la PSU. Se suma una vez por aviso cuando hay iluminación. Ej. $45.000.'),
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
                TextColumn::make('pricing_mode')->label('Modo')->badge(),
                MoneyFormat::column('unit_price', 'Precio'),
                MoneyFormat::column('power_supply_cost', 'Fuente de alimentación (PSU)'),
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
            'index' => ListAcrylicLightingOptions::route('/'),
            'create' => CreateAcrylicLightingOption::route('/create'),
            'edit' => EditAcrylicLightingOption::route('/{record}/edit'),
        ];
    }
}
