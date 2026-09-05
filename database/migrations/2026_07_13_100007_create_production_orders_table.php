<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            // Producto terminado que se va a fabricar.
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            // Usuario que solicita / crea la orden.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('quantity', 15, 4);
            // pendiente | en_progreso | completado | cancelado
            $table->string('status')->default('pendiente')->index();
            $table->text('notes')->nullable();
            $table->date('requested_at')->nullable();
            $table->date('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
