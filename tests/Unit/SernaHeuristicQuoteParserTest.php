<?php

namespace Tests\Unit;

use App\Services\Serna\Ai\SernaHeuristicQuoteParser;
use App\Services\Serna\Ai\SernaPriceTableBinder;
use Database\Seeders\AcrylicQuoteSeeder;
use Database\Seeders\Serna2026PriceListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SernaHeuristicQuoteParserTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Serna2026PriceListSeeder::class);
        $this->seed(AcrylicQuoteSeeder::class);
    }

    public function test_parses_project_dimensions_and_installation(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            'Proyecto Medellín aviso acrílico 3mm 420x214 cm con cantonera. Instalación por 3500000. Contacto Gloria Marin. Contado.'
        );

        $this->assertSame('heuristic', $draft['source']);
        $this->assertNotEmpty($draft['project_name']);
        $this->assertSame('GLORIA MARIN', $draft['contact_name']);
        $this->assertSame('CONTADO', $draft['payment_form']);
        $this->assertNotEmpty($draft['pieces']);
        $this->assertGreaterThanOrEqual(2, count($draft['pieces'][0]['items']));

        $types = collect($draft['pieces'][0]['items'])->pluck('item_type')->all();
        $this->assertContains('precio_fijo', $types);
    }

    public function test_splits_multiple_named_pieces(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            "Pieza 1: Fachada acrílico 3mm 200x50 cm\nPieza 2: Interior vinilo 100x40 cm"
        );

        $this->assertGreaterThanOrEqual(2, count($draft['pieces']));
    }

    public function test_detects_led_lighting_item(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            'Aviso acrílico 3mm 200x80 cm con LED perimetral'
        );

        $types = collect($draft['pieces'][0]['items'])->pluck('item_type')->all();
        $this->assertContains('iluminacion', $types);
    }

    public function test_transport_and_installation_create_manual_fixed_item_without_price(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            'Aviso acrílico 3mm 200x80 cm con transporte e instalación'
        );

        $fixed = collect($draft['pieces'][0]['items'])
            ->firstWhere('item_type', 'precio_fijo');

        $this->assertNotNull($fixed);
        $this->assertSame('TRANSPORTE E INSTALACION', $fixed['material']);
        $this->assertNull($fixed['unit_price']);
    }

    public function test_first_item_acabados_does_not_store_full_assistant_prompt(): void
    {
        $prompt = 'Aviso acrílico 3mm 200x80 cm cristal, vinilo instalado, LED perimetral + transporte e instalación. Contacto Ana. Contado.';

        $draft = app(SernaHeuristicQuoteParser::class)->parse($prompt);
        $first = $draft['pieces'][0]['items'][0];

        $this->assertSame('ACRILICO 3MM', $first['material']);
        $this->assertStringContainsString('CRISTAL', $first['acabados']);
        $this->assertStringNotContainsString('CONTACTO', $first['acabados']);
        $this->assertStringNotContainsString('VINILO', $first['acabados']);
        $this->assertStringNotContainsString('TRANSPORTE', $first['acabados']);
        $this->assertLessThan(120, mb_strlen($first['acabados']));

        $types = collect($draft['pieces'][0]['items'])->pluck('item_type')->all();
        $this->assertContains('vinilo', $types);
        $this->assertContains('iluminacion', $types);
        $this->assertContains('precio_fijo', $types);
    }

    public function test_maps_full_sheet_to_lista_serna_lamina(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            'Lámina entera 3mm formato 120x180 cristal'
        );

        $item = collect($draft['pieces'][0]['items'])->firstWhere('item_type', 'lamina_entera');
        $this->assertNotNull($item);
        $this->assertNotNull($item['sheet_price_id']);
        $this->assertSame('120x180', $item['format']);

        $bound = app(SernaPriceTableBinder::class)->bind($draft);
        $boundItem = $bound['pieces'][0]['items'][0];
        $this->assertSame('lamina_entera', $boundItem['item_type']);
        $this->assertNotNull($boundItem['sheet_price_id']);
        $this->assertTrue($bound['pricing']['ok'] ?? false);
    }

    public function test_maps_catalog_product_from_lista_serna(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            'Necesito cubrealfombra presidente'
        );

        $item = collect($draft['pieces'][0]['items'])->firstWhere('item_type', 'producto_catalogo');
        $this->assertNotNull($item);
        $this->assertSame('CUBRE-PRES', $item['sku']);

        $bound = app(SernaPriceTableBinder::class)->bind($draft);
        $this->assertSame('producto_catalogo', $bound['pieces'][0]['items'][0]['item_type']);
        $this->assertTrue($bound['pricing']['ok'] ?? false);
    }

    public function test_assigns_process_rate_code_from_lista_serna(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            'Aviso acrílico 3mm 100x50 cm corte láser'
        );

        $laser = collect($draft['pieces'][0]['items'])->firstWhere('item_type', 'corte_laser');
        $this->assertNotNull($laser);
        $this->assertSame('laser_2_4', $laser['process_rate_code']);
    }

    public function test_led_binds_lighting_option_id_from_lista_serna(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            'Aviso acrílico 3mm 200x80 cm con LED perimetral'
        );

        $light = collect($draft['pieces'][0]['items'])->firstWhere('item_type', 'iluminacion');
        $this->assertNotNull($light);
        $this->assertNotNull($light['lighting_option_id']);
    }

    public function test_full_sheet_piece_also_keeps_espejo_enchapado_and_mano_obra(): void
    {
        $draft = app(SernaHeuristicQuoteParser::class)->parse(
            'Pieza 1: Aviso acrílico 3mm 240x100 cm cristal, corte láser, 10 letras 3D 15x80 cm rectas sin tapa, vinilo instalado, LED perimetral + transporte e instalación por 850000.'."\n"
            .'Pieza 2: Lámina entera 3mm 120x180 color, enchapado 50x30 cm, acabado espejo plata 40x20 cm, plotter 30x20 cm y mano de obra 3mm 60x40 cm.'
        );

        $this->assertGreaterThanOrEqual(2, count($draft['pieces']));

        $piece2Types = collect($draft['pieces'][1]['items'])->pluck('item_type')->all();
        $this->assertContains('lamina_entera', $piece2Types);
        $this->assertContains('enchapado', $piece2Types);
        $this->assertContains('espejo', $piece2Types);
        $this->assertContains('mano_obra', $piece2Types);
        $this->assertContains('plotter', $piece2Types);

        $piece1Types = collect($draft['pieces'][0]['items'])->pluck('item_type')->all();
        $this->assertContains('terminado', $piece1Types);
        $this->assertContains('corte_laser', $piece1Types);

        $bound = app(SernaPriceTableBinder::class)->bind($draft);
        $boundTypes = collect($bound['pieces'][1]['items'])->pluck('item_type')->all();
        $this->assertContains('enchapado', $boundTypes);
        $this->assertContains('espejo', $boundTypes);
    }
}
