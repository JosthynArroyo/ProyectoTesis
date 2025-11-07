<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (
            Schema::hasTable('citas_medicas') &&
            Schema::hasColumn('citas_medicas','doctor_id') &&
            Schema::hasColumn('citas_medicas','fecha') &&
            Schema::hasColumn('citas_medicas','hora')
        ) {
            Schema::table('citas_medicas', function (Blueprint $table) {
                $table->unique(
                    ['doctor_id','fecha','hora'],
                    'citas_medicas_doctor_fecha_hora_unique'
                );
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('citas_medicas')) {
            Schema::table('citas_medicas', function (Blueprint $table) {
                $table->dropUnique('citas_medicas_doctor_fecha_hora_unique');
            });
        }
    }
};
