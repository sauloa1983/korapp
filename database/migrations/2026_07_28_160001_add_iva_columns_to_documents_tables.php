<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['sales', 'quotes', 'purchases', 'sale_returns', 'purchase_returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                if (! Schema::hasColumn($tableName, 'subtotal')) {
                    $table->decimal('subtotal', 15, 2)->default(0);
                }

                if (! Schema::hasColumn($tableName, 'iva_rate')) {
                    $table->decimal('iva_rate', 5, 2)->default(0);
                }

                if (! Schema::hasColumn($tableName, 'iva_amount')) {
                    $table->decimal('iva_amount', 15, 2)->default(0);
                }
            });

            DB::table($tableName)->update([
                'subtotal' => DB::raw('total'),
                'iva_rate' => 0,
                'iva_amount' => 0,
            ]);
        }
    }

    public function down(): void
    {
        foreach (['sales', 'quotes', 'purchases', 'sale_returns', 'purchase_returns'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $columns = collect(['subtotal', 'iva_rate', 'iva_amount'])
                    ->filter(fn (string $column): bool => Schema::hasColumn($tableName, $column))
                    ->values()
                    ->all();

                if ($columns !== []) {
                    $table->dropColumn($columns);
                }
            });
        }
    }
};
