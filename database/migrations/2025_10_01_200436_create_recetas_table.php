<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('recetas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('cita_id')->unique(); // 1 receta por cita
            $table->text('diagnostico');
            $table->text('medicamentos');
            $table->text('indicaciones')->nullable();
            $table->string('pdf_path')->nullable(); // storage/app/recetas/...
            $table->timestamp('enviado_en')->nullable();
            $table->timestamps();

            $table->foreign('cita_id')
                ->references('id')->on('citas_medicas')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recetas');
    }
};
