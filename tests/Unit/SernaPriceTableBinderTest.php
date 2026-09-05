<?php

namespace Tests\Unit;

use App\Enums\SernaItemType;
use App\Models\SernaProcessRate;
use App\Services\Serna\Ai\SernaAiAssistant;
use App\Services\Serna\Ai\SernaPriceTableBinder;
use Database\Seeders\AcrylicQuoteSeeder;
use Database\Seeders\Serna2026PriceListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SernaPriceTableBinderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Serna2026PriceListSeeder::class);
        $this->seed(AcrylicQuoteSeeder::class);
    }

    public function test_binds_laser_item_to_table_rate_and_calculates_from_cm2(): void
    {
        $rate = SernaProcessRate::query()->where('code', 'laser_2_4')->firstOrFail();

        $draft = app(SernaPriceTableBinder::class)->bind([
            'project_name' => 'PROYECTO TEST',
            'contact_name' => null,
            'notes' => null,
            'payment_form' => 'CONTADO',
            'source' => 'test',
            'pieces' => [[
                'name' => 'PROYECTO TEST',
                'items' => [[
                    'item_type' => SernaItemType::CorteLaser->value,
                    'material' => 'ACRILICO 3MM',
                    'acabados' => 'CORTE',
                    'thickness_mm' => 3,
                    'width_cm' => 100,
                    'height_cm' => 100,
                    'quantity' => 1,
                    // Precio inventado por IA: debe ignorarse.
                    'unit_price' => '999999',
                ]],
            ]],
        ]);

        $item = $draft['pieces'][0]['items'][0];
        $this->assertSame($rate->id, $item['process_rate_id']);
        $this->assertNull($item['unit_price']);
        $this->assertTrue($draft['pricing']['ok']);
        $this->assertEquals(55000.0, $draft['pricing']['subtotal']);
        $this->assertEquals(5.5, $draft['pricing']['lines'][0]['price_per_cm2']);
    }

    public function test_binds_catalog_product_price_from_table(): void
    {
        $draft = app(SernaPriceTableBinder::class)->bind([
            'project_name' => 'OFICINA',
            'contact_name' => null,
            'notes' => null,
            'payment_form' => null,
            'source' => 'test',
            'pieces' => [[
                'name' => 'CUBREALFOMBRA',
                'items' => [[
                    'item_type' => SernaItemType::ProductoCatalogo->value,
                    'material' => 'CUBREALFOMBRA GERENTE',
                    'acabados' => 'GERENTE 5.5MM',
                    'quantity' => 1,
                    'unit_price' => '1',
                ]],
            ]],
        ]);

        $item = $draft['pieces'][0]['items'][0];
        $this->assertNotNull($item['catalog_product_id']);
        $this->assertNull($item['unit_price']);
        $this->assertTrue($draft['pricing']['ok']);
        $this->assertEquals(612000.0, $draft['pricing']['subtotal']);
    }

    public function test_binds_lighting_and_auto_appends_power_supply(): void
    {
        $draft = app(SernaPriceTableBinder::class)->bind([
            'project_name' => 'AVISO LED',
            'contact_name' => null,
            'notes' => null,
            'payment_form' => null,
            'source' => 'test',
            'pieces' => [[
                'name' => 'AVISO LED',
                'items' => [[
                    'item_type' => SernaItemType::Iluminacion->value,
                    'material' => 'LED PERIMETRAL',
                    'acabados' => 'LED',
                    'lighting_name' => 'LED perimetral / módulos',
                    'width_cm' => 200,
                    'height_cm' => 50,
                    'quantity' => 1,
                    'unit_price' => '999999',
                ]],
            ]],
        ]);

        $types = collect($draft['pieces'][0]['items'])->pluck('item_type')->all();
        $this->assertContains(SernaItemType::Iluminacion->value, $types);
        $this->assertContains(SernaItemType::Fuente->value, $types);

        $light = collect($draft['pieces'][0]['items'])
            ->firstWhere('item_type', SernaItemType::Iluminacion->value);
        $this->assertNotNull($light['lighting_option_id']);
        $this->assertNull($light['unit_price']);
        $this->assertTrue($draft['pricing']['ok']);
        // Perímetro 2*(2+0.5)=5 m × 28000 + fuente 45000 = 185000
        $this->assertEquals(185000.0, $draft['pricing']['subtotal']);
    }

    public function test_keeps_transport_installation_as_manual_pending_price(): void
    {
        $draft = app(SernaPriceTableBinder::class)->bind([
            'project_name' => 'OBRA',
            'contact_name' => null,
            'notes' => null,
            'payment_form' => null,
            'source' => 'test',
            'pieces' => [[
                'name' => 'OBRA',
                'items' => [[
                    'item_type' => SernaItemType::PrecioFijo->value,
                    'material' => 'TRANSPORTE E INSTALACION',
                    'acabados' => 'VALOR MANUAL',
                    'quantity' => 1,
                    'unit_price' => null,
                ]],
            ]],
        ]);

        $item = $draft['pieces'][0]['items'][0];
        $this->assertSame(SernaItemType::PrecioFijo->value, $item['item_type']);
        $this->assertNull($item['unit_price']);
        $this->assertSame('manual_pending', $item['table_source']);
        $this->assertFalse($draft['pricing']['ok']);
    }

    public function test_merge_appends_new_piece_without_replacing_existing(): void
    {
        $existing = [[
            'name' => 'FACHADA',
            'items' => [[
                'item_type' => SernaItemType::CorteLaser->value,
                'material' => 'ACRILICO 3MM',
                'acabados' => 'CORTE',
                'thickness_mm' => 3,
                'width_cm' => 100,
                'height_cm' => 100,
                'quantity' => 1,
            ]],
        ]];

        $newDraft = [
            'project_name' => 'PROYECTO',
            'contact_name' => null,
            'notes' => null,
            'payment_form' => null,
            'source' => 'test',
            'explanation' => 'nueva pieza',
            'pieces' => [[
                'name' => 'INTERIOR',
                'items' => [[
                    'item_type' => SernaItemType::Iluminacion->value,
                    'material' => 'BACKLIGHT',
                    'acabados' => 'BACKLIGHT',
                    'lighting_name' => 'Backlight / módulos LED',
                    'width_cm' => 100,
                    'height_cm' => 50,
                    'quantity' => 1,
                ]],
            ]],
        ];

        $merged = app(SernaAiAssistant::class)->mergeDraftIntoExisting(
            app(SernaPriceTableBinder::class)->bind($newDraft),
            $existing,
        );

        $names = collect($merged['pieces'])->pluck('name')->all();
        $this->assertContains('FACHADA', $names);
        $this->assertContains('INTERIOR', $names);
    }
}
