<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->foreignId('quote_id')
                ->nullable()
                ->after('customer_id')
                ->constrained()
                ->nullOnDelete();

            $table->unique('quote_id');
        });
    }

    public function down(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->dropUnique(['quote_id']);
            $table->dropConstrainedForeignId('quote_id');
        });
    }
};
