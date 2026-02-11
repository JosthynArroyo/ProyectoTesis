<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('lab_orders', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_id');
            $table->enum('source', ['MEDICAL_ORDER', 'ROUTINE']);
            $table->unsignedBigInteger('doctor_id')->nullable();
            $table->unsignedBigInteger('medical_order_id')->nullable();
            $table->enum('priority', ['normal', 'urgente'])->default('normal');
            $table->enum('status', [
                'pendiente_toma',
                'muestra_tomada',
                'en_analisis',
                'resultado_listo',
                'cancelado',
                'no_se_presento',
            ])->default('pendiente_toma');
            $table->timestamp('scheduled_at')->nullable();
            $table->text('doctor_notes')->nullable();
            $table->timestamps();

            $table->foreign('patient_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('doctor_id')->references('id')->on('users')->nullOnDelete();
            $table->foreign('medical_order_id')->references('id')->on('medical_orders')->nullOnDelete();
            $table->index(['patient_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_orders');
    }
};
