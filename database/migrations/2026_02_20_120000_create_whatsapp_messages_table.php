<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('whatsapp_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cita_id');
            $t->unsignedBigInteger('user_id')->nullable();
            $t->string('rol_receptor', 20);
            $t->string('evento', 30);
            $t->string('telefono', 30)->nullable();
            $t->text('mensaje');
            $t->string('estado', 20)->default('pendiente');
            $t->string('provider', 30)->default('twilio');
            $t->string('provider_message_id', 100)->nullable();
            $t->string('error', 500)->nullable();
            $t->json('payload')->nullable();
            $t->timestamp('enviado_at')->nullable();
            $t->timestamps();

            $t->foreign('cita_id')->references('id')->on('citas_medicas')->cascadeOnDelete();
            $t->foreign('user_id')->references('id')->on('users')->nullOnDelete();
            $t->unique(['cita_id', 'evento', 'rol_receptor']);
            $t->index(['evento', 'estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_messages');
    }
};
