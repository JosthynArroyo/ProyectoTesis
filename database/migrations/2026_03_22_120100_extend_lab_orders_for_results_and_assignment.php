<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            if (! Schema::hasColumn('lab_orders', 'laboratorio_id')) {
                $table->foreignId('laboratorio_id')
                    ->nullable()
                    ->after('medical_order_id')
                    ->constrained('users')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('lab_orders', 'resultado_path')) {
                $table->string('resultado_path')->nullable()->after('doctor_notes');
            }

            if (! Schema::hasColumn('lab_orders', 'resultado_resumen')) {
                $table->text('resultado_resumen')->nullable()->after('resultado_path');
            }

            if (! Schema::hasColumn('lab_orders', 'resultado_publicado_at')) {
                $table->timestamp('resultado_publicado_at')->nullable()->after('resultado_resumen');
            }

            if (! Schema::hasColumn('lab_orders', 'resultado_enviado_at')) {
                $table->timestamp('resultado_enviado_at')->nullable()->after('resultado_publicado_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lab_orders', function (Blueprint $table) {
            foreach ([
                'resultado_enviado_at',
                'resultado_publicado_at',
                'resultado_resumen',
                'resultado_path',
                'laboratorio_id',
            ] as $column) {
                if (! Schema::hasColumn('lab_orders', $column)) {
                    continue;
                }

                if ($column === 'laboratorio_id') {
                    $table->dropConstrainedForeignId('laboratorio_id');
                    continue;
                }

                $table->dropColumn($column);
            }
        });
    }
};
