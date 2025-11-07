<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCitasMedicasTable extends Migration
{
    public function up()
    {
        Schema::create('citas_medicas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('paciente_id');
            $table->unsignedBigInteger('doctor_id');
            $table->unsignedBigInteger('especialidad_id');
            $table->date('fecha');
            $table->time('hora');
            $table->enum('estado', ['pendiente', 'cancelada', 'reagendada'])->default('pendiente');
            $table->timestamps();

            $table->foreign('paciente_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('especialidad_id')->references('id')->on('especialidades')->onDelete('cascade');
        });
    }

    public function down()
    {
        Schema::dropIfExists('citas_medicas');
    }
}
