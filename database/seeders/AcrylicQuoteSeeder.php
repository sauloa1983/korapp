<?php

namespace Database\Seeders;

use App\Enums\AcrylicFinishPricingMode;
use App\Enums\AcrylicFinishType;
use App\Enums\AcrylicLetteringPricingMode;
use App\Enums\AcrylicLetteringType;
use App\Enums\AcrylicLightingPricingMode;
use App\Enums\ItemType;
use App\Models\AcrylicFinishOption;
use App\Models\AcrylicLetteringOption;
use App\Models\AcrylicLightingOption;
use App\Models\AcrylicMaterial;
use App\Models\AcrylicPricingSetting;
use App\Models\Item;
use Illuminate\Database\Seeder;

class AcrylicQuoteSeeder extends Seeder
{
    public function run(): void
    {
        AcrylicPricingSetting::current()->update([
            'labor_fixed_cost' => 25000,
            'labor_per_m2' => 18000,
            'assembly_percent_of_lettering' => 15,
            'logo_price_per_m2' => 180000,
            'logo_fixed_cost' => 25000,
            'margin_percent' => 35,
        ]);

        $materials = [
            ['name' => 'Acrílico transparente', 'thickness_mm' => 3, 'price_per_m2' => 85000, 'waste_percent' => 10, 'sort_order' => 1],
            ['name' => 'Acrílico transparente', 'thickness_mm' => 5, 'price_per_m2' => 120000, 'waste_percent' => 10, 'sort_order' => 2],
            ['name' => 'Acrílico transparente', 'thickness_mm' => 8, 'price_per_m2' => 175000, 'waste_percent' => 12, 'sort_order' => 3],
            ['name' => 'Acrílico blanco / color', 'thickness_mm' => 3, 'price_per_m2' => 95000, 'waste_percent' => 10, 'sort_order' => 4],
            ['name' => 'Acrílico blanco / color', 'thickness_mm' => 5, 'price_per_m2' => 135000, 'waste_percent' => 10, 'sort_order' => 5],
        ];

        foreach ($materials as $row) {
            AcrylicMaterial::query()->updateOrCreate(
                [
                    'name' => $row['name'],
                    'thickness_mm' => $row['thickness_mm'],
                ],
                [
                    ...$row,
                    'is_active' => true,
                ],
            );
        }

        $lettering = [
            [
                'name' => 'Sin letras',
                'type' => AcrylicLetteringType::None,
                'pricing_mode' => AcrylicLetteringPricingMode::None,
                'price_per_m2' => 0,
                'cut_price_per_meter' => 0,
                'fixed_price' => 0,
                'price_per_letter' => 0,
                'sort_order' => 1,
            ],
            [
                'name' => 'Vinilo impreso',
                'type' => AcrylicLetteringType::Vinyl,
                'pricing_mode' => AcrylicLetteringPricingMode::CoverageArea,
                'price_per_m2' => 95000,
                'cut_price_per_meter' => 0,
                'fixed_price' => 0,
                'price_per_letter' => 3500,
                'sort_order' => 2,
            ],
            [
                'name' => 'Acrílico en relieve 2D',
                'type' => AcrylicLetteringType::Relief2d,
                'pricing_mode' => AcrylicLetteringPricingMode::CoverageAreaPlusCut,
                'price_per_m2' => 220000,
                'cut_price_per_meter' => 18000,
                'fixed_price' => 0,
                'price_per_letter' => 12000,
                'sort_order' => 3,
            ],
            [
                'name' => 'Letras en caja 3D',
                'type' => AcrylicLetteringType::Box3d,
                'pricing_mode' => AcrylicLetteringPricingMode::CoverageAreaPlusCut,
                'price_per_m2' => 380000,
                'cut_price_per_meter' => 25000,
                'fixed_price' => 0,
                'price_per_letter' => 28000,
                'sort_order' => 4,
            ],
            [
                'name' => 'Corte UV',
                'type' => AcrylicLetteringType::UvCut,
                'pricing_mode' => AcrylicLetteringPricingMode::CoverageAreaPlusCut,
                'price_per_m2' => 160000,
                'cut_price_per_meter' => 22000,
                'fixed_price' => 0,
                'price_per_letter' => 8000,
                'sort_order' => 5,
            ],
        ];

        AcrylicLetteringOption::query()
            ->where('name', 'Sin letras / logo')
            ->update(['name' => 'Sin letras']);

        foreach ($lettering as $row) {
            AcrylicLetteringOption::query()->updateOrCreate(
                ['type' => $row['type']],
                [...$row, 'is_active' => true],
            );
        }

        $lighting = [
            [
                'name' => 'Sin iluminación',
                'pricing_mode' => AcrylicLightingPricingMode::None,
                'unit_price' => 0,
                'power_supply_cost' => 0,
                'sort_order' => 1,
            ],
            [
                'name' => 'LED perimetral / módulos',
                'pricing_mode' => AcrylicLightingPricingMode::PerMeter,
                'unit_price' => 28000,
                'power_supply_cost' => 45000,
                'sort_order' => 2,
            ],
            [
                'name' => 'Backlight / módulos LED',
                'pricing_mode' => AcrylicLightingPricingMode::PerSquareMeter,
                'unit_price' => 95000,
                'power_supply_cost' => 65000,
                'sort_order' => 3,
            ],
            [
                'name' => 'Neón Flex',
                'pricing_mode' => AcrylicLightingPricingMode::Fixed,
                'unit_price' => 180000,
                'power_supply_cost' => 45000,
                'sort_order' => 4,
            ],
        ];

        foreach ($lighting as $row) {
            AcrylicLightingOption::query()->updateOrCreate(
                ['name' => $row['name']],
                [...$row, 'is_active' => true],
            );
        }

        $finishes = [
            [
                'name' => 'Distanciadores metálicos',
                'type' => AcrylicFinishType::Spacer,
                'pricing_mode' => AcrylicFinishPricingMode::PerUnit,
                'unit_price' => 2500,
                'sort_order' => 1,
            ],
            [
                'name' => 'Chasis metálico',
                'type' => AcrylicFinishType::Chassis,
                'pricing_mode' => AcrylicFinishPricingMode::PerSquareMeter,
                'unit_price' => 65000,
                'sort_order' => 2,
            ],
            [
                'name' => 'Instalación básica',
                'type' => AcrylicFinishType::Other,
                'pricing_mode' => AcrylicFinishPricingMode::Fixed,
                'unit_price' => 80000,
                'sort_order' => 3,
            ],
        ];

        foreach ($finishes as $row) {
            AcrylicFinishOption::query()->updateOrCreate(
                ['name' => $row['name']],
                [...$row, 'is_active' => true],
            );
        }

        AcrylicFinishOption::query()
            ->whereIn('name', ['Corte láser', 'Corte CNC', 'Separadores / tornillos'])
            ->update(['is_active' => false]);

        Item::query()->updateOrCreate(
            ['sku' => 'AVISO-ACRILICO'],
            [
                'name' => 'Aviso acrílico a medida',
                'type' => ItemType::ProductoTerminado,
                'unit_of_measure' => 'und',
                'stock' => 0,
                'min_stock' => 0,
                'cost' => 0,
                'price' => 0,
                'is_active' => true,
                'description' => 'Producto generado desde el cotizador de avisos en acrílico.',
            ],
        );
    }
}
