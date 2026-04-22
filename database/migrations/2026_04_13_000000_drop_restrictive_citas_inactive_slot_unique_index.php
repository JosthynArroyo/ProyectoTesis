<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('citas_medicas')) {
            return;
        }

        try {
            Schema::table('citas_medicas', function (Blueprint $table): void {
                $table->dropUnique('citas_medicas_doctor_fecha_hora_activo_unique');
            });
        } catch (\Throwable $e) {
            //
        }
    }

    public function down(): void
    {
        //
    }
};
