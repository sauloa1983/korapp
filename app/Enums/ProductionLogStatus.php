<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasIcon;
use Filament\Support\Contracts\HasLabel;

enum ProductionLogStatus: string implements HasLabel, HasColor, HasIcon
{
    case EnEspera = 'en_espera';
    case Procesando = 'procesando';
    case Terminado = 'terminado';

    public function getLabel(): string
    {
        return match ($this) {
            self::EnEspera => 'En espera',
            self::Procesando => 'Procesando',
            self::Terminado => 'Terminado',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::EnEspera => 'gray',
            self::Procesando => 'warning',
            self::Terminado => 'success',
        };
    }

    public function getIcon(): string
    {
        return match ($this) {
            self::EnEspera => 'heroicon-o-clock',
            self::Procesando => 'heroicon-o-play',
            self::Terminado => 'heroicon-o-check-circle',
        };
    }
}
