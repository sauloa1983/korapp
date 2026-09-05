<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ProcessDepartment: string implements HasLabel
{
    case Impresion = 'impresion';
    case Laser = 'laser';
    case Miscelanea = 'miscelanea';
    case Termoformado = 'termoformado';
    case Avisos = 'avisos';
    case ControlCalidad = 'control_calidad';

    public function getLabel(): string
    {
        return match ($this) {
            self::Impresion => 'Impresión',
            self::Laser => 'Láser',
            self::Miscelanea => 'Miscelánea',
            self::Termoformado => 'Termoformado',
            self::Avisos => 'Avisos',
            self::ControlCalidad => 'Control calidad',
        };
    }

    /** Orden de aparición en la OP / PDF (como la hoja física). */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Impresion => 10,
            self::Laser => 20,
            self::Miscelanea => 30,
            self::Termoformado => 40,
            self::Avisos => 50,
            self::ControlCalidad => 60,
        };
    }

    /** @return list<self> */
    public static function ordered(): array
    {
        $cases = self::cases();
        usort($cases, fn (self $a, self $b): int => $a->sortOrder() <=> $b->sortOrder());

        return $cases;
    }
}
