<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AcrylicLetteringType: string implements HasLabel
{
    case None = 'none';
    case Vinyl = 'vinyl';
    case Relief2d = 'relief_2d';
    case Box3d = 'box_3d';
    case UvCut = 'uv_cut';

    public function getLabel(): string
    {
        return match ($this) {
            self::None => 'Sin letras',
            self::Vinyl => 'Vinilo impreso',
            self::Relief2d => 'Acrílico en relieve 2D',
            self::Box3d => 'Letras en caja 3D',
            self::UvCut => 'Corte UV',
        };
    }
}
