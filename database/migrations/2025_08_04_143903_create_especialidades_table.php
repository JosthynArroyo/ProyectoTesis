<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CreateEspecialidadesTable extends Migration
{
    public function up()
    {
        Schema::create('especialidades', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();     // ← único opcional
            $table->text('descripcion')->nullable();
            $table->timestamps();
        });

        if (
            DB::getDriverName() !== 'sqlite' &&
            Schema::hasTable('citas_medicas') &&
            Schema::hasColumn('citas_medicas', 'especialidad_id')
        ) {
            Schema::table('citas_medicas', function (Blueprint $table) {
                $table->foreign('especialidad_id', 'citas_medicas_especialidad_id_foreign')
                    ->references('id')
                    ->on('especialidades')
                    ->onDelete('cascade');
            });
        }
    }

    public function down()
    {
        if (
            DB::getDriverName() !== 'sqlite' &&
            Schema::hasTable('citas_medicas') &&
            Schema::hasColumn('citas_medicas', 'especialidad_id')
        ) {
            try {
                Schema::table('citas_medicas', function (Blueprint $table) {
                    $table->dropForeign('citas_medicas_especialidad_id_foreign');
                });
            } catch (\Throwable $e) {
                //
            }
        }

        Schema::dropIfExists('especialidades');
    }
}
