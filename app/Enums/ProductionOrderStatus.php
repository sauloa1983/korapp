<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum ProductionOrderStatus: string implements HasLabel, HasColor
{
    case Pendiente = 'pendiente';
    case EnProgreso = 'en_progreso';
    case Completado = 'completado';
    case Entregado = 'entregado';
    case Cancelado = 'cancelado';

    public function getLabel(): string
    {
        return match ($this) {
            self::Pendiente => 'Pendiente',
            self::EnProgreso => 'En progreso',
            self::Completado => 'Completado',
            self::Entregado => 'Entregado',
            self::Cancelado => 'Cancelado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Pendiente => 'gray',
            self::EnProgreso => 'warning',
            self::Completado => 'success',
            self::Entregado => 'primary',
            self::Cancelado => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Pendiente, self::EnProgreso], true);
    }

    public function canMarkDelivered(): bool
    {
        return $this === self::Completado;
    }
}
