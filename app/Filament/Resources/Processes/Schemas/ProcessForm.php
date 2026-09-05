<?php

namespace App\Filament\Resources\Processes\Schemas;

use App\Enums\ProcessDepartment;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProcessForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre de la etapa')
                    ->required()
                    ->maxLength(255)
                    ->helperText('Ej: Plotter de corte, Grabado, Revisión.'),
                Select::make('department')
                    ->label('Departamento')
                    ->options(ProcessDepartment::class)
                    ->native(false)
                    ->nullable()
                    ->helperText('Área de la OP impresa (Impresión, Láser, etc.).'),
                TextInput::make('sort_order')
                    ->label('Orden estimado')
                    ->numeric()
                    ->default(0)
                    ->helperText('Define la posición de la etapa dentro del flujo de producción.'),
                TextInput::make('estimated_minutes')
                    ->label('Minutos estimados')
                    ->numeric()
                    ->suffix('min'),
                ColorPicker::make('color')
                    ->label('Color'),
                Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->label('Activa')
                    ->default(true)
                    ->helperText('Solo las etapas activas se incluyen al generar el flujo de una orden.'),
            ]);
    }
}
