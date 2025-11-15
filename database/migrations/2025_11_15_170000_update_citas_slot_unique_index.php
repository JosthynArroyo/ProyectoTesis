<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('citas_medicas')) {
            return;
        }

        Schema::table('citas_medicas', function (Blueprint $table) {
            try {
                $table->dropUnique('citas_medicas_doctor_fecha_hora_unique');
            } catch (\Throwable $e) {
                // índice no existía o ya estaba eliminado
            }

            $table->unique(
                ['doctor_id', 'fecha', 'hora', 'activo'],
                'citas_medicas_doctor_fecha_hora_activo_unique'
            );
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('citas_medicas')) {
            return;
        }

        Schema::table('citas_medicas', function (Blueprint $table) {
            try {
                $table->dropUnique('citas_medicas_doctor_fecha_hora_activo_unique');
            } catch (\Throwable $e) {
                // índice no existía
            }

            $table->unique(
                ['doctor_id', 'fecha', 'hora'],
                'citas_medicas_doctor_fecha_hora_unique'
            );
        });
    }
};
