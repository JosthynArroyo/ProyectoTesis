<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('facturas', function (Blueprint $table) {
            $table->id();

            // Relación con la cita (opcional pero recomendado para trazabilidad)
            $table->foreignId('cita_id')
                ->nullable()
                ->constrained('citas_medicas')
                ->nullOnDelete();

            // Dueño de la factura (paciente) y el doctor que atendió
            $table->foreignId('paciente_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('doctor_id')
                ->constrained('users')
                ->cascadeOnDelete();

            // Datos económicos
            $table->string('moneda', 3)->default('USD');                 // ISO 4217: USD, EUR, etc.
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('impuestos', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);

            // Estado de la factura
            // valores esperados: borrador | emitida | anulada
            $table->enum('estado', ['borrador', 'emitida', 'anulada'])->default('borrador');

            // Archivo generado (PDF o .txt inicialmente)
            $table->string('pdf_path')->nullable();

            // Marcas de tiempo clave
            $table->timestamp('emitida_en')->nullable(); // cuando pasa a emitida

            $table->timestamps();

            // Índices útiles
            $table->index(['paciente_id', 'doctor_id']);
            $table->index(['estado']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas');
    }
};
