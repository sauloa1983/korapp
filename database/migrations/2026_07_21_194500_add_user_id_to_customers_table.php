<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->foreignId('user_id')
                ->nullable()
                ->after('is_active')
                ->constrained()
                ->nullOnDelete();
        });

        // Hereda el vendedor del prospecto que originó al cliente.
        $leads = DB::table('leads')
            ->whereNotNull('customer_id')
            ->whereNotNull('user_id')
            ->orderByDesc('converted_at')
            ->get(['customer_id', 'user_id']);

        foreach ($leads as $lead) {
            DB::table('customers')
                ->where('id', $lead->customer_id)
                ->whereNull('user_id')
                ->update(['user_id' => $lead->user_id]);
        }
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });
    }
};
