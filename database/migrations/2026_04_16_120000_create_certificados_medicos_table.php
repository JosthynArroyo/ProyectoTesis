<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('certificados_medicos', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 40)->unique();
            $table->foreignId('cita_id')->unique()->constrained('citas_medicas')->cascadeOnDelete();
            $table->foreignId('paciente_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('clinical_record_id')->nullable()->constrained('clinical_records')->nullOnDelete();
            $table->timestamp('fecha_emision');
            $table->text('texto_constancia');
            $table->unsignedSmallInteger('dias_reposo')->default(0);
            $table->date('reposo_desde')->nullable();
            $table->date('reposo_hasta')->nullable();
            $table->text('observaciones')->nullable();
            $table->string('pdf_path')->nullable();
            $table->timestamps();

            $table->index(['paciente_id', 'fecha_emision']);
            $table->index(['doctor_id', 'fecha_emision']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('certificados_medicos');
    }
};
