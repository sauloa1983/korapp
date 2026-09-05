<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('production_order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('process_id')->constrained()->restrictOnDelete();
            // Operario responsable de la etapa.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // Token único por etapa/orden que se codifica en el QR.
            $table->string('qr_token')->unique();
            // Posición de la etapa dentro del flujo de esta orden.
            $table->unsignedInteger('sequence')->default(0);
            // en_espera | procesando | terminado
            $table->string('status')->default('en_espera')->index();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            // Duración calculada al finalizar, base para la reportería de tiempos.
            $table->unsignedBigInteger('duration_seconds')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['production_order_id', 'process_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_logs');
    }
};
