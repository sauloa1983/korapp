<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['item_id']);
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropUnique(['sale_id', 'item_id']);
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->text('description')->nullable()->after('item_id');
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
            $table->index(['sale_id', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::table('sale_items', function (Blueprint $table): void {
            $table->dropForeign(['sale_id']);
            $table->dropForeign(['item_id']);
            $table->dropIndex(['sale_id', 'item_id']);
            $table->dropColumn('description');
        });

        Schema::table('sale_items', function (Blueprint $table): void {
            $table->foreign('sale_id')->references('id')->on('sales')->cascadeOnDelete();
            $table->foreign('item_id')->references('id')->on('items')->restrictOnDelete();
            $table->unique(['sale_id', 'item_id']);
        });
    }
};
