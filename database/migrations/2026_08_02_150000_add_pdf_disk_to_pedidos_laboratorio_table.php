<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_laboratorio', function (Blueprint $table) {
            if (! Schema::hasColumn('pedidos_laboratorio', 'pdf_disk')) {
                $table->string('pdf_disk', 50)->nullable()->after('pdf_path');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_laboratorio', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos_laboratorio', 'pdf_disk')) {
                $table->dropColumn('pdf_disk');
            }
        });
    }
};
