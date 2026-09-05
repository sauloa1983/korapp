<?php

namespace App\Filament\Resources\SernaProcessRates;

use App\Filament\Resources\SernaProcessRates\Pages\CreateSernaProcessRate;
use App\Filament\Resources\SernaProcessRates\Pages\EditSernaProcessRate;
use App\Filament\Resources\SernaProcessRates\Pages\ListSernaProcessRates;
use App\Filament\Concerns\RestrictsOperarioAccess;
use App\Models\SernaProcessRate;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SernaProcessRateResource extends Resource
{
    use RestrictsOperarioAccess;
    protected static ?string $model = SernaProcessRate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-scissors';

    protected static string|UnitEnum|null $navigationGroup = 'Lista de precios';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Tarifas cm²';

    protected static ?string $modelLabel = 'Tarifa Serna';

    protected static ?string $pluralModelLabel = 'Tarifas Serna';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('code')->label('Código')->required()->maxLength(64),
            TextInput::make('name')->label('Nombre')->required()->maxLength(255),
            TextInput::make('category')->label('Categoría')->required()->maxLength(64),
            TextInput::make('thickness_mm_min')->label('Calibre min (mm)')->numeric()->nullable(),
            TextInput::make('thickness_mm_max')->label('Calibre max (mm)')->numeric()->nullable(),
            TextInput::make('price_per_cm2')->label('$ / cm²')->numeric()->required()->step(0.0001),
            TextInput::make('min_charge')->label('Cargo mínimo')->numeric()->nullable(),
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
                TextColumn::make('code')->label('Código')->searchable(),
                TextColumn::make('name')->label('Nombre')->searchable(),
                TextColumn::make('category')->label('Categoría')->badge(),
                TextColumn::make('price_per_cm2')->label('$/cm²'),
                TextColumn::make('min_charge')->label('Mínimo'),
                IconColumn::make('is_active')->label('Activo')->boolean(),
            ])
            ->recordActions([
                \Filament\Actions\EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSernaProcessRates::route('/'),
            'create' => CreateSernaProcessRate::route('/create'),
            'edit' => EditSernaProcessRate::route('/{record}/edit'),
        ];
    }
}
