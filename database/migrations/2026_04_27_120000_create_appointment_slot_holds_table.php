<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_slot_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('doctor_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('paciente_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->time('hora');
            $table->string('session_id', 120)->nullable();
            $table->string('token', 80)->unique();
            $table->string('status', 20)->default('active');
            $table->timestamp('expires_at');
            $table->timestamps();

            $table->index(['doctor_id', 'fecha', 'status', 'expires_at'], 'slot_holds_lookup_idx');
            $table->index(['token', 'status'], 'slot_holds_token_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_slot_holds');
    }
};
