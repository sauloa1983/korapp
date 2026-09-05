<?php

namespace Tests\Unit;

use App\Enums\SernaItemType;
use App\Models\AcrylicLightingOption;
use App\Models\SernaProcessRate;
use App\Services\Serna\Ai\SernaQuoteReadinessChecker;
use Database\Seeders\AcrylicQuoteSeeder;
use Database\Seeders\Serna2026PriceListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SernaQuoteReadinessCheckerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Serna2026PriceListSeeder::class);
        $this->seed(AcrylicQuoteSeeder::class);
    }

    public function test_blocks_when_prompt_mentions_vinilo_but_item_missing(): void
    {
        $result = app(SernaQuoteReadinessChecker::class)->check(
            [[
                'name' => 'AVISO',
                'items' => [[
                    'item_type' => SernaItemType::CorteLaser->value,
                    'material' => 'ACRILICO 3MM',
                    'thickness_mm' => 3,
                    'width_cm' => 100,
                    'height_cm' => 50,
                    'quantity' => 1,
                ]],
            ]],
            'Aviso acrílico 3mm 100x50 con vinilo adhesivo',
        );

        $this->assertFalse($result['ok']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn (string $e): bool => str_contains(mb_strtolower($e), 'vinilo'))
        );
    }

    public function test_blocks_manual_price_without_value(): void
    {
        $result = app(SernaQuoteReadinessChecker::class)->check(
            [[
                'name' => 'OBRA',
                'items' => [[
                    'item_type' => SernaItemType::PrecioFijo->value,
                    'material' => 'TRANSPORTE E INSTALACION',
                    'quantity' => 1,
                    'unit_price' => null,
                ]],
            ]],
            'transporte e instalación',
        );

        $this->assertFalse($result['ok']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn (string $e): bool => str_contains(mb_strtolower($e), 'precio'))
        );
    }

    public function test_blocks_unknown_material_in_prompt(): void
    {
        $result = app(SernaQuoteReadinessChecker::class)->check(
            [[
                'name' => 'AVISO',
                'items' => [[
                    'item_type' => SernaItemType::CorteLaser->value,
                    'material' => 'ACRILICO 3MM',
                    'thickness_mm' => 3,
                    'width_cm' => 100,
                    'height_cm' => 50,
                    'quantity' => 1,
                ]],
            ]],
            'Aviso en aluminio 100x50',
        );

        $this->assertFalse($result['ok']);
        $this->assertTrue(
            collect($result['errors'])->contains(fn (string $e): bool => str_contains(mb_strtolower($e), 'aluminio'))
        );
    }

    public function test_ok_when_lighting_and_acrylic_are_present(): void
    {
        $light = AcrylicLightingOption::query()
            ->where('name', 'LED perimetral / módulos')
            ->firstOrFail();

        $result = app(SernaQuoteReadinessChecker::class)->check(
            [[
                'name' => 'AVISO',
                'items' => [
                    [
                        'item_type' => SernaItemType::CorteLaser->value,
                        'material' => 'ACRILICO 3MM',
                        'thickness_mm' => 3,
                        'width_cm' => 200,
                        'height_cm' => 80,
                        'quantity' => 1,
                    ],
                    [
                        'item_type' => SernaItemType::Iluminacion->value,
                        'material' => 'LED',
                        'lighting_option_id' => $light->id,
                        'width_cm' => 200,
                        'height_cm' => 80,
                        'quantity' => 1,
                    ],
                    [
                        'item_type' => SernaItemType::Fuente->value,
                        'material' => 'FUENTE',
                        'lighting_option_id' => $light->id,
                        'quantity' => 1,
                    ],
                ],
            ]],
            'Aviso acrílico 3mm 200x80 con LED perimetral',
        );

        $this->assertTrue($result['ok'], implode(' | ', $result['errors']));
    }

    public function test_ok_when_prompt_mentions_acrylic_and_item_is_terminado(): void
    {
        $rate = SernaProcessRate::query()
            ->where('code', 'letra_recta_sin_tapa')
            ->firstOrFail();

        $result = app(SernaQuoteReadinessChecker::class)->check(
            [[
                'name' => 'AVISO REF AERIE EN 3D',
                'items' => [[
                    'item_type' => SernaItemType::Terminado->value,
                    'material' => 'ACRILICO 3MM',
                    'process_rate_id' => $rate->id,
                    'thickness_mm' => 3,
                    'width_cm' => 100,
                    'height_cm' => 40,
                    'quantity' => 1,
                ]],
            ]],
            'Aviso en acrílico 3mm letras 3D 100x40',
        );

        $this->assertTrue($result['ok'], implode(' | ', $result['errors']));
    }

    public function test_does_not_block_on_bare_word_corte_without_acrylic_item(): void
    {
        $result = app(SernaQuoteReadinessChecker::class)->check(
            [[
                'name' => 'SERVICIO',
                'items' => [[
                    'item_type' => SernaItemType::PrecioFijo->value,
                    'material' => 'TRANSPORTE',
                    'quantity' => 1,
                    'unit_price' => 50000,
                ]],
            ]],
            'Solo transporte, sin corte ni materiales',
        );

        $this->assertTrue(
            collect($result['errors'])->doesntContain(
                fn (string $e): bool => str_contains(mb_strtolower($e), 'acrílico/corte')
            ),
            implode(' | ', $result['errors']),
        );
    }

    public function test_does_not_treat_lamina_de_color_as_full_sheet(): void
    {
        $rate = SernaProcessRate::query()
            ->where('code', 'letra_recta_sin_tapa')
            ->firstOrFail();

        $light = AcrylicLightingOption::query()
            ->where('name', 'LED perimetral / módulos')
            ->firstOrFail();

        $result = app(SernaQuoteReadinessChecker::class)->check(
            [[
                'name' => 'AVISO',
                'items' => [
                    [
                        'item_type' => SernaItemType::CorteLaser->value,
                        'material' => 'ACRILICO 3MM',
                        'thickness_mm' => 3,
                        'width_cm' => 240,
                        'height_cm' => 100,
                        'quantity' => 1,
                    ],
                    [
                        'item_type' => SernaItemType::Terminado->value,
                        'material' => 'LETRAS 3MM',
                        'process_rate_id' => $rate->id,
                        'thickness_mm' => 3,
                        'width_cm' => 15,
                        'height_cm' => 80,
                        'quantity' => 10,
                    ],
                    [
                        'item_type' => SernaItemType::Iluminacion->value,
                        'material' => 'LED',
                        'lighting_option_id' => $light->id,
                        'width_cm' => 240,
                        'height_cm' => 100,
                        'quantity' => 1,
                    ],
                    [
                        'item_type' => SernaItemType::Fuente->value,
                        'material' => 'FUENTE',
                        'lighting_option_id' => $light->id,
                        'quantity' => 1,
                    ],
                ],
            ]],
            'Aviso 240 x 100 cm, con lamina de color de 3mm y 10 letras de 15 x 80 cm en 3mm, todas con luz led',
        );

        $this->assertTrue(
            collect($result['errors'])->doesntContain(
                fn (string $e): bool => str_contains(mb_strtolower($e), 'lámina entera')
            ),
            implode(' | ', $result['errors']),
        );
    }
}
