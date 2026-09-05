<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            if (! Schema::hasColumn('quotes', 'project_name')) {
                $table->string('project_name')->nullable()->after('code');
            }
            if (! Schema::hasColumn('quotes', 'contact_name')) {
                $table->string('contact_name')->nullable()->after('lead_id');
            }
            if (! Schema::hasColumn('quotes', 'payment_form')) {
                $table->string('payment_form')->nullable()->after('advance_percent');
            }
            if (! Schema::hasColumn('quotes', 'validity_days')) {
                $table->unsignedSmallInteger('validity_days')->default(10)->after('valid_until');
            }
            if (! Schema::hasColumn('quotes', 'delivery_note')) {
                $table->string('delivery_note')->nullable()->after('delivery_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotes', function (Blueprint $table) {
            $columns = collect([
                'project_name',
                'contact_name',
                'payment_form',
                'validity_days',
                'delivery_note',
            ])
                ->filter(fn (string $column): bool => Schema::hasColumn('quotes', $column))
                ->all();

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
