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
                if (! Schema::hasColumn('pagos', 'comprobante_disk')) {
                    $table->string('comprobante_disk', 50)->nullable()->after('comprobante_path');
                }
            });
        }

        if (Schema::hasTable('payment_receipts')) {
            Schema::table('payment_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_receipts', 'comprobante_disk')) {
                    $table->string('comprobante_disk', 50)->nullable()->after('comprobante_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('pagos')) {
            Schema::table('pagos', function (Blueprint $table) {
                if (Schema::hasColumn('pagos', 'comprobante_disk')) {
                    $table->dropColumn('comprobante_disk');
                }
            });
        }

        if (Schema::hasTable('payment_receipts')) {
            Schema::table('payment_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('payment_receipts', 'comprobante_disk')) {
                    $table->dropColumn('comprobante_disk');
                }
            });
        }
    }
};
