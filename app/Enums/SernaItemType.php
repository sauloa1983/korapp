<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SernaItemType: string implements HasLabel
{
    case CorteLaser = 'corte_laser';
    case ManoObra = 'mano_obra';
    case Terminado = 'terminado';
    case Vinilo = 'vinilo';
    case Plotter = 'plotter';
    case Espejo = 'espejo';
    case Enchapado = 'enchapado';
    case LaminaEntera = 'lamina_entera';
    case ProductoCatalogo = 'producto_catalogo';
    case PrecioFijo = 'precio_fijo';
    case Iluminacion = 'iluminacion';
    case Fuente = 'fuente';

    public function getLabel(): string
    {
        return match ($this) {
            self::CorteLaser => 'Corte láser',
            self::ManoObra => 'Mano de obra',
            self::Terminado => 'Terminado / Cantonera / Letras',
            self::Vinilo => 'Vinilo',
            self::Plotter => 'Plotter',
            self::Espejo => 'Acabado espejo',
            self::Enchapado => 'Enchapado',
            self::LaminaEntera => 'Lámina entera',
            self::ProductoCatalogo => 'Producto de catálogo',
            self::PrecioFijo => 'Precio fijo / Instalación',
            self::Iluminacion => 'Iluminación / Luces',
            self::Fuente => 'Fuente de alimentación',
        };
    }

    public function usesArea(): bool
    {
        return match ($this) {
            self::LaminaEntera, self::ProductoCatalogo, self::PrecioFijo, self::Fuente => false,
            self::Iluminacion => true, // necesita X/Y para perímetro o m²
            default => true,
        };
    }

    public function pricingMode(): string
    {
        return match ($this) {
            self::LaminaEntera => 'full_sheet',
            self::ProductoCatalogo, self::PrecioFijo => 'fixed',
            self::Iluminacion => 'lighting',
            self::Fuente => 'power_supply',
            default => 'per_cm2',
        };
    }
}
