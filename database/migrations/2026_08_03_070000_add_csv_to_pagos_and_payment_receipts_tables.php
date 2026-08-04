<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('pagos')) {
            Schema::table('pagos', function (Blueprint $table) {
                if (! Schema::hasColumn('pagos', 'csv')) {
                    $table->string('csv', 20)->nullable()->unique()->after('token_publico');
                }
            });
        }

        if (Schema::hasTable('payment_receipts')) {
            Schema::table('payment_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_receipts', 'csv')) {
                    $table->string('csv', 20)->nullable()->unique()->after('verification_token');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pagos')) {
            Schema::table('pagos', function (Blueprint $table) {
                if (Schema::hasColumn('pagos', 'csv')) {
                    $table->dropColumn('csv');
                }
            });
        }

        if (Schema::hasTable('payment_receipts')) {
            Schema::table('payment_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('payment_receipts', 'csv')) {
                    $table->dropColumn('csv');
                }
            });
        }
    }
};
