<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum PurchaseStatus: string implements HasLabel, HasColor
{
    case Borrador = 'borrador';
    case Recibida = 'recibida';
    case Devuelta = 'devuelta';
    case Cancelada = 'cancelada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Recibida => 'Recibida',
            self::Devuelta => 'Devuelta',
            self::Cancelada => 'Cancelada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Recibida => 'success',
            self::Devuelta => 'warning',
            self::Cancelada => 'danger',
        };
    }
}
