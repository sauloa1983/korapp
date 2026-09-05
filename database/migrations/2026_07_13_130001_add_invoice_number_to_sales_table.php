<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            // Consecutivo fiscal formal, asignado sólo al confirmar la venta.
            $table->unsignedBigInteger('invoice_sequence')->nullable()->unique()->after('code');
            $table->string('invoice_number')->nullable()->unique()->after('invoice_sequence');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['invoice_sequence', 'invoice_number']);
        });
    }
};
