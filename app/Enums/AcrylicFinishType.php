<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AcrylicFinishType: string implements HasLabel
{
    case Spacer = 'spacer';
    case Cut = 'cut';
    case Chassis = 'chassis';
    case Other = 'other';

    public function getLabel(): string
    {
        return match ($this) {
            self::Spacer => 'Separadores / fijación',
            self::Cut => 'Corte',
            self::Chassis => 'Chasis / estructura',
            self::Other => 'Otro',
        };
    }
}
