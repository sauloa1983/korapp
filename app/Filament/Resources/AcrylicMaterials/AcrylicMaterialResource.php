<?php

namespace App\Filament\Resources\AcrylicMaterials;

use App\Filament\Concerns\RestrictsToSuperAdmin;
use App\Filament\Resources\AcrylicMaterials\Pages\CreateAcrylicMaterial;
use App\Filament\Resources\AcrylicMaterials\Pages\EditAcrylicMaterial;
use App\Filament\Resources\AcrylicMaterials\Pages\ListAcrylicMaterials;
use App\Filament\Support\MoneyFormat;
use App\Models\AcrylicMaterial;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class AcrylicMaterialResource extends Resource
{
    use RestrictsToSuperAdmin;

    protected static ?string $model = AcrylicMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-square-3-stack-3d';

    protected static string|UnitEnum|null $navigationGroup = 'Catálogo';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Materiales';

    protected static ?string $modelLabel = 'Material acrílico';

    protected static ?string $pluralModelLabel = 'Materiales acrílico';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Nombre')
                ->required()
                ->maxLength(255),
            TextInput::make('thickness_mm')
                ->label('Espesor (mm)')
                ->numeric()
                ->required()
                ->minValue(0.1)
                ->step(0.1),
            MoneyFormat::copInput('price_per_m2', 'Precio por m²')->required(),
            TextInput::make('waste_percent')
                ->label('Desperdicio (%)')
                ->numeric()
                ->required()
                ->minValue(0)
                ->maxValue(100)
                ->suffix('%')
                ->default(10),
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
                TextColumn::make('thickness_mm')->label('mm')->sortable()->toggleable(),
                MoneyFormat::column('price_per_m2', '$/m²')->toggleable(),
                TextColumn::make('waste_percent')->label('Desperdicio %')->toggleable(),
                IconColumn::make('is_active')->label('Activo')->boolean()->toggleable(),
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
            'index' => ListAcrylicMaterials::route('/'),
            'create' => CreateAcrylicMaterial::route('/create'),
            'edit' => EditAcrylicMaterial::route('/{record}/edit'),
        ];
    }
}
