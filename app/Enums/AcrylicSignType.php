<?php

namespace App\Enums;

use Filament\Support\Contracts\HasDescription;
use Filament\Support\Contracts\HasLabel;

enum AcrylicSignType: string implements HasLabel, HasDescription
{
    case WithBase = 'with_base';
    case LettersOnly = 'letters_only';
    case FlatLaser = 'flat_laser';

    public function getLabel(): string
    {
        return match ($this) {
            self::WithBase => 'Caja / fondo + letras o logo',
            self::LettersOnly => 'Solo letras o logo (sin base)',
            self::FlatLaser => 'Corte láser plano',
        };
    }

    public function getDescription(): ?string
    {
        return match ($this) {
            self::WithBase => 'Placa o caja de acrílico con letras y/o logo sobrepuestos (2D, 3D, vinilo, etc.). También puede ser solo logo.',
            self::LettersOnly => 'Letras o logo individuales, sin placa ni fondo. Las medidas son el área envolvente.',
            self::FlatLaser => 'Aviso plano recortado en láser; la pieza es el producto (sin letras 3D aparte).',
        };
    }

    public function requiresBase(): bool
    {
        return $this !== self::LettersOnly;
    }

    public function requiresLettering(): bool
    {
        return $this !== self::FlatLaser;
    }

    /** En solo letras, el área del aviso es el área de las letras (100% cobertura). */
    public function forcesFullLetterCoverage(): bool
    {
        return $this === self::LettersOnly;
    }
}
