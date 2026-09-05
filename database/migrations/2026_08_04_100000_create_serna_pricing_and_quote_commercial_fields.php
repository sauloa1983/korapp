<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('serna_process_rates', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('category', 64);
            $table->decimal('thickness_mm_min', 8, 2)->nullable();
            $table->decimal('thickness_mm_max', 8, 2)->nullable();
            $table->decimal('price_per_cm2', 14, 4)->default(0);
            $table->decimal('min_charge', 14, 2)->nullable();
            $table->unsignedSmallInteger('year')->default(2026);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::create('serna_sheet_prices', function (Blueprint $table) {
            $table->id();
            $table->string('format', 32);
            $table->decimal('width_cm', 8, 2);
            $table->decimal('height_cm', 8, 2);
            $table->decimal('thickness_mm', 8, 2);
            $table->string('finish', 64);
            $table->decimal('price', 14, 2);
            $table->string('price_source', 64)->default('official');
            $table->unsignedSmallInteger('year')->default(2026);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['format', 'thickness_mm', 'finish', 'year'], 'serna_sheets_unique');
            $table->index(['format', 'is_active']);
        });

        Schema::create('serna_catalog_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category', 64);
            $table->json('specs')->nullable();
            $table->decimal('unit_price', 14, 2);
            $table->string('pricing_mode', 32)->default('fixed');
            $table->unsignedSmallInteger('year')->default(2026);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'is_active']);
        });

        Schema::table('customers', function (Blueprint $table) {
            if (! Schema::hasColumn('customers', 'is_retenedor')) {
                $table->boolean('is_retenedor')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('customers', 'retenedor_percent')) {
                $table->decimal('retenedor_percent', 5, 2)->default(0)->after('is_retenedor');
            }
        });

        Schema::table('quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('quotes', 'delivery_date')) {
                $table->date('delivery_date')->nullable()->after('valid_until');
            }
            if (! Schema::hasColumn('quotes', 'withholding_rate')) {
                $table->decimal('withholding_rate', 5, 2)->default(0)->after('iva_amount');
            }
            if (! Schema::hasColumn('quotes', 'withholding_amount')) {
                $table->decimal('withholding_amount', 14, 2)->default(0)->after('withholding_rate');
            }
            if (! Schema::hasColumn('quotes', 'advance_percent')) {
                $table->decimal('advance_percent', 5, 2)->default(50)->after('withholding_amount');
            }
            if (! Schema::hasColumn('quotes', 'terms')) {
                $table->text('terms')->nullable()->after('notes');
            }
        });

        Schema::table('company_settings', function (Blueprint $table) {
            if (! Schema::hasColumn('company_settings', 'bank_name')) {
                $table->string('bank_name')->nullable()->after('website');
            }
            if (! Schema::hasColumn('company_settings', 'bank_account_type')) {
                $table->string('bank_account_type')->nullable()->after('bank_name');
            }
            if (! Schema::hasColumn('company_settings', 'bank_account_number')) {
                $table->string('bank_account_number')->nullable()->after('bank_account_type');
            }
            if (! Schema::hasColumn('company_settings', 'bank_account_holder')) {
                $table->string('bank_account_holder')->nullable()->after('bank_account_number');
            }
        });
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $columns = collect(['bank_name', 'bank_account_type', 'bank_account_number', 'bank_account_holder'])
                ->filter(fn (string $column): bool => Schema::hasColumn('company_settings', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('quotes', function (Blueprint $table) {
            $columns = collect(['delivery_date', 'withholding_rate', 'withholding_amount', 'advance_percent', 'terms'])
                ->filter(fn (string $column): bool => Schema::hasColumn('quotes', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('customers', function (Blueprint $table) {
            $columns = collect(['is_retenedor', 'retenedor_percent'])
                ->filter(fn (string $column): bool => Schema::hasColumn('customers', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::dropIfExists('serna_catalog_products');
        Schema::dropIfExists('serna_sheet_prices');
        Schema::dropIfExists('serna_process_rates');
    }
};
