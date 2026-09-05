<?php

namespace App\Filament\Support;

use App\Support\Money;
use Filament\Forms\Components\TextInput;
use Filament\Support\RawJs;
use Filament\Tables\Columns\TextColumn;

class MoneyFormat
{
    public static function column(string $name, string $label = 'Total'): TextColumn
    {
        return TextColumn::make($name)
            ->label($label)
            ->alignEnd()
            ->formatStateUsing(fn ($state): string => Money::format($state))
            ->sortable();
    }

    public static function input(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->prefix('$')
            ->inputMode('decimal')
            ->step(0.01);
    }

    /**
     * Input COP con formato en tiempo real (1.234.567), sin decimales.
     * Usa máscara Alpine ($money) para no esperar debounce/blur de Livewire.
     */
    public static function copInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->prefix('$')
            ->placeholder('0')
            // decimal=',' (no se usa con precision 0), miles='.' → 1.234.567
            // No usar stripCharacters('.'): al hidratar "95000.00" de BD queda "9500000".
            ->mask(RawJs::make('$money($input, \',\', \'.\', 0)'))
            ->live(debounce: 500)
            ->formatStateUsing(fn ($state): ?string => Money::formatInputState($state))
            ->dehydrateStateUsing(fn ($state): ?float => Money::parseInput($state))
            ->rule(fn (): \Closure => function (string $attribute, $value, \Closure $fail): void {
                if ($value === null || $value === '') {
                    return;
                }

                $parsed = Money::parseInput($value);

                if ($parsed === null) {
                    $fail('Ingresa un monto válido.');

                    return;
                }

                if ($parsed < 0) {
                    $fail('El monto no puede ser negativo.');
                }
            })
            ->extraInputAttributes([
                'inputmode' => 'numeric',
                'style' => 'font-variant-numeric: tabular-nums;',
            ]);
    }

    public static function display(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->disabled()
            ->dehydrated(false)
            ->prefix('$')
            ->formatStateUsing(fn ($state): string => number_format((float) ($state ?? 0), 0, ',', '.'));
    }
}
