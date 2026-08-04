<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pedidos_laboratorio', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos_laboratorio', 'sample_collected_by')) {
                $table->unsignedBigInteger('sample_collected_by')->nullable()->after('sample_collected_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('pedidos_laboratorio', function (Blueprint $table) {
            if (Schema::hasColumn('pedidos_laboratorio', 'sample_collected_by')) {
                $table->dropColumn('sample_collected_by');
            }
        });
    }
};
