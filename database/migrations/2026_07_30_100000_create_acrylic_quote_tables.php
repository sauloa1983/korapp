<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('acrylic_materials', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->decimal('thickness_mm', 8, 2);
            $table->decimal('price_per_m2', 14, 2)->default(0);
            $table->decimal('waste_percent', 5, 2)->default(10);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('acrylic_lighting_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('pricing_mode', 32)->default('none');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->decimal('power_supply_cost', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('acrylic_finish_options', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('type', 32)->default('other');
            $table->string('pricing_mode', 32)->default('fixed');
            $table->decimal('unit_price', 14, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('acrylic_pricing_settings', function (Blueprint $table) {
            $table->id();
            $table->decimal('labor_fixed_cost', 14, 2)->default(0);
            $table->decimal('labor_per_m2', 14, 2)->default(0);
            $table->decimal('margin_percent', 5, 2)->default(35);
            $table->timestamps();
        });

        Schema::table('quote_items', function (Blueprint $table) {
            $table->json('meta')->nullable()->after('line_total');
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            $table->dropColumn('meta');
        });

        Schema::dropIfExists('acrylic_pricing_settings');
        Schema::dropIfExists('acrylic_finish_options');
        Schema::dropIfExists('acrylic_lighting_options');
        Schema::dropIfExists('acrylic_materials');
    }
};
