<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Efectivo = 'efectivo';
    case Transferencia = 'transferencia';
    case Datafono = 'datafono';

    public function getLabel(): string
    {
        return match ($this) {
            self::Efectivo => 'Efectivo',
            self::Transferencia => 'Transferencia',
            self::Datafono => 'Datáfono',
        };
    }
}
