<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('pedidos_laboratorio', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cita_id')->nullable();
            $table->unsignedBigInteger('paciente_id');
            $table->unsignedBigInteger('doctor_id');
            $table->json('examenes');
            $table->string('pdf_path')->nullable();
            $table->string('estado')->default('pendiente_toma'); // pendiente_toma, resultado_listo
            $table->string('resultado_path')->nullable();
            $table->text('resultado_resumen')->nullable();
            $table->dateTime('resultado_publicado_at')->nullable();
            $table->dateTime('resultado_enviado_at')->nullable();
            $table->timestamps();

            $table->foreign('cita_id')->references('id')->on('citas_medicas')->onDelete('set null');
            $table->foreign('paciente_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedidos_laboratorio');
    }
};
