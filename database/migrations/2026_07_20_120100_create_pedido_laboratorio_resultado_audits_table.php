<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pedido_laboratorio_resultado_audits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pedido_laboratorio_id')->constrained('pedidos_laboratorio', indexName: 'pl_res_audit_pedido_fk')->cascadeOnDelete();
            $table->foreignId('pedido_laboratorio_resultado_id')->nullable()->constrained('pedido_laboratorio_resultados', indexName: 'pl_res_audit_resultado_fk')->nullOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users', indexName: 'pl_res_audit_user_fk')->nullOnDelete();
            $table->string('accion', 50);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['pedido_laboratorio_id', 'accion'], 'pl_res_audit_pedido_accion_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pedido_laboratorio_resultado_audits');
    }
};
