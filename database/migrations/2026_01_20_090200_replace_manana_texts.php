<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $targets = [
            'lab_tests' => ['preparacion_default', 'indicaciones_default', 'restricciones'],
            'lab_order_items' => ['preparacion_snapshot', 'indicaciones_snapshot'],
            'laboratorio_ordenes' => ['preparacion', 'indicaciones', 'resultado_resumen'],
            'lab_orders' => ['doctor_notes'],
            'medical_orders' => ['doctor_notes'],
        ];

        $replacements = [
            ['Manana', 'Mañana'],
            ['manana', 'mañana'],
            ['MANANA', 'MAÑANA'],
        ];

        foreach ($targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                foreach ($replacements as [$from, $to]) {
                    DB::table($table)
                        ->where($column, 'like', "%{$from}%")
                        ->update([
                            $column => DB::raw("REPLACE($column, '{$from}', '{$to}')"),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        $targets = [
            'lab_tests' => ['preparacion_default', 'indicaciones_default', 'restricciones'],
            'lab_order_items' => ['preparacion_snapshot', 'indicaciones_snapshot'],
            'laboratorio_ordenes' => ['preparacion', 'indicaciones', 'resultado_resumen'],
            'lab_orders' => ['doctor_notes'],
            'medical_orders' => ['doctor_notes'],
        ];

        $replacements = [
            ['Mañana', 'Manana'],
            ['mañana', 'manana'],
            ['MAÑANA', 'MANANA'],
        ];

        foreach ($targets as $table => $columns) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    continue;
                }
                foreach ($replacements as [$from, $to]) {
                    DB::table($table)
                        ->where($column, 'like', "%{$from}%")
                        ->update([
                            $column => DB::raw("REPLACE($column, '{$from}', '{$to}')"),
                        ]);
                }
            }
        }
    }
};
