<?php

namespace Database\Seeders;

use App\Enums\ItemType;
use App\Models\Customer;
use App\Models\Item;
use App\Models\ItemCategory;
use App\Models\ProductionOrder;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Database\Seeder;

class KorappSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ProductionProcessesSeeder::class);

        // -------------------------------------------------------------
        // Bodegas (multi-bodega). La principal es la predeterminada.
        // -------------------------------------------------------------
        $mainWarehouse = Warehouse::firstOrCreate(['code' => 'PRINCIPAL'], [
            'name' => 'Bodega Principal',
            'is_default' => true,
            'is_active' => true,
        ]);

        Warehouse::firstOrCreate(['code' => 'PDV'], [
            'name' => 'Punto de Venta',
            'is_default' => false,
            'is_active' => true,
        ]);

        // -------------------------------------------------------------
        // Categorías de inventario.
        // -------------------------------------------------------------
        $laminas = ItemCategory::firstOrCreate(['name' => 'Láminas de acrílico']);
        $herrajes = ItemCategory::firstOrCreate(['name' => 'Herrajes']);
        $pegantes = ItemCategory::firstOrCreate(['name' => 'Pegantes y adhesivos']);
        $terminados = ItemCategory::firstOrCreate(['name' => 'Productos terminados']);

        // -------------------------------------------------------------
        // Items (inventario único: materia prima, insumos, terminados).
        // -------------------------------------------------------------
        $lamina = Item::firstOrCreate(['sku' => 'MP-LAM-3MM-TRANS'], [
            'item_category_id' => $laminas->id,
            'name' => 'Lámina acrílico 3mm transparente',
            'type' => ItemType::MateriaPrima,
            'unit_of_measure' => 'm2',
            'stock' => 120,
            'min_stock' => 20,
            'cost' => 45000,
            'price' => 0,
        ]);

        $herraje = Item::firstOrCreate(['sku' => 'INS-HER-SOP-01'], [
            'item_category_id' => $herrajes->id,
            'name' => 'Soporte metálico exhibidor',
            'type' => ItemType::Insumo,
            'unit_of_measure' => 'unidad',
            'stock' => 500,
            'min_stock' => 100,
            'cost' => 3500,
            'price' => 0,
        ]);

        $pegante = Item::firstOrCreate(['sku' => 'INS-PEG-CIA-30'], [
            'item_category_id' => $pegantes->id,
            'name' => 'Pegante cianoacrilato 30ml',
            'type' => ItemType::Insumo,
            'unit_of_measure' => 'unidad',
            'stock' => 80,
            'min_stock' => 15,
            'cost' => 8000,
            'price' => 0,
        ]);

        $exhibidor = Item::firstOrCreate(['sku' => 'PT-EXH-ACR-01'], [
            'item_category_id' => $terminados->id,
            'name' => 'Exhibidor acrílico 3 niveles',
            'type' => ItemType::ProductoTerminado,
            'unit_of_measure' => 'unidad',
            'stock' => 0,
            'min_stock' => 0,
            'cost' => 0,
            'price' => 85000,
        ]);

        // Backfill de saldos de apertura en la bodega principal.
        foreach ([$lamina, $herraje, $pegante, $exhibidor] as $item) {
            if (! $item->warehouses()->where('warehouse_id', $mainWarehouse->id)->exists()) {
                $item->warehouses()->attach($mainWarehouse->id, ['stock' => (float) $item->stock]);
            }
        }

        // -------------------------------------------------------------
        // Proveedor (Línea 1) enlazado a la materia prima e insumos.
        // -------------------------------------------------------------
        $supplier = Supplier::firstOrCreate(['name' => 'Acrílicos del Pacífico S.A.S.'], [
            'tax_id' => '900123456-7',
            'email' => 'ventas@acrilicospacifico.com',
            'phone' => '+57 320 000 0000',
            'is_active' => true,
        ]);

        $supplier->items()->syncWithoutDetaching([
            $lamina->id => ['supplier_sku' => 'AP-LAM-3T', 'last_purchase_cost' => 45000, 'lead_time_days' => 5],
            $herraje->id => ['supplier_sku' => 'AP-SOP-01', 'last_purchase_cost' => 3500, 'lead_time_days' => 3],
        ]);

        // -------------------------------------------------------------
        // Orden de producción demo (Línea 2) con pipeline y consumo.
        // -------------------------------------------------------------
        if (ProductionOrder::query()->doesntExist()) {
            $order = ProductionOrder::create([
                'item_id' => $exhibidor->id,
                'quantity' => 10,
                'due_at' => now()->addDays(7),
                'notes' => 'Orden demo generada por el seeder.',
            ]);

            $order->requirements()->createMany([
                ['item_id' => $lamina->id, 'quantity_required' => 15],
                ['item_id' => $herraje->id, 'quantity_required' => 30],
                ['item_id' => $pegante->id, 'quantity_required' => 5],
            ]);

            // Clona el catálogo de procesos activos como pipeline con QR único.
            $order->generatePipeline();
        }

        // -------------------------------------------------------------
        // Compra demo (Línea 1) en borrador, lista para "Recibir".
        // -------------------------------------------------------------
        if (Purchase::query()->doesntExist()) {
            $purchase = Purchase::create([
                'supplier_id' => $supplier->id,
                'notes' => 'Compra demo generada por el seeder.',
            ]);

            $purchase->items()->createMany([
                ['item_id' => $lamina->id, 'quantity' => 50, 'unit_cost' => 45000],
                ['item_id' => $herraje->id, 'quantity' => 200, 'unit_cost' => 3500],
            ]);

            $purchase->recalculateTotal();
        }

        // -------------------------------------------------------------
        // Venta demo (Línea 1) en borrador, lista para "Confirmar".
        // -------------------------------------------------------------
        if (Sale::query()->doesntExist()) {
            $customer = Customer::firstOrCreate(['name' => 'Cliente Mostrador'], [
                'tax_id' => '222222222-2',
                'is_active' => true,
            ]);

            $sale = Sale::create([
                'customer_id' => $customer->id,
                'notes' => 'Venta demo generada por el seeder.',
            ]);

            $sale->items()->create([
                'item_id' => $exhibidor->id,
                'quantity' => 2,
                'unit_price' => $exhibidor->price,
            ]);

            $sale->recalculateTotal();
        }
    }
}
