<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acrylic_lettering_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32)->default('none');
            $table->string('pricing_mode', 32)->default('none');
            $table->decimal('price_per_m2', 14, 2)->default(0);
            $table->decimal('cut_price_per_meter', 14, 2)->default(0);
            $table->decimal('fixed_price', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('acrylic_pricing_settings', function (Blueprint $table) {
            $table->decimal('assembly_percent_of_lettering', 5, 2)->default(15)->after('labor_per_m2');
        });
    }

    public function down(): void
    {
        Schema::table('acrylic_pricing_settings', function (Blueprint $table) {
            $table->dropColumn('assembly_percent_of_lettering');
        });

        Schema::dropIfExists('acrylic_lettering_options');
    }
};
