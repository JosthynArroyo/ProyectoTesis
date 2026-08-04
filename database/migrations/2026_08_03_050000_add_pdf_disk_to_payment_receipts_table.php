<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_receipts')) {
            Schema::table('payment_receipts', function (Blueprint $table) {
                if (! Schema::hasColumn('payment_receipts', 'pdf_disk')) {
                    $table->string('pdf_disk', 50)->nullable()->after('pdf_path');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_receipts')) {
            Schema::table('payment_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('payment_receipts', 'pdf_disk')) {
                    $table->dropColumn('pdf_disk');
                }
            });
        }
    }
};
