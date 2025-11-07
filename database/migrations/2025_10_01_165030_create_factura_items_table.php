<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('factura_items', function (Blueprint $table) {
            $table->id();

            $table->foreignId('factura_id')
                ->constrained('facturas')
                ->cascadeOnDelete();

            $table->string('descripcion');               // p.ej. "Consulta médica general"
            $table->unsignedInteger('cantidad')->default(1);
            $table->decimal('precio_unitario', 10, 2);
            $table->decimal('total_linea', 10, 2);       // cantidad * precio_unitario (guardado por conveniencia)

            $table->timestamps();

            $table->index(['factura_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('factura_items');
    }
};
