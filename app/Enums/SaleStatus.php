<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SaleStatus: string implements HasLabel, HasColor
{
    case Borrador = 'borrador';
    case Confirmada = 'confirmada';
    case Devuelta = 'devuelta';
    case Anulada = 'anulada';

    public function getLabel(): string
    {
        return match ($this) {
            self::Borrador => 'Borrador',
            self::Confirmada => 'Confirmada',
            self::Devuelta => 'Devuelta',
            self::Anulada => 'Anulada',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Borrador => 'gray',
            self::Confirmada => 'success',
            self::Devuelta => 'warning',
            self::Anulada => 'danger',
        };
    }
}
