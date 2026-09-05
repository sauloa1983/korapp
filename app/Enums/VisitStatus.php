<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisitStatus: string implements HasLabel, HasColor
{
    case Programada = 'programada';
    case Realizada = 'realizada';
    case Cancelada = 'cancelada';
    case NoAsistio = 'no_asistio';

    public function getLabel(): string
    {
        return match ($this) {
            self::Programada => 'Programada',
            self::Realizada => 'Realizada',
            self::Cancelada => 'Cancelada',
            self::NoAsistio => 'No asistió',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Programada => 'warning',
            self::Realizada => 'success',
            self::Cancelada => 'gray',
            self::NoAsistio => 'danger',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Programada;
    }
}
