<?php

namespace Tests\Unit;

use App\Enums\SernaItemType;
use App\Models\CompanySetting;
use App\Models\SernaCatalogProduct;
use App\Models\SernaProcessRate;
use App\Models\SernaSheetPrice;
use App\Services\Serna\SernaQuotationEngine;
use Database\Seeders\Serna2026PriceListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SernaQuotationEngineTest extends TestCase
{
    use RefreshDatabase;

    private SernaQuotationEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();

        CompanySetting::current()->update([
            'charges_iva' => true,
            'iva_rate' => 19,
        ]);

        $this->seed(Serna2026PriceListSeeder::class);
        $this->engine = app(SernaQuotationEngine::class);
    }

    public function test_laser_cut_3mm_uses_cm2_rate(): void
    {
        $line = $this->engine->calculateItem([
            'item_type' => SernaItemType::CorteLaser->value,
            'thickness_mm' => 3,
            'width_cm' => 30,
            'height_cm' => 20,
            'quantity' => 2,
        ]);

        $this->assertSame(600.0, $line['area_cm2']);
        $this->assertEquals(5.5, $line['price_per_cm2']);
        $this->assertEquals(3300.0, $line['unit_price']);
        $this->assertEquals(6600.0, $line['line_total']);
        $this->assertSame('per_cm2', $line['pricing_mode']);
    }

    public function test_letter_minimum_charge_applies(): void
    {
        $rate = SernaProcessRate::query()->where('code', 'letra_recta_sin_tapa')->firstOrFail();

        $line = $this->engine->calculateItem([
            'item_type' => SernaItemType::Terminado->value,
            'process_rate_id' => $rate->id,
            'width_cm' => 10,
            'height_cm' => 10,
            'quantity' => 1,
        ]);

        $this->assertTrue($line['min_charge_applied']);
        $this->assertEquals(53000.0, $line['unit_price']);
    }

    public function test_full_sheet_120x180_3mm_cristal(): void
    {
        $sheet = SernaSheetPrice::query()
            ->where('format', '120x180')
            ->where('thickness_mm', 3)
            ->where('finish', 'cristal_opal')
            ->firstOrFail();

        $line = $this->engine->calculateItem([
            'item_type' => SernaItemType::LaminaEntera->value,
            'sheet_price_id' => $sheet->id,
            'quantity' => 1,
        ]);

        $this->assertEquals(278000.0, $line['unit_price']);
        $this->assertSame('full_sheet', $line['pricing_mode']);
        $this->assertSame('official_120x180', $line['price_source']);
    }

    public function test_scaled_sheet_formats_are_seeded(): void
    {
        $this->assertTrue(
            SernaSheetPrice::query()->where('format', '180x300')->where('price_source', 'scaled_from_120x180')->exists()
        );
    }

    public function test_catalog_product_fixed_price(): void
    {
        $product = SernaCatalogProduct::query()->where('sku', 'CUBRE-GER')->firstOrFail();

        $line = $this->engine->calculateItem([
            'item_type' => SernaItemType::ProductoCatalogo->value,
            'catalog_product_id' => $product->id,
            'quantity' => 1,
        ]);

        $this->assertEquals(612000.0, $line['unit_price']);
        $this->assertSame('fixed', $line['pricing_mode']);
    }

    public function test_proposal_applies_iva_and_withholding(): void
    {
        $proposal = $this->engine->calculateProposal([
            [
                'item_type' => SernaItemType::CorteLaser->value,
                'thickness_mm' => 3,
                'width_cm' => 100,
                'height_cm' => 100,
                'quantity' => 1,
            ],
        ], withholdingRate: 2.5);

        $this->assertEquals(55000.0, $proposal['subtotal']);
        $this->assertEquals(19.0, $proposal['iva_rate']);
        $this->assertEquals(10450.0, $proposal['iva_amount']);
        $this->assertEquals(65450.0, $proposal['total']);
        $this->assertEquals(1375.0, $proposal['withholding_amount']);
        $this->assertEquals(64075.0, $proposal['total_payable']);
    }

    public function test_proposal_groups_items_under_pieces(): void
    {
        $proposal = $this->engine->calculateProposalFromPieces([
            [
                'name' => 'PROYECTO MEDELLIN',
                'items' => [
                    [
                        'item_type' => SernaItemType::CorteLaser->value,
                        'material' => 'ACRILICO 3MM',
                        'thickness_mm' => 3,
                        'width_cm' => 100,
                        'height_cm' => 100,
                        'quantity' => 1,
                    ],
                    [
                        'item_type' => SernaItemType::PrecioFijo->value,
                        'material' => 'INSTALACION',
                        'unit_price' => 100000,
                        'quantity' => 1,
                    ],
                ],
            ],
            [
                'name' => 'AVISO INTERIOR',
                'items' => [
                    [
                        'item_type' => SernaItemType::Vinilo->value,
                        'process_rate_id' => SernaProcessRate::query()->where('code', 'vinilo_adhesivo')->value('id'),
                        'material' => 'VINILO ADHESIVO',
                        'width_cm' => 10,
                        'height_cm' => 10,
                        'quantity' => 1,
                    ],
                ],
            ],
        ]);

        $this->assertSame(2, $proposal['piece_count']);
        $this->assertSame(3, $proposal['item_count']);
        $this->assertSame('PROYECTO MEDELLIN', $proposal['pieces'][0]['name']);
        $this->assertSame('PROYECTO MEDELLIN', $proposal['items'][0]['pieza']);
        $this->assertSame('AVISO INTERIOR', $proposal['items'][2]['pieza']);
        $this->assertEquals(155550.0, $proposal['subtotal']);
    }
}
