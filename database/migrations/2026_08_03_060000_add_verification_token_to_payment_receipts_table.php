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
                if (! Schema::hasColumn('payment_receipts', 'verification_token')) {
                    $table->string('verification_token', 64)->nullable()->unique()->after('pdf_disk');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('payment_receipts')) {
            Schema::table('payment_receipts', function (Blueprint $table) {
                if (Schema::hasColumn('payment_receipts', 'verification_token')) {
                    $table->dropColumn('verification_token');
                }
            });
        }
    }
};
