<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_soap_diagnosticos', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nota_soap_id');
            $table->enum('tipo', ['principal', 'secundario', 'diferencial'])->default('principal');
            $table->string('texto', 255);
            $table->string('cie10', 20)->nullable();
            $table->timestamps();

            $table->foreign('nota_soap_id')->references('id')->on('notas_soap')->cascadeOnDelete();
            $table->index(['nota_soap_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_soap_diagnosticos');
    }
};
