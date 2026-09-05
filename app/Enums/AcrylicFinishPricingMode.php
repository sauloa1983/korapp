<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AcrylicFinishPricingMode: string implements HasLabel
{
    case Fixed = 'fixed';
    case PerSquareMeter = 'per_m2';
    case PerMeter = 'per_meter';
    case PerUnit = 'per_unit';

    public function getLabel(): string
    {
        return match ($this) {
            self::Fixed => 'Precio fijo por aviso',
            self::PerSquareMeter => 'Por m²',
            self::PerMeter => 'Por metro lineal',
            self::PerUnit => 'Por unidad (ej. separador)',
        };
    }
}
