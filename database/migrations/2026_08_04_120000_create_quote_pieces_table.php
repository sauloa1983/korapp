<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('quote_pieces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quote_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['quote_id', 'sort_order']);
        });

        Schema::table('quote_items', function (Blueprint $table) {
            if (! Schema::hasColumn('quote_items', 'quote_piece_id')) {
                $table->foreignId('quote_piece_id')
                    ->nullable()
                    ->after('quote_id')
                    ->constrained('quote_pieces')
                    ->nullOnDelete();
            }
            if (! Schema::hasColumn('quote_items', 'sort_order')) {
                $table->unsignedInteger('sort_order')->default(0)->after('meta');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quote_items', function (Blueprint $table) {
            if (Schema::hasColumn('quote_items', 'quote_piece_id')) {
                $table->dropConstrainedForeignId('quote_piece_id');
            }
            if (Schema::hasColumn('quote_items', 'sort_order')) {
                $table->dropColumn('sort_order');
            }
        });

        Schema::dropIfExists('quote_pieces');
    }
};
