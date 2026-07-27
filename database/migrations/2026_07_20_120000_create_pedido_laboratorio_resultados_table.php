<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_laboratorio_resultados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_laboratorio_id')->constrained('pedidos_laboratorio', indexName: 'pl_res_pedido_fk')->cascadeOnDelete();
            $table->unsignedInteger('version')->default(1);
            $table->enum('estado', ['borrador', 'publicado', 'reemplazado', 'anulado'])->default('borrador');
            $table->json('resultado_items')->nullable();
            $table->text('observaciones_generales')->nullable();
            $table->foreignId('laboratorio_id')->nullable()->constrained('users', indexName: 'pl_res_laboratorio_fk')->nullOnDelete();
            $table->string('csv', 32)->nullable()->unique();
            $table->string('pdf_path')->nullable();
            $table->timestamp('publicado_at')->nullable();
            $table->string('enviado_a')->nullable();
            $table->timestamp('enviado_en')->nullable();
            $table->string('envio_estado', 30)->default('pendiente');
            $table->text('envio_error')->nullable();
            $table->unsignedSmallInteger('envio_intentos')->default(0);
            $table->foreignId('reemplaza_id')->nullable()->constrained('pedido_laboratorio_resultados', indexName: 'pl_res_reemplaza_fk')->nullOnDelete();
            $table->timestamps();

            $table->unique(['pedido_laboratorio_id', 'version'], 'pl_res_pedido_version_unique');
            $table->index(['pedido_laboratorio_id', 'estado'], 'pl_res_pedido_estado_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_laboratorio_resultados');
    }
};
