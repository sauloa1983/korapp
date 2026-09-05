<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->boolean('charges_iva')->default(false)->after('tax_id');
            $table->decimal('iva_rate', 5, 2)->default(0)->after('charges_iva');
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table): void {
            $table->dropColumn(['charges_iva', 'iva_rate']);
        });
    }
};
