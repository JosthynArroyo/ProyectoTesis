<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('nota_soap_enmiendas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('nota_soap_id');
            $table->unsignedBigInteger('user_id')->nullable();
            $table->text('motivo');
            $table->text('contenido');
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->foreign('nota_soap_id')->references('id')->on('notas_soap')->cascadeOnDelete();
            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $table->index('nota_soap_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('nota_soap_enmiendas');
    }
};
