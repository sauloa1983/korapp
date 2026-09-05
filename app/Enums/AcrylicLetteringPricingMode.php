<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AcrylicLetteringPricingMode: string implements HasLabel
{
    case None = 'none';
    case CoverageArea = 'coverage_m2';
    case CoverageAreaPlusCut = 'coverage_m2_plus_cut';
    case Fixed = 'fixed';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Sin costo',
            self::CoverageArea => 'Área de letras (m² de cobertura)',
            self::CoverageAreaPlusCut => 'Área de letras + metros de corte',
            self::Fixed => 'Precio fijo por aviso',
        };
    }
}
