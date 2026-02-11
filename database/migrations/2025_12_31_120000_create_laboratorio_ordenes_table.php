<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('laboratorio_ordenes', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cita_id');
            $table->unsignedBigInteger('solicitante_id')->nullable();
            $table->enum('origen', ['doctor', 'paciente'])->default('paciente');
            $table->enum('prioridad', ['normal', 'urgente'])->default('normal');
            $table->string('tipo_examen', 255);
            $table->text('indicaciones')->nullable();
            $table->text('preparacion')->nullable();
            $table->enum('estado', [
                'orden_creada',
                'cita_programada',
                'muestra_tomada',
                'resultado_disponible',
            ])->default('orden_creada');
            $table->string('resultado_path')->nullable();
            $table->text('resultado_resumen')->nullable();
            $table->timestamp('resultado_publicado_at')->nullable();
            $table->timestamp('resultado_enviado_at')->nullable();
            $table->timestamps();

            $table->foreign('cita_id')->references('id')->on('citas_medicas')->onDelete('cascade');
            $table->foreign('solicitante_id')->references('id')->on('users')->nullOnDelete();
            $table->unique('cita_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('laboratorio_ordenes');
    }
};
