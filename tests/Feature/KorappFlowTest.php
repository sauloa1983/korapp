<?php

namespace Tests\Feature;

use App\Enums\ItemType;
use App\Enums\ProductionLogStatus;
use App\Enums\ProductionOrderStatus;
use App\Enums\QuoteStatus;
use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Item;
use App\Models\Process;
use App\Models\ProductionOrder;
use App\Models\Purchase;
use App\Models\Quote;
use App\Models\Sale;
use App\Models\SaleReturn;
use App\Models\Supplier;
use App\Enums\StockMovementType;
use App\Models\User;
use App\Models\Warehouse;
use App\Notifications\LowStockAlert;
use App\Notifications\LowStockDigest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class KorappFlowTest extends TestCase
{
    use RefreshDatabase;

    private function seedBasics(): array
    {
        Process::create(['name' => 'Corte', 'sort_order' => 10]);
        Process::create(['name' => 'Pulido', 'sort_order' => 20]);

        $product = Item::create([
            'sku' => 'PT-1',
            'name' => 'Producto',
            'type' => ItemType::ProductoTerminado,
            'stock' => 0,
        ]);

        $material = Item::create([
            'sku' => 'MP-1',
            'name' => 'Lámina',
            'type' => ItemType::MateriaPrima,
            'stock' => 100,
        ]);

        return [$product, $material];
    }

    public function test_dashboard_renders_with_widgets(): void
    {
        $this->seedBasics();
        $user = User::factory()->create();
        Role::findOrCreate('super_admin', 'web');
        $user->assignRole('super_admin');

        $this->actingAs($user)
            ->get('/admin')
            ->assertOk();
    }

    public function test_production_order_generates_pipeline_with_qr_tokens(): void
    {
        [$product] = $this->seedBasics();

        $order = ProductionOrder::create([
            'item_id' => $product->id,
            'quantity' => 5,
        ]);

        $this->assertNotEmpty($order->code);
        $this->assertStringStartsWith('OP-', $order->code);

        $order->generatePipeline();

        $this->assertCount(2, $order->logs);
        $this->assertNotEmpty($order->logs->first()->qr_token);
    }

    public function test_qr_scan_starts_and_finishes_stage_and_tracks_time(): void
    {
        [$product] = $this->seedBasics();

        $order = ProductionOrder::create(['item_id' => $product->id, 'quantity' => 1]);
        $order->generatePipeline();
        $log = $order->logs->first();

        // La página pública muestra la etapa.
        $this->get(route('scan.show', $log->qr_token))
            ->assertOk()
            ->assertSee('Corte');

        // Primer escaneo: inicia la etapa y arranca la orden.
        $this->post(route('scan.update', $log->qr_token))->assertRedirect();
        $log->refresh();
        $order->refresh();
        $this->assertSame(ProductionLogStatus::Procesando, $log->status);
        $this->assertNotNull($log->started_at);
        $this->assertSame(ProductionOrderStatus::EnProgreso, $order->status);

        // Segundo escaneo: finaliza y calcula la duración.
        $this->post(route('scan.update', $log->qr_token))->assertRedirect();
        $log->refresh();
        $this->assertSame(ProductionLogStatus::Terminado, $log->status);
        $this->assertNotNull($log->ended_at);
        $this->assertNotNull($log->duration_seconds);
    }

    public function test_consume_stock_decrements_inventory_and_logs_movement(): void
    {
        [$product, $material] = $this->seedBasics();

        $order = ProductionOrder::create(['item_id' => $product->id, 'quantity' => 1]);
        $order->requirements()->create([
            'item_id' => $material->id,
            'quantity_required' => 30,
        ]);

        $order->consumeStock();

        $material->refresh();
        $this->assertEquals(70, (float) $material->stock);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $material->id,
            'type' => 'salida',
            'quantity' => 30,
        ]);
    }

    public function test_completing_production_order_produces_finished_goods_stock(): void
    {
        [$product, $material] = $this->seedBasics();
        $product->update(['stock' => 0]);

        $order = ProductionOrder::create(['item_id' => $product->id, 'quantity' => 4]);
        $order->requirements()->create([
            'item_id' => $material->id,
            'quantity_required' => 20,
        ]);
        $order->generatePipeline();

        foreach ($order->logs as $log) {
            $log->start();
            $log->finish();
        }

        $order->refresh();
        $product->refresh();
        $material->refresh();

        $this->assertSame(\App\Enums\ProductionOrderStatus::Completado, $order->status);
        $this->assertEquals(4, (float) $product->stock);
        $this->assertEquals(80, (float) $material->stock);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $product->id,
            'type' => 'entrada',
            'quantity' => 4,
        ]);
        $this->assertTrue($order->hasProducedStock());

        $order->produceStock();
        $product->refresh();
        $this->assertEquals(4, (float) $product->stock);
    }

    public function test_production_order_can_fabricate_insumo(): void
    {
        [, $material] = $this->seedBasics();

        $insumo = Item::create([
            'sku' => 'INS-1',
            'name' => 'Pieza intermedia',
            'type' => ItemType::Insumo,
            'stock' => 0,
        ]);

        $order = ProductionOrder::create(['item_id' => $insumo->id, 'quantity' => 10]);
        $order->requirements()->create([
            'item_id' => $material->id,
            'quantity_required' => 5,
        ]);
        $order->generatePipeline();

        foreach ($order->logs as $log) {
            $log->start();
            $log->finish();
        }

        $insumo->refresh();
        $this->assertEquals(10, (float) $insumo->stock);
        $this->assertSame(\App\Enums\ProductionOrderStatus::Completado, $order->fresh()->status);
    }

    public function test_purchase_receive_increases_stock(): void
    {
        [, $material] = $this->seedBasics();
        $supplier = Supplier::create(['name' => 'Proveedor Test']);

        $purchase = Purchase::create(['supplier_id' => $supplier->id]);
        $purchase->items()->create([
            'item_id' => $material->id,
            'quantity' => 50,
            'unit_cost' => 40000,
        ]);

        $this->assertStringStartsWith('OC-', $purchase->code);

        $purchase->receive();

        $material->refresh();
        $purchase->refresh();

        $this->assertEquals(150, (float) $material->stock);
        $this->assertEquals(40000, (float) $material->cost);
        $this->assertSame(\App\Enums\PurchaseStatus::Recibida, $purchase->status);
        $this->assertEquals(50 * 40000, (float) $purchase->total);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $material->id,
            'type' => 'entrada',
            'quantity' => 50,
        ]);
    }

    public function test_labels_page_requires_auth_and_renders_for_admin(): void
    {
        [$product] = $this->seedBasics();
        $order = ProductionOrder::create(['item_id' => $product->id, 'quantity' => 1]);
        $order->generatePipeline();

        // Sin sesión redirige al login.
        $this->get(route('orders.labels', $order))->assertRedirect();

        // Autenticado renderiza las etiquetas.
        $this->actingAs(User::factory()->create())
            ->get(route('orders.labels', $order))
            ->assertOk()
            ->assertSee('Etapa #1');
    }

    public function test_sale_confirm_does_not_discount_stock(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 10, 'price' => 85000]);

        $sale = Sale::create([]);
        $sale->items()->create([
            'item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 85000,
        ]);

        $sale->confirm();
        $product->refresh();
        $sale->refresh();

        $this->assertEquals(10, (float) $product->stock);
        $this->assertEquals(3 * 85000, (float) $sale->total);
        $this->assertSame(SaleStatus::Confirmada, $sale->status);
        $this->assertDatabaseMissing('stock_movements', [
            'item_id' => $product->id,
            'type' => 'salida',
            'source_type' => Sale::class,
            'source_id' => $sale->id,
        ]);
    }

    public function test_quote_pdf_requires_auth_and_renders(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['price' => 50000]);

        $quote = Quote::create([
            'notes' => 'Cotización de prueba',
            'valid_until' => now()->addDays(15),
        ]);
        $quote->items()->create([
            'item_id' => $product->id,
            'description' => $product->name,
            'quantity' => 2,
            'unit_price' => 50000,
        ]);
        $quote->refresh();

        $user = User::factory()->create();
        \Spatie\Permission\Models\Permission::findOrCreate('View:Quote', 'web');
        $user->givePermissionTo('View:Quote');

        $this->get(route('quotes.pdf', $quote))->assertRedirect();
        $this->actingAs($user)
            ->get(route('quotes.pdf', $quote))
            ->assertOk()
            ->assertSee($quote->code)
            ->assertSee('Cotización comercial')
            ->assertSee('Producto');
    }

    public function test_accepted_quote_creates_draft_sale(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 10]);
        Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $customer = Customer::create([
            'name' => 'Cliente Demo',
            'tax_id' => '900123456',
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'status' => QuoteStatus::Accepted,
            'customer_id' => $customer->id,
            'notes' => 'Aceptada por el cliente',
        ]);
        $quote->items()->create([
            'item_id' => $product->id,
            'description' => $product->name,
            'quantity' => 2,
            'unit_price' => 45000,
        ]);
        $quote->refresh();

        $corte = Process::query()->where('name', 'Corte')->firstOrFail();
        $orders = $quote->createProductionOrders(null, [$corte->id]);
        $this->assertCount(1, $orders);
        $this->assertFalse($quote->fresh()->canCreateSale());

        $orders->first()->forceFill(['status' => ProductionOrderStatus::Completado])->save();
        $this->assertTrue($quote->fresh()->canCreateSale());

        $sale = $quote->createSale();

        $this->assertSame(SaleStatus::Borrador, $sale->status);
        $this->assertSame($customer->id, $sale->customer_id);
        $this->assertSame($quote->id, $sale->quote_id);
        $this->assertEquals(90000, (float) $sale->total);
        $this->assertCount(1, $sale->items);
        $this->assertStringContainsString($quote->code, (string) $sale->notes);
        $this->assertFalse($quote->fresh()->canCreateSale());
        $this->assertCount(1, $sale->productionOrders);
        $this->assertSame($sale->id, $sale->productionOrders->first()->sale_id);

        $this->expectException(\InvalidArgumentException::class);
        $quote->createSale();
    }

    public function test_quote_creates_production_order_before_sale_with_selected_processes(): void
    {
        [$product] = $this->seedBasics();
        Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $corte = Process::query()->where('name', 'Corte')->firstOrFail();
        $pulido = Process::query()->where('name', 'Pulido')->firstOrFail();

        $customer = Customer::create([
            'name' => 'Cliente OP',
            'tax_id' => '900111222',
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'status' => QuoteStatus::Accepted,
            'customer_id' => $customer->id,
        ]);
        $quote->items()->create([
            'item_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $this->assertFalse($quote->canCreateSale());
        $this->assertTrue($quote->canCreateProductionOrders());

        $orders = $quote->createProductionOrders(null, [$corte->id]);
        $order = $orders->first();

        $this->assertNotNull($order);
        $this->assertSame($quote->id, $order->quote_id);
        $this->assertNull($order->sale_id);
        $this->assertEquals(1, (float) $order->quantity);
        $this->assertCount(1, $order->logs);
        $this->assertSame($corte->id, $order->logs->first()->process_id);
        $this->assertDatabaseMissing('production_logs', [
            'production_order_id' => $order->id,
            'process_id' => $pulido->id,
        ]);

        $this->assertFalse($quote->fresh()->canCreateSale());
        $order->forceFill(['status' => ProductionOrderStatus::Completado])->save();

        $sale = $quote->createSale();
        $this->assertSame($sale->id, $order->fresh()->sale_id);
    }

    public function test_cannot_create_sale_until_all_production_orders_are_finished(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 10]);
        $productB = Item::create([
            'sku' => 'PT-2',
            'name' => 'Producto B',
            'type' => ItemType::ProductoTerminado,
            'stock' => 10,
            'price' => 30000,
        ]);
        Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $corte = Process::query()->where('name', 'Corte')->firstOrFail();

        $customer = Customer::create([
            'name' => 'Cliente Multi OP',
            'tax_id' => '900333444',
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'status' => QuoteStatus::Accepted,
            'customer_id' => $customer->id,
        ]);
        $quote->items()->create([
            'item_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 45000,
        ]);
        $quote->items()->create([
            'item_id' => $productB->id,
            'description' => $productB->name,
            'quantity' => 1,
            'unit_price' => 30000,
        ]);

        $orders = $quote->fresh()->createProductionOrders(null, [$corte->id]);
        $this->assertCount(2, $orders);
        $this->assertFalse($quote->fresh()->canCreateSale());

        $orders->first()->forceFill(['status' => ProductionOrderStatus::Completado])->save();
        $this->assertFalse($quote->fresh()->canCreateSale());

        try {
            $quote->createSale();
            $this->fail('Expected InvalidArgumentException when some OPs are unfinished.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('completadas o entregadas', $e->getMessage());
        }

        $orders->last()->forceFill(['status' => ProductionOrderStatus::Entregado])->save();
        $this->assertTrue($quote->fresh()->canCreateSale());

        $sale = $quote->createSale();
        $this->assertCount(2, $sale->productionOrders);
    }

    public function test_quote_create_sale_requires_production_order_first(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 0]);
        Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $customer = Customer::create([
            'name' => 'Cliente Sin OP',
            'tax_id' => '900999888',
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'status' => QuoteStatus::Accepted,
            'customer_id' => $customer->id,
        ]);
        $quote->items()->create([
            'item_id' => $product->id,
            'description' => $product->name,
            'quantity' => 2,
            'unit_price' => 45000,
        ]);

        $this->assertFalse($quote->canCreateSale());

        try {
            $quote->createSale();
            $this->fail('Se esperaba InvalidArgumentException por falta de OP.');
        } catch (\InvalidArgumentException $e) {
            $this->assertStringContainsString('orden de producción', mb_strtolower($e->getMessage()));
        }

        $this->assertDatabaseMissing('sales', ['quote_id' => $quote->id]);
    }

    public function test_serna_quote_creates_one_op_per_piece_not_per_line(): void
    {
        $this->seedBasics();
        Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $servicio = Item::create([
            'sku' => 'SERNA-SERVICIO',
            'name' => 'Servicio / manufactura Serna',
            'type' => ItemType::ProductoTerminado,
            'stock' => 0,
        ]);
        $manual = Item::create([
            'sku' => 'SERNA-MANUAL',
            'name' => 'Transporte / instalación / valor manual',
            'type' => ItemType::ProductoTerminado,
            'stock' => 0,
        ]);

        $corte = Process::query()->where('name', 'Corte')->firstOrFail();
        $pulido = Process::query()->where('name', 'Pulido')->firstOrFail();

        $customer = Customer::create([
            'name' => 'Cliente Serna OP',
            'tax_id' => '900555666',
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'status' => QuoteStatus::Accepted,
            'customer_id' => $customer->id,
            'project_name' => 'AVISO NORTE',
        ]);

        $pieza1 = $quote->pieces()->create(['name' => 'FACHADA', 'sort_order' => 1]);
        $pieza2 = $quote->pieces()->create(['name' => 'INTERIOR', 'sort_order' => 2]);

        foreach ([$pieza1, $pieza2] as $pieza) {
            foreach (['Corte láser', 'Letras 3D', 'Vinilo', 'LED'] as $i => $desc) {
                $quote->items()->create([
                    'quote_piece_id' => $pieza->id,
                    'item_id' => $servicio->id,
                    'description' => $desc,
                    'quantity' => 1,
                    'unit_price' => 10000,
                    'meta' => [
                        'source' => 'serna_cotizador',
                        'calculation' => ['item_type' => 'corte_laser', 'material' => $desc],
                    ],
                    'sort_order' => $i,
                ]);
            }
        }

        $quote->items()->create([
            'quote_piece_id' => $pieza1->id,
            'item_id' => $manual->id,
            'description' => 'TRANSPORTE E INSTALACION',
            'quantity' => 1,
            'unit_price' => 850000,
            'meta' => [
                'source' => 'serna_cotizador',
                'calculation' => ['item_type' => 'precio_fijo', 'material' => 'TRANSPORTE E INSTALACION'],
            ],
            'sort_order' => 99,
        ]);

        $quote->refresh();
        $this->assertTrue($quote->isSernaQuote());
        $this->assertCount(2, $quote->productionJobs());
        $this->assertCount(8, $quote->finishedGoodLines());
        $this->assertFalse(
            $quote->finishedGoodLines()->contains(
                fn ($line): bool => str_contains((string) $line->description, 'TRANSPORTE')
            )
        );

        $orders = $quote->createProductionOrders(null, [$corte->id, $pulido->id]);

        $this->assertCount(2, $orders);
        $this->assertFalse($quote->fresh()->canCreateSale());

        foreach ($orders as $order) {
            $this->assertCount(2, $order->logs);
            $this->assertStringContainsString('Trabajo:', (string) $order->notes);
            $order->forceFill(['status' => ProductionOrderStatus::Completado])->save();
        }

        $this->assertTrue($quote->fresh()->canCreateSale());
    }

    public function test_serna_multi_piece_with_transport_sale_only_requires_finished_ops(): void
    {
        $this->seedBasics();
        Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $servicio = Item::create([
            'sku' => 'SERNA-SERVICIO',
            'name' => 'Servicio / manufactura Serna',
            'type' => ItemType::ProductoTerminado,
            'stock' => 0,
        ]);
        $manual = Item::create([
            'sku' => 'SERNA-MANUAL',
            'name' => 'Transporte / instalación / valor manual',
            'type' => ItemType::ProductoTerminado,
            'stock' => 0,
        ]);

        $corte = Process::query()->where('name', 'Corte')->firstOrFail();

        $customer = Customer::create([
            'name' => 'Cliente Serna Venta',
            'tax_id' => '900777888',
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'status' => QuoteStatus::Accepted,
            'customer_id' => $customer->id,
            'project_name' => 'AVISO SUR',
        ]);

        $pieza1 = $quote->pieces()->create(['name' => 'FACHADA', 'sort_order' => 1]);
        $pieza2 = $quote->pieces()->create(['name' => 'INTERIOR', 'sort_order' => 2]);

        foreach ([$pieza1, $pieza2] as $pieza) {
            $quote->items()->create([
                'quote_piece_id' => $pieza->id,
                'item_id' => $servicio->id,
                'description' => $pieza->name.' · Material: ACRILICO 3MM',
                'quantity' => 1,
                'unit_price' => 10000,
                'line_total' => 10000,
                'meta' => [
                    'source' => 'serna_cotizador',
                    'calculation' => ['item_type' => 'corte_laser', 'material' => 'ACRILICO 3MM'],
                ],
            ]);
            $quote->items()->create([
                'quote_piece_id' => $pieza->id,
                'item_id' => $servicio->id,
                'description' => $pieza->name.' · Material: LED',
                'quantity' => 1,
                'unit_price' => 5000,
                'line_total' => 5000,
                'meta' => [
                    'source' => 'serna_cotizador',
                    'calculation' => ['item_type' => 'iluminacion', 'material' => 'LED'],
                ],
            ]);
        }

        $quote->items()->create([
            'quote_piece_id' => $pieza1->id,
            'item_id' => $manual->id,
            'description' => 'TRANSPORTE E INSTALACION · Material: TRANSPORTE E INSTALACION',
            'quantity' => 1,
            'unit_price' => 850000,
            'line_total' => 850000,
            'meta' => [
                'source' => 'serna_cotizador',
                'calculation' => ['item_type' => 'precio_fijo', 'material' => 'TRANSPORTE E INSTALACION'],
            ],
        ]);

        $quote->refresh();
        $this->assertCount(2, $quote->productionJobs());
        $this->assertFalse(
            $quote->finishedGoodLines()->contains(
                fn ($line): bool => str_contains((string) $line->description, 'TRANSPORTE')
            )
        );

        $orders = $quote->createProductionOrders(null, [$corte->id]);
        $this->assertCount(2, $orders);
        $this->assertFalse($quote->fresh()->canCreateSale());

        foreach ($orders as $order) {
            $order->forceFill(['status' => ProductionOrderStatus::Completado])->save();
        }

        $this->assertTrue($quote->fresh()->canCreateSale());

        $sale = $quote->createSale();
        $this->assertCount(2, $sale->productionOrders);
        // 2 piezas agregadas (sin desglose) + transporte.
        $this->assertCount(3, $sale->items);
        $this->assertTrue(
            $sale->items->contains(
                fn ($line): bool => $line->description === 'FACHADA' && abs((float) $line->unit_price - 15000.0) < 0.01
            )
        );
        $this->assertTrue(
            $sale->items->contains(
                fn ($line): bool => $line->description === 'INTERIOR' && abs((float) $line->unit_price - 15000.0) < 0.01
            )
        );
        $this->assertTrue(
            $sale->items->contains(fn ($line): bool => abs((float) $line->unit_price - 850000.0) < 0.01)
        );
        $this->assertEquals(880000.0, (float) $sale->total);
    }

    public function test_create_sale_purges_trashed_sale_for_same_quote(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 10]);
        Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);
        $corte = Process::query()->where('name', 'Corte')->firstOrFail();

        $customer = Customer::create([
            'name' => 'Cliente Soft Delete',
            'tax_id' => '900121212',
            'is_active' => true,
        ]);

        $quote = Quote::create([
            'status' => QuoteStatus::Accepted,
            'customer_id' => $customer->id,
        ]);
        $quote->items()->create([
            'item_id' => $product->id,
            'description' => $product->name,
            'quantity' => 1,
            'unit_price' => 50000,
        ]);

        $order = $quote->createProductionOrders(null, [$corte->id])->first();
        $order->forceFill(['status' => ProductionOrderStatus::Completado])->save();

        $first = $quote->createSale();
        $first->delete();

        $this->assertTrue($quote->fresh()->canCreateSale());

        $second = $quote->fresh()->createSale();
        $this->assertNotSame($first->id, $second->id);
        $this->assertNull(Sale::withTrashed()->find($first->id));
        $this->assertSame($second->id, $order->fresh()->sale_id);
    }

    public function test_sale_confirm_allows_without_stock_for_now(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 1, 'price' => 10000]);

        $sale = Sale::create([]);
        $sale->items()->create([
            'item_id' => $product->id,
            'quantity' => 3,
            'unit_price' => 10000,
        ]);

        $sale->confirm();

        $this->assertSame(SaleStatus::Confirmada, $sale->fresh()->status);
        $this->assertEquals(1, (float) $product->fresh()->stock);
    }

    public function test_sale_reverse_returns_to_draft_for_editing(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 10, 'price' => 10000]);

        $sale = Sale::create([]);
        $sale->items()->create([
            'item_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 10000,
        ]);

        $sale->confirm();
        $sale->refresh();

        $this->assertSame(SaleStatus::Confirmada, $sale->status);
        $this->assertFalse($sale->isEditable());
        $this->assertNotNull($sale->invoice_number);

        $invoice = $sale->invoice_number;
        $sale->reverse();
        $sale->refresh();

        $this->assertSame(SaleStatus::Borrador, $sale->status);
        $this->assertTrue($sale->isEditable());
        $this->assertSame($invoice, $sale->invoice_number);
    }

    public function test_reports_export_returns_csv(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('reports.export.sales'))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_operator_scan_page_is_role_gated(): void
    {
        Role::findOrCreate('Operario', 'web');

        $operario = User::factory()->create();
        $operario->assignRole('Operario');

        $this->actingAs($operario)
            ->get('/admin/escaneo-operario')
            ->assertOk();

        // Un usuario sin rol no puede acceder a la estación.
        $this->actingAs(User::factory()->create())
            ->get('/admin/escaneo-operario')
            ->assertForbidden();
    }

    public function test_sale_confirm_assigns_incremental_fiscal_number(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 100, 'price' => 1000]);

        $first = Sale::create([]);
        $first->items()->create(['item_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]);
        $first->confirm();
        $first->refresh();

        $this->assertNotNull($first->invoice_number);
        $this->assertStringStartsWith('FV-', $first->invoice_number);

        $second = Sale::create([]);
        $second->items()->create(['item_id' => $product->id, 'quantity' => 1, 'unit_price' => 1000]);
        $second->confirm();
        $second->refresh();

        $this->assertSame($first->invoice_sequence + 1, $second->invoice_sequence);

        // Reconfirmar no reasigna el consecutivo.
        $number = $first->invoice_number;
        $first->confirm();
        $first->refresh();
        $this->assertSame($number, $first->invoice_number);
    }

    public function test_low_stock_transition_notifies_admins(): void
    {
        Notification::fake();

        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $material = Item::create([
            'sku' => 'MP-LOW',
            'name' => 'Material crítico',
            'type' => ItemType::MateriaPrima,
            'stock' => 10,
            'min_stock' => 5,
        ]);

        // 10 -> 4 cruza por debajo del mínimo: dispara la alerta.
        $material->registerMovement(\App\Enums\StockMovementType::Salida, 6);

        Notification::assertSentTo($admin, LowStockAlert::class);
    }

    public function test_check_low_stock_command_sends_digest(): void
    {
        Notification::fake();

        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        Item::create([
            'sku' => 'MP-CRIT',
            'name' => 'Bajo',
            'type' => ItemType::MateriaPrima,
            'stock' => 2,
            'min_stock' => 5,
        ]);
        Item::create([
            'sku' => 'MP-OK',
            'name' => 'Sano',
            'type' => ItemType::MateriaPrima,
            'stock' => 50,
            'min_stock' => 5,
        ]);

        $this->artisan('inventory:check-low-stock')->assertExitCode(0);

        Notification::assertSentTo($admin, LowStockDigest::class, function (LowStockDigest $n): bool {
            return $n->items->count() === 1;
        });
    }

    public function test_pos_page_checkout_creates_confirmed_sale(): void
    {
        Role::findOrCreate('super_admin', 'web');
        $admin = User::factory()->create();
        $admin->assignRole('super_admin');

        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);
        $product = Item::create([
            'sku' => 'PT-POS',
            'name' => 'Producto POS',
            'type' => ItemType::ProductoTerminado,
            'stock' => 10,
            'price' => 3000,
        ]);

        $this->actingAs($admin);

        Livewire::test(\App\Filament\Pages\PuntoDeVenta::class)
            ->call('addToCart', $product->id)
            ->call('addToCart', $product->id)
            ->call('checkout')
            ->assertHasNoErrors();

        $sale = Sale::query()->latest('id')->first();
        $this->assertNotNull($sale);
        $this->assertSame(\App\Enums\SaleStatus::Confirmada, $sale->status);
        $this->assertEquals(2, (float) $sale->items->sum('quantity'));

        $product->refresh();
        $this->assertEquals(8, (float) $product->stock);
    }

    public function test_electronic_invoice_job_accepts_with_fake_driver(): void
    {
        [$product] = $this->seedBasics();
        $product->update(['stock' => 10, 'price' => 5000]);

        $sale = Sale::create([]);
        $sale->items()->create(['item_id' => $product->id, 'quantity' => 2, 'unit_price' => 5000]);
        $sale->confirm();

        \App\Jobs\SubmitElectronicInvoice::dispatchSync($sale->refresh());
        $sale->refresh();

        $this->assertSame(\App\Enums\EInvoiceStatus::Aceptada, $sale->einvoice_status);
        $this->assertStringStartsWith('FAKE-', $sale->einvoice_uuid);
        $this->assertNotNull($sale->einvoiced_at);
    }

    public function test_multi_warehouse_tracks_stock_per_location(): void
    {
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);
        $pdv = Warehouse::create(['code' => 'PDV', 'name' => 'Punto de venta']);

        $item = Item::create([
            'sku' => 'MP-WH',
            'name' => 'Material multi-bodega',
            'type' => ItemType::MateriaPrima,
            'stock' => 0,
        ]);

        $item->registerMovement(StockMovementType::Entrada, 100, warehouseId: $main->id);
        $item->registerMovement(StockMovementType::Entrada, 20, warehouseId: $pdv->id);
        $item->refresh();

        $this->assertEquals(120, (float) $item->stock);
        $this->assertEquals(100, $item->stockInWarehouse($main->id));
        $this->assertEquals(20, $item->stockInWarehouse($pdv->id));

        $item->registerMovement(StockMovementType::Salida, 30, warehouseId: $main->id);
        $item->refresh();

        $this->assertEquals(90, (float) $item->stock);
        $this->assertEquals(70, $item->stockInWarehouse($main->id));
    }

    public function test_sale_return_reenters_stock_and_marks_sale_returned(): void
    {
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $product = Item::create([
            'sku' => 'PT-RET',
            'name' => 'Producto devolución',
            'type' => ItemType::ProductoTerminado,
            'stock' => 10,
            'price' => 5000,
        ]);

        $sale = Sale::create(['warehouse_id' => $main->id]);
        $sale->items()->create(['item_id' => $product->id, 'quantity' => 4, 'unit_price' => 5000]);
        $sale->confirm();
        $product->refresh();
        // Confirmación ya no descuenta inventario.
        $this->assertEquals(10, (float) $product->stock);

        $return = $sale->returns()->create(['warehouse_id' => $main->id]);
        $return->items()->create(['item_id' => $product->id, 'quantity' => 4, 'unit_price' => 5000]);
        $return->load('items');
        $return->apply();

        $product->refresh();
        $sale->refresh();

        $this->assertEquals(14, (float) $product->stock);
        $this->assertSame(\App\Enums\SaleStatus::Devuelta, $sale->status);
        $this->assertStringStartsWith('NC-', $return->code);
        $this->assertDatabaseHas('stock_movements', [
            'item_id' => $product->id,
            'type' => 'entrada',
            'source_type' => SaleReturn::class,
        ]);
    }

    public function test_purchase_return_discounts_stock(): void
    {
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $material = Item::create([
            'sku' => 'MP-RET',
            'name' => 'Material devolución',
            'type' => ItemType::MateriaPrima,
            'stock' => 0,
        ]);

        $supplier = Supplier::create(['name' => 'Prov']);
        $purchase = Purchase::create(['supplier_id' => $supplier->id, 'warehouse_id' => $main->id]);
        $purchase->items()->create(['item_id' => $material->id, 'quantity' => 100, 'unit_cost' => 10]);
        $purchase->receive();
        $material->refresh();
        $this->assertEquals(100, (float) $material->stock);

        $return = $purchase->returns()->create(['warehouse_id' => $main->id]);
        $return->items()->create(['item_id' => $material->id, 'quantity' => 30, 'unit_cost' => 10]);
        $return->load('items');
        $return->apply();

        $material->refresh();
        $this->assertEquals(70, (float) $material->stock);
        $this->assertStringStartsWith('DC-', $return->code);
    }

    public function test_movement_migrates_opening_balance_to_default_warehouse(): void
    {
        $main = Warehouse::create(['code' => 'MAIN', 'name' => 'Principal', 'is_default' => true]);

        $item = Item::create([
            'sku' => 'MP-OPEN',
            'name' => 'Con saldo inicial',
            'type' => ItemType::MateriaPrima,
            'stock' => 50,
        ]);

        // Primer movimiento: el saldo de apertura (50) migra a la bodega principal.
        $item->registerMovement(StockMovementType::Salida, 10, warehouseId: $main->id);
        $item->refresh();

        $this->assertEquals(40, (float) $item->stock);
        $this->assertEquals(40, $item->stockInWarehouse($main->id));
    }
}
