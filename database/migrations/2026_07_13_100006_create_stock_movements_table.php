<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // entrada | salida | ajuste
            $table->string('type')->index();
            $table->decimal('quantity', 15, 4);
            $table->decimal('balance_after', 15, 4)->nullable();
            $table->decimal('unit_cost', 15, 2)->nullable();
            // Origen polimórfico: compra, venta, orden de producción, ajuste manual...
            $table->nullableMorphs('source');
            $table->string('reference')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
