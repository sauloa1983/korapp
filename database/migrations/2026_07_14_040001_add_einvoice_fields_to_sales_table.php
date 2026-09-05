<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->string('einvoice_status')->default('pendiente')->after('invoice_number')->index();
            // CUFE/UUID devuelto por la DIAN o el proveedor tecnológico.
            $table->string('einvoice_uuid')->nullable()->after('einvoice_status');
            $table->json('einvoice_response')->nullable()->after('einvoice_uuid');
            $table->timestamp('einvoiced_at')->nullable()->after('einvoice_response');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['einvoice_status', 'einvoice_uuid', 'einvoice_response', 'einvoiced_at']);
        });
    }
};
