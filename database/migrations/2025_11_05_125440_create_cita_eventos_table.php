<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cita_eventos', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cita_id');
            $t->unsignedBigInteger('user_id')->nullable(); // actor, opcional
            $t->string('tipo', 30); // agendada|confirmada|cancelada|realizada|reprogramada
            $t->string('de_estado', 20)->nullable();
            $t->string('a_estado', 20)->nullable();
            $t->date('de_fecha')->nullable();
            $t->date('a_fecha')->nullable();
            $t->string('de_hora', 10)->nullable();
            $t->string('a_hora', 10)->nullable();
            $t->timestamps();

            $t->foreign('cita_id')->references('id')->on('citas_medicas')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cita_eventos');
    }
};
