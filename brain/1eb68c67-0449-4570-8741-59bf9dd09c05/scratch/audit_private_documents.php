<?php

require 'c:/Users/josth/Desktop/proyecto_clinica_JA/vendor/autoload.php';
$app = require_once 'c:/Users/josth/Desktop/proyecto_clinica_JA/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

echo "=== AUDITORÍA DE BASE DE DATOS Y ALMACENAMIENTO ===" . PHP_EOL . PHP_EOL;

$dbAudit = [];

function checkColumnIfExists(string $table, string $column, array &$dbAudit, string $key = null) {
    if (!Schema::hasTable($table) || !Schema::hasColumn($table, $column)) {
        return;
    }
    $key = $key ?: "{$table}.{$column}";
    $total = DB::table($table)->count();
    $nonNull = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->count();
    $paths = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column);
    
    $missingLocal = 0;
    $formats = [];
    foreach ($paths as $p) {
        $p = (string) $p;
        $fmt = str_starts_with($p, 'r2') ? 'r2' : (str_starts_with($p, '/storage') || str_starts_with($p, 'http') ? 'public/url' : 'local_relative');
        $formats[$fmt] = ($formats[$fmt] ?? 0) + 1;
        
        $existsLocal = Storage::disk('local')->exists($p) || Storage::disk('public')->exists($p);
        if (!$existsLocal && !str_starts_with($p, 'http')) {
            $missingLocal++;
        }
    }

    $dbAudit[$key] = [
        'table' => $table,
        'column' => $column,
        'total' => $total,
        'non_null' => $nonNull,
        'formats' => $formats,
        'missing_local' => $missingLocal,
    ];
}

// Check candidate tables and columns
checkColumnIfExists('recetas', 'pdf_path', $dbAudit);
checkColumnIfExists('certificados_medicos', 'pdf_path', $dbAudit);
checkColumnIfExists('pedidos_laboratorio', 'pdf_path', $dbAudit);
checkColumnIfExists('pedidos_laboratorio', 'resultado_path', $dbAudit);
checkColumnIfExists('pedido_laboratorio_resultados', 'pdf_path', $dbAudit);
checkColumnIfExists('laboratorio_ordenes', 'resultado_path', $dbAudit);
checkColumnIfExists('lab_orders', 'resultado_path', $dbAudit);
checkColumnIfExists('pagos', 'comprobante_path', $dbAudit);
checkColumnIfExists('pagos', 'pdf_path', $dbAudit);
checkColumnIfExists('payment_receipts', 'pdf_path', $dbAudit);
checkColumnIfExists('citas_medicas', 'comprobante_pdf_path', $dbAudit);
checkColumnIfExists('users', 'avatar', $dbAudit);
checkColumnIfExists('dependientes', 'avatar', $dbAudit);

echo json_encode($dbAudit, JSON_PRETTY_PRINT) . PHP_EOL;
