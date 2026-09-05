<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->foreignId('quote_id')->nullable()->after('warehouse_id')->constrained('quotes')->nullOnDelete();
            $table->foreignId('quote_item_id')->nullable()->after('quote_id')->constrained('quote_items')->nullOnDelete();
            $table->timestamp('delivered_at')->nullable()->after('completed_at');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quote_item_id');
            $table->dropConstrainedForeignId('quote_id');
            $table->dropColumn('delivered_at');
        });
    }
};
