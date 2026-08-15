<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = 'certificados_medicos';

        // Read current indexes dynamically
        $existingIndexes = collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name')->toArray();

        // 1. Add normal index on cita_id FIRST so the foreign key constraint has an index
        if (! in_array('certificados_medicos_cita_id_index', $existingIndexes, true)) {
            Schema::table($table, function (Blueprint $t) {
                $t->index('cita_id', 'certificados_medicos_cita_id_index');
            });
        }

        // Re-read indexes after adding cita_id_index
        $existingIndexes = collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name')->toArray();

        // 2. Drop unique index on cita_id
        if (in_array('certificados_medicos_cita_id_unique', $existingIndexes, true)) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropUnique('certificados_medicos_cita_id_unique');
            });
        }

        // 3. Add versioning columns
        Schema::table($table, function (Blueprint $t) use ($table) {
            if (! Schema::hasColumn($table, 'estado_version')) {
                $t->string('estado_version', 20)->default('vigente');
            }
            if (! Schema::hasColumn($table, 'version')) {
                $t->unsignedInteger('version')->default(1);
            }
            if (! Schema::hasColumn($table, 'reemplaza_a_id')) {
                $t->foreignId('reemplaza_a_id')->nullable()->constrained('certificados_medicos')->nullOnDelete();
            }
            if (! Schema::hasColumn($table, 'reemplazado_por_id')) {
                $t->foreignId('reemplazado_por_id')->nullable()->constrained('certificados_medicos')->nullOnDelete();
            }
            if (! Schema::hasColumn($table, 'motivo_correccion')) {
                $t->text('motivo_correccion')->nullable();
            }
            if (! Schema::hasColumn($table, 'corregido_por')) {
                $t->foreignId('corregido_por')->nullable()->constrained('users')->nullOnDelete();
            }
            if (! Schema::hasColumn($table, 'fecha_correccion')) {
                $t->timestamp('fecha_correccion')->nullable();
            }
        });

        // 4. Add composite index for cita_id + estado_version
        $existingIndexes = collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name')->toArray();
        if (! in_array('certificados_medicos_cita_estado_index', $existingIndexes, true)) {
            Schema::table($table, function (Blueprint $t) {
                $t->index(['cita_id', 'estado_version'], 'certificados_medicos_cita_estado_index');
            });
        }
    }

    public function down(): void
    {
        $table = 'certificados_medicos';

        Schema::table($table, function (Blueprint $t) use ($table) {
            if (Schema::hasColumn($table, 'reemplaza_a_id')) {
                $t->dropForeign(['reemplaza_a_id']);
            }
            if (Schema::hasColumn($table, 'reemplazado_por_id')) {
                $t->dropForeign(['reemplazado_por_id']);
            }
            if (Schema::hasColumn($table, 'corregido_por')) {
                $t->dropForeign(['corregido_por']);
            }
            $t->dropColumn([
                'estado_version',
                'version',
                'reemplaza_a_id',
                'reemplazado_por_id',
                'motivo_correccion',
                'corregido_por',
                'fecha_correccion',
            ]);
        });

        $existingIndexes = collect(DB::select("SHOW INDEX FROM {$table}"))->pluck('Key_name')->toArray();

        if (in_array('certificados_medicos_cita_estado_index', $existingIndexes, true)) {
            Schema::table($table, function (Blueprint $t) {
                $t->dropIndex('certificados_medicos_cita_estado_index');
            });
        }

        if (! in_array('certificados_medicos_cita_id_unique', $existingIndexes, true)) {
            try {
                Schema::table($table, function (Blueprint $t) {
                    $t->unique('cita_id', 'certificados_medicos_cita_id_unique');
                });
            } catch (\Throwable) {
                // Ignore
            }
        }

        if (in_array('certificados_medicos_cita_id_index', $existingIndexes, true)) {
            try {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropIndex('certificados_medicos_cita_id_index');
                });
            } catch (\Throwable) {
                // Ignore
            }
        }
    }
};
