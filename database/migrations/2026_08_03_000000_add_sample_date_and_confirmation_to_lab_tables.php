<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar fecha real de toma de muestra y procesamiento a pedidos_laboratorio
        Schema::table('pedidos_laboratorio', function (Blueprint $table) {
            if (!Schema::hasColumn('pedidos_laboratorio', 'sample_collected_at')) {
                $table->timestamp('sample_collected_at')->nullable()->after('estado');
            }
            if (!Schema::hasColumn('pedidos_laboratorio', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('sample_collected_at');
            }
        });

        // 2. Agregar origen de cálculo de edad y confirmación por informe en laboratory_result_values
        Schema::table('laboratory_result_values', function (Blueprint $table) {
            if (!Schema::hasColumn('laboratory_result_values', 'age_calculation_source')) {
                $table->string('age_calculation_source')->default('order_created_fallback')->after('age_calculation_date_snapshot');
            }
            if (!Schema::hasColumn('laboratory_result_values', 'reference_confirmed_for_result')) {
                $table->boolean('reference_confirmed_for_result')->default(false)->after('override_reason');
            }
            if (!Schema::hasColumn('laboratory_result_values', 'reference_confirmed_by')) {
                $table->unsignedBigInteger('reference_confirmed_by')->nullable()->after('reference_confirmed_for_result');
            }
            if (!Schema::hasColumn('laboratory_result_values', 'reference_confirmed_at')) {
                $table->timestamp('reference_confirmed_at')->nullable()->after('reference_confirmed_by');
            }
        });
    }

    public function down(): void
    {
        Schema::table('laboratory_result_values', function (Blueprint $table) {
            $table->dropColumn(['age_calculation_source', 'reference_confirmed_for_result', 'reference_confirmed_by', 'reference_confirmed_at']);
        });

        Schema::table('pedidos_laboratorio', function (Blueprint $table) {
            $table->dropColumn(['sample_collected_at', 'processed_at']);
        });
    }
};
