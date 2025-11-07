<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('doctor_especialidad', function (Blueprint $table) {
            $table->id();

            // Usamos users porque tus doctores son usuarios con rol "doctor"
            $table->foreignId('user_id')
                  ->constrained('users')
                  ->cascadeOnDelete();

            $table->foreignId('especialidad_id')
                  ->constrained('especialidades')
                  ->cascadeOnDelete();

            $table->unique(['user_id', 'especialidad_id']);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_especialidad');
    }
};
