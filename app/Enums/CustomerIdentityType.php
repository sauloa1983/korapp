<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

/**
 * Tipo de documento de identidad del cliente (NIT/CC/CE…).
 * No confundir con CustomerDocumentType (archivos adjuntos).
 */
enum CustomerIdentityType: string implements HasLabel
{
    case Nit = 'nit';
    case Cedula = 'cc';
    case Ce = 'ce';
    case Pasaporte = 'pasaporte';
    case Otro = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Nit => 'NIT',
            self::Cedula => 'Cédula (CC)',
            self::Ce => 'Cédula de extranjería (CE)',
            self::Pasaporte => 'Pasaporte',
            self::Otro => 'Otro',
        };
    }
}
