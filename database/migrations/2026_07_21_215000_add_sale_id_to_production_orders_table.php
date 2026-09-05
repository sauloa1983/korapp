<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->foreignId('sale_id')
                ->nullable()
                ->after('warehouse_id')
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('sale_item_id')
                ->nullable()
                ->after('sale_id')
                ->constrained()
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sale_item_id');
            $table->dropConstrainedForeignId('sale_id');
        });
    }
};
