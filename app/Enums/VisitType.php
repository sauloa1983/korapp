<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum VisitType: string implements HasLabel, HasColor
{
    case Visita = 'visita';
    case Llamada = 'llamada';
    case Email = 'email';
    case Reunion = 'reunion';
    case Whatsapp = 'whatsapp';
    case Otro = 'otro';

    public function getLabel(): string
    {
        return match ($this) {
            self::Visita => 'Visita',
            self::Llamada => 'Llamada',
            self::Email => 'Correo',
            self::Reunion => 'Reunión',
            self::Whatsapp => 'WhatsApp',
            self::Otro => 'Otro',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Visita => 'primary',
            self::Llamada => 'info',
            self::Email => 'gray',
            self::Reunion => 'warning',
            self::Whatsapp => 'success',
            self::Otro => 'gray',
        };
    }
}
