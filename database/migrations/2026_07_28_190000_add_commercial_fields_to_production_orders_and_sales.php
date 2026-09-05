<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->string('contact_name')->nullable()->after('notes');
            $table->foreignId('quoted_by_user_id')->nullable()->after('contact_name')->constrained('users')->nullOnDelete();
            $table->string('payment_method')->nullable()->after('quoted_by_user_id');
            $table->decimal('advance_amount', 14, 2)->nullable()->after('payment_method');
            $table->string('file_path')->nullable()->after('advance_amount');
            $table->boolean('has_plans')->default(false)->after('file_path');
            $table->string('plans_attachment')->nullable()->after('has_plans');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->string('payment_method')->nullable()->after('notes');
            $table->decimal('advance_amount', 14, 2)->nullable()->after('payment_method');
        });
    }

    public function down(): void
    {
        Schema::table('production_orders', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('quoted_by_user_id');
            $table->dropColumn([
                'contact_name',
                'payment_method',
                'advance_amount',
                'file_path',
                'has_plans',
                'plans_attachment',
            ]);
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn(['payment_method', 'advance_amount']);
        });
    }
};
