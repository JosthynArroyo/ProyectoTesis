<?php

require 'c:/Users/josth/Desktop/proyecto_clinica_JA/vendor/autoload.php';
$app = require_once 'c:/Users/josth/Desktop/proyecto_clinica_JA/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

echo "=== VERIFICACIÓN DE CONTEOS ACTIVOS vs HUÉRFANOS EN DISCO ===" . PHP_EOL;

function auditDirectory(string $dir, array $dbPaths) {
    if (!Storage::disk('local')->exists($dir)) {
        return ['active' => 0, 'orphans' => 0, 'total_files' => 0];
    }
    $allFiles = Storage::disk('local')->allFiles($dir);
    $dbPathsSet = collect($dbPaths)->filter()->flip();
    $active = 0;
    $orphans = 0;
    foreach ($allFiles as $f) {
        if ($dbPathsSet->has($f)) {
            $active++;
        } else {
            $orphans++;
        }
    }
    return ['active' => $active, 'orphans' => $orphans, 'total_files' => count($allFiles)];
}

// 1. Recetas
$recetasPaths = DB::table('recetas')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->pluck('pdf_path')->toArray();
$rAudit = auditDirectory('recetas', $recetasPaths);
echo "Recetas: Activos BD: " . count($recetasPaths) . " | En Disco Activos: {$rAudit['active']} | Huérfanos: {$rAudit['orphans']} | Total Disco: {$rAudit['total_files']}" . PHP_EOL;

// 2. Certificados
$certPaths = DB::table('certificados_medicos')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->pluck('pdf_path')->toArray();
$cAudit = auditDirectory('certificados-medicos', $certPaths);
echo "Certificados: Activos BD: " . count($certPaths) . " | En Disco Activos: {$cAudit['active']} | Huérfanos: {$cAudit['orphans']} | Total Disco: {$cAudit['total_files']}" . PHP_EOL;

// 3. Pedidos Laboratorio
$pedidosPaths = DB::table('pedidos_laboratorio')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->pluck('pdf_path')->toArray();
$pAudit = auditDirectory('pedidos-laboratorio', $pedidosPaths);
echo "Pedidos Lab: Activos BD: " . count($pedidosPaths) . " | En Disco Activos: {$pAudit['active']} | Huérfanos: {$pAudit['orphans']} | Total Disco: {$pAudit['total_files']}" . PHP_EOL;

// 4. Resultados Laboratorio
$resPaths = DB::table('pedido_laboratorio_resultados')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->pluck('pdf_path')->toArray();
$resAudit = auditDirectory('pedidos-laboratorio-resultados', $resPaths);
echo "Resultados Lab: Activos BD: " . count($resPaths) . " | En Disco Activos: {$resAudit['active']} | Huérfanos: {$resAudit['orphans']} | Total Disco: {$resAudit['total_files']}" . PHP_EOL;

// 5. Recibos de Pago
$receiptPaths = DB::table('payment_receipts')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->pluck('pdf_path')->toArray();
$recAudit = auditDirectory('pagos/recibos', $receiptPaths);
echo "Recibos Pago: Activos BD: " . count($receiptPaths) . " | En Disco Activos: {$recAudit['active']} | Huérfanos: {$recAudit['orphans']} | Total Disco: {$recAudit['total_files']}" . PHP_EOL;

// 6. Órdenes de Cobro
$ordenesAudit = auditDirectory('pagos/ordenes', []);
echo "Órdenes Cobro: En Disco Total: {$ordenesAudit['total_files']} (Archivos PDF de órdenes)" . PHP_EOL;

// 7. Comprobante Cita
$citasPaths = DB::table('citas_medicas')->whereNotNull('comprobante_pdf_path')->where('comprobante_pdf_path', '!=', '')->pluck('comprobante_pdf_path')->toArray();
$ccAudit = auditDirectory('citas/comprobantes', $citasPaths);
echo "Comprobantes Cita: Activos BD: " . count($citasPaths) . " | En Disco Activos: {$ccAudit['active']} | Huérfanos: {$ccAudit['orphans']} | Total Disco: {$ccAudit['total_files']}" . PHP_EOL;
