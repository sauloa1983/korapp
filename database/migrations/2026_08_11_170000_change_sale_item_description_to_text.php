<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE sale_items MODIFY description TEXT NULL');
        }

        // Empareja por precio + cantidad (el orden id cotización ≠ orden líneas venta).
        $sales = DB::table('sales')->whereNotNull('quote_id')->pluck('id', 'quote_id');

        foreach ($sales as $quoteId => $saleId) {
            $quoteItems = DB::table('quote_items')
                ->where('quote_id', $quoteId)
                ->get(['id', 'description', 'unit_price', 'quantity']);

            $saleItems = DB::table('sale_items')
                ->where('sale_id', $saleId)
                ->get(['id', 'unit_price', 'quantity', 'description']);

            $used = [];
            foreach ($saleItems as $saleItem) {
                foreach ($quoteItems as $quoteItem) {
                    if (isset($used[$quoteItem->id])) {
                        continue;
                    }

                    if (
                        abs((float) $quoteItem->unit_price - (float) $saleItem->unit_price) < 0.01
                        && abs((float) $quoteItem->quantity - (float) $saleItem->quantity) < 0.0001
                        && filled($quoteItem->description)
                    ) {
                        DB::table('sale_items')->where('id', $saleItem->id)->update([
                            'description' => $quoteItem->description,
                        ]);
                        $used[$quoteItem->id] = true;
                        break;
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE sale_items MODIFY description VARCHAR(255) NULL');
        }
    }
};
