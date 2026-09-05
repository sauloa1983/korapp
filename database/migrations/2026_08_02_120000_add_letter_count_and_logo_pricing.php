<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('acrylic_lettering_options', function (Blueprint $table) {
            $table->decimal('price_per_letter', 14, 2)->default(0)->after('fixed_price');
        });

        Schema::table('acrylic_pricing_settings', function (Blueprint $table) {
            $table->decimal('logo_price_per_m2', 14, 2)->default(180000)->after('assembly_percent_of_lettering');
            $table->decimal('logo_fixed_cost', 14, 2)->default(0)->after('logo_price_per_m2');
        });
    }

    public function down(): void
    {
        Schema::table('acrylic_lettering_options', function (Blueprint $table) {
            $table->dropColumn('price_per_letter');
        });

        Schema::table('acrylic_pricing_settings', function (Blueprint $table) {
            $table->dropColumn(['logo_price_per_m2', 'logo_fixed_cost']);
        });
    }
};
