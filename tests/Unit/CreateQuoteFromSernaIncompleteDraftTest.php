<?php

namespace Tests\Unit;

use App\Enums\QuoteStatus;
use App\Enums\SernaItemType;
use App\Models\Customer;
use App\Models\User;
use App\Services\Serna\CreateQuoteFromSernaCalculation;
use Database\Seeders\Serna2026PriceListSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CreateQuoteFromSernaIncompleteDraftTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(Serna2026PriceListSeeder::class);
    }

    public function test_saves_incomplete_draft_with_pending_manual_price(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $customer = Customer::query()->create([
            'name' => 'CLIENTE TEST',
            'is_active' => true,
        ]);

        $quote = app(CreateQuoteFromSernaCalculation::class)->handle([
            'customer_id' => $customer->id,
            'project_name' => 'PROYECTO BORRADOR',
            'ai_prompt' => 'Aviso acrílico 3mm 100x50 con transporte e instalación',
            'pieces' => [[
                'name' => 'AVISO',
                'items' => [
                    [
                        'item_type' => SernaItemType::CorteLaser->value,
                        'material' => 'ACRILICO 3MM',
                        'thickness_mm' => 3,
                        'width_cm' => 100,
                        'height_cm' => 50,
                        'quantity' => 1,
                    ],
                    [
                        'item_type' => SernaItemType::PrecioFijo->value,
                        'material' => 'TRANSPORTE E INSTALACION',
                        'quantity' => 1,
                        'unit_price' => null,
                    ],
                ],
            ]],
        ], allowIncomplete: true);

        $this->assertSame(QuoteStatus::Draft, $quote->status);
        $this->assertStringContainsString('[PENDIENTES BORRADOR]', (string) $quote->notes);
        $this->assertGreaterThanOrEqual(2, $quote->items()->count());

        $pending = $quote->items()->get()->first(
            fn ($item): bool => str_contains((string) $item->description, 'PENDIENTE')
                || (bool) data_get($item->meta, 'incomplete')
        );
        $this->assertNotNull($pending);
        $this->assertEquals(0.0, (float) $pending->unit_price);
    }
}
