<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_supplier', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->constrained()->cascadeOnDelete();
            $table->string('supplier_sku')->nullable();
            $table->decimal('last_purchase_cost', 15, 2)->nullable();
            $table->unsignedInteger('lead_time_days')->nullable();
            $table->timestamps();

            $table->unique(['item_id', 'supplier_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_supplier');
    }
};
