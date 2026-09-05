<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->string('sidebar_color', 7)->nullable()->after('sidebar_theme');
        });

        DB::table('company_settings')
            ->whereNull('sidebar_color')
            ->where('sidebar_theme', 'indigo')
            ->update(['sidebar_color' => '#313A82']);

        DB::table('company_settings')
            ->whereNull('sidebar_color')
            ->where('sidebar_theme', 'light')
            ->update(['sidebar_color' => '#FFFFFF']);
    }

    public function down(): void
    {
        Schema::table('company_settings', function (Blueprint $table) {
            $table->dropColumn('sidebar_color');
        });
    }
};
