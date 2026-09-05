<?php

namespace App\Filament\Resources\StockMovements\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StockMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->disabled()
            ->components([
                TextInput::make('item.name')->label('Artículo'),
                TextInput::make('type')->label('Tipo'),
                TextInput::make('quantity')->label('Cantidad'),
                TextInput::make('balance_after')->label('Saldo resultante'),
                TextInput::make('unit_cost')->label('Costo unitario')->prefix('$')->numeric()->inputMode('decimal')->step(0.01),
                TextInput::make('reference')->label('Referencia'),
            ]);
    }
}
