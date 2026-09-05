<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_order_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            // Materia prima o insumo a consumir.
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->decimal('quantity_required', 15, 4);
            $table->decimal('quantity_consumed', 15, 4)->default(0);
            $table->timestamps();

            $table->unique(['production_order_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_order_requirements');
    }
};
