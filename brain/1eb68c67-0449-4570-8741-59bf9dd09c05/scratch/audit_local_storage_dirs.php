<?php

require 'c:/Users/josth/Desktop/proyecto_clinica_JA/vendor/autoload.php';
$app = require_once 'c:/Users/josth/Desktop/proyecto_clinica_JA/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

echo "=== AUDITORÍA DE ARCHIVOS EN DISCO LOCAL ===" . PHP_EOL . PHP_EOL;

$dirs = [
    'certificados-medicos',
    'citas',
    'pedidos-laboratorio',
    'pedidos-laboratorio-resultados',
    'recetas',
    'pagos',
    'comprobantes',
    'captcha',
    'avatars',
];

$allKnownDbPaths = collect();

// Collect all DB paths
if (\Illuminate\Support\Facades\Schema::hasTable('recetas')) {
    $allKnownDbPaths = $allKnownDbPaths->merge(DB::table('recetas')->whereNotNull('pdf_path')->pluck('pdf_path'));
}
if (\Illuminate\Support\Facades\Schema::hasTable('certificados_medicos')) {
    $allKnownDbPaths = $allKnownDbPaths->merge(DB::table('certificados_medicos')->whereNotNull('pdf_path')->pluck('pdf_path'));
}
if (\Illuminate\Support\Facades\Schema::hasTable('pedidos_laboratorio')) {
    $allKnownDbPaths = $allKnownDbPaths->merge(DB::table('pedidos_laboratorio')->whereNotNull('pdf_path')->pluck('pdf_path'));
    $allKnownDbPaths = $allKnownDbPaths->merge(DB::table('pedidos_laboratorio')->whereNotNull('resultado_path')->pluck('resultado_path'));
}
if (\Illuminate\Support\Facades\Schema::hasTable('pedido_laboratorio_resultados')) {
    $allKnownDbPaths = $allKnownDbPaths->merge(DB::table('pedido_laboratorio_resultados')->whereNotNull('pdf_path')->pluck('pdf_path'));
}
if (\Illuminate\Support\Facades\Schema::hasTable('payment_receipts')) {
    $allKnownDbPaths = $allKnownDbPaths->merge(DB::table('payment_receipts')->whereNotNull('pdf_path')->pluck('pdf_path'));
}
if (\Illuminate\Support\Facades\Schema::hasTable('pagos')) {
    $allKnownDbPaths = $allKnownDbPaths->merge(DB::table('pagos')->whereNotNull('comprobante_path')->pluck('comprobante_path'));
}
if (\Illuminate\Support\Facades\Schema::hasTable('citas_medicas')) {
    $allKnownDbPaths = $allKnownDbPaths->merge(DB::table('citas_medicas')->whereNotNull('comprobante_pdf_path')->pluck('comprobante_pdf_path'));
}

$allKnownDbPathsSet = $allKnownDbPaths->filter()->flip();

$storageAudit = [];

foreach ($dirs as $dir) {
    if (!Storage::disk('local')->exists($dir)) {
        $storageAudit[$dir] = [
            'exists' => false,
            'file_count' => 0,
            'total_bytes' => 0,
            'extensions' => [],
            'orphans' => 0,
        ];
        continue;
    }

    $allFiles = Storage::disk('local')->allFiles($dir);
    $totalSize = 0;
    $exts = [];
    $orphans = 0;
    $associated = 0;
    $oldest = null;
    $newest = null;

    foreach ($allFiles as $file) {
        $size = Storage::disk('local')->size($file);
        $time = Storage::disk('local')->lastModified($file);
        $totalSize += $size;

        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        $exts[$ext] = ($exts[$ext] ?? 0) + 1;

        if ($oldest === null || $time < $oldest) {
            $oldest = $time;
        }
        if ($newest === null || $time > $newest) {
            $newest = $time;
        }

        if ($allKnownDbPathsSet->has($file)) {
            $associated++;
        } else {
            $orphans++;
        }
    }

    $storageAudit[$dir] = [
        'exists' => true,
        'file_count' => count($allFiles),
        'total_bytes' => $totalSize,
        'extensions' => $exts,
        'associated_to_db' => $associated,
        'orphans' => $orphans,
        'oldest_date' => $oldest ? date('Y-m-d H:i:s', $oldest) : null,
        'newest_date' => $newest ? date('Y-m-d H:i:s', $newest) : null,
    ];
}

echo json_encode($storageAudit, JSON_PRETTY_PRINT) . PHP_EOL;
