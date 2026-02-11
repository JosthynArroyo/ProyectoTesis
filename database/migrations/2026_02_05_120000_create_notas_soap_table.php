<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notas_soap', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cita_id')->unique();
            $table->enum('estado', ['draft', 'signed'])->default('draft');
            $table->timestamp('signed_at')->nullable();
            $table->unsignedBigInteger('signed_by')->nullable();

            // S - Subjetivo
            $table->text('subjetivo_motivo')->nullable();
            $table->text('subjetivo_hpi')->nullable();
            $table->json('subjetivo_ros')->nullable();
            $table->text('subjetivo_notas')->nullable();

            // O - Objetivo
            $table->json('signos_vitales')->nullable();
            $table->text('examen_fisico')->nullable();
            $table->text('notas_objetivas')->nullable();

            // A - Assessment
            $table->text('assessment')->nullable();

            // P - Plan
            $table->text('plan_general')->nullable();
            $table->text('plan_seguimiento')->nullable();
            $table->text('plan_notas')->nullable();

            $table->timestamps();

            $table->foreign('cita_id')->references('id')->on('citas_medicas')->cascadeOnDelete();
            $table->foreign('signed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notas_soap');
    }
};
