<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AcrylicLightingPricingMode: string implements HasLabel
{
    case None = 'none';
    case PerMeter = 'per_meter';
    case PerSquareMeter = 'per_m2';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Sin iluminación',
            self::PerMeter => 'Por metro lineal (perímetro)',
            self::PerSquareMeter => 'Por m²',
            self::Fixed => 'Precio fijo',
        };
    }
}
