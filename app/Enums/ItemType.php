<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ItemType: string implements HasLabel, HasColor
{
    case MateriaPrima = 'materia_prima';
    case Insumo = 'insumo';
    case ProductoTerminado = 'producto_terminado';

    public function getLabel(): string
    {
        return match ($this) {
            self::MateriaPrima => 'Materia prima',
            self::Insumo => 'Insumo',
            self::ProductoTerminado => 'Producto terminado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::MateriaPrima => 'warning',
            self::Insumo => 'info',
            self::ProductoTerminado => 'success',
        };
    }

    /**
     * Tipos que pueden ser consumidos como entrada de una orden de producción.
     *
     * @return array<int, string>
     */
    public static function consumableValues(): array
    {
        return [self::MateriaPrima->value, self::Insumo->value];
    }

    /**
     * Tipos que la empresa puede fabricar en una orden de producción
     * (producto terminado o insumos hechos en casa).
     *
     * @return array<int, string>
     */
    public static function producibleValues(): array
    {
        return [self::ProductoTerminado->value, self::Insumo->value];
    }

    public function isProducible(): bool
    {
        return in_array($this->value, self::producibleValues(), true);
    }

    public function isConsumable(): bool
    {
        return in_array($this->value, self::consumableValues(), true);
    }
}
