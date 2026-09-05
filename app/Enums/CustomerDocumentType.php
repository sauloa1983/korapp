<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CustomerDocumentType: string implements HasLabel
{
    case Cedula = 'cedula';
    case NitRut = 'nit_rut';
    case CamaraComercio = 'camara_comercio';
    case CertificadoBancario = 'certificado_bancario';
    case Contrato = 'contrato';
    case Otro = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Cedula => 'Cédula / Documento de identidad',
            self::NitRut => 'NIT / RUT',
            self::CamaraComercio => 'Cámara de comercio',
            self::CertificadoBancario => 'Certificado bancario',
            self::Contrato => 'Contrato',
            self::Otro => 'Otro',
        };
    }
}
