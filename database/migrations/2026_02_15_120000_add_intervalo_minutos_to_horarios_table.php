<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            if (!Schema::hasColumn('horarios', 'intervalo_minutos')) {
                $table->unsignedSmallInteger('intervalo_minutos')->default(30)->after('hora_fin');
            }
        });
    }

    public function down(): void
    {
        Schema::table('horarios', function (Blueprint $table) {
            if (Schema::hasColumn('horarios', 'intervalo_minutos')) {
                $table->dropColumn('intervalo_minutos');
            }
        });
    }
};
