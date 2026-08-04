<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            if (! Schema::hasColumn('citas_medicas', 'comprobante_pdf_disk')) {
                $table->string('comprobante_pdf_disk', 50)->nullable()->after('comprobante_pdf_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            if (Schema::hasColumn('citas_medicas', 'comprobante_pdf_disk')) {
                $table->dropColumn('comprobante_pdf_disk');
            }
        });
    }
};
