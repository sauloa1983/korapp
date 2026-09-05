<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SernaSheetFinish: string implements HasLabel
{
    case CristalOpal = 'cristal_opal';
    case Color = 'color';
    case DosColoresEstampada = 'dos_colores_estampada';

    public function getLabel(): string
    {
        return match ($this) {
            self::CristalOpal => 'Cristal / Opal',
            self::Color => 'Color',
            self::DosColoresEstampada => '2 Colores / Estampada',
        };
    }
}
