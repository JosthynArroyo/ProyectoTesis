<?php

require 'c:/Users/josth/Desktop/proyecto_clinica_JA/vendor/autoload.php';
$app = require_once 'c:/Users/josth/Desktop/proyecto_clinica_JA/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Schema;

echo "=== 1. DISK ROOTS ===" . PHP_EOL;
$localRoot = config('filesystems.disks.local.root');
$publicRoot = config('filesystems.disks.public.root');
$r2PrivateRoot = config('filesystems.disks.r2_private.root');
$r2PublicRoot = config('filesystems.disks.r2_public.root');

echo "local root: " . $localRoot . PHP_EOL;
echo "public root: " . $publicRoot . PHP_EOL;
echo "r2_private root: " . ($r2PrivateRoot ?: 'N/A (S3 Driver)') . PHP_EOL;
echo "r2_public root: " . ($r2PublicRoot ?: 'N/A (S3 Driver)') . PHP_EOL . PHP_EOL;

echo "=== ACTIVE DB LOGICAL KEYS vs PHYSICAL PATHS ===" . PHP_EOL;

// 1. Receta
$receta = DB::table('recetas')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->first();
if ($receta) {
    $logical = $receta->pdf_path;
    $physical = Storage::disk('local')->path($logical);
    $exists = Storage::disk('local')->exists($logical);
    echo "Receta DB ID {$receta->id}:" . PHP_EOL;
    echo "  Clave lógica BD: {$logical}" . PHP_EOL;
    echo "  Disco Laravel: local" . PHP_EOL;
    echo "  Ruta física: {$physical}" . PHP_EOL;
    echo "  Existe local: " . ($exists ? 'SI' : 'NO') . PHP_EOL . PHP_EOL;
}

// 2. Certificado
$cert = DB::table('certificados_medicos')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->first();
if ($cert) {
    $logical = $cert->pdf_path;
    $physical = Storage::disk('local')->path($logical);
    $exists = Storage::disk('local')->exists($logical);
    echo "Certificado DB ID {$cert->id}:" . PHP_EOL;
    echo "  Clave lógica BD: {$logical}" . PHP_EOL;
    echo "  Disco Laravel: local" . PHP_EOL;
    echo "  Ruta física: {$physical}" . PHP_EOL;
    echo "  Existe local: " . ($exists ? 'SI' : 'NO') . PHP_EOL . PHP_EOL;
}

// 3. Pedido Laboratorio
$pedido = DB::table('pedidos_laboratorio')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->first();
if ($pedido) {
    $logical = $pedido->pdf_path;
    $physical = Storage::disk('local')->path($logical);
    $exists = Storage::disk('local')->exists($logical);
    echo "Pedido Laboratorio DB ID {$pedido->id}:" . PHP_EOL;
    echo "  Clave lógica BD: {$logical}" . PHP_EOL;
    echo "  Disco Laravel: local" . PHP_EOL;
    echo "  Ruta física: {$physical}" . PHP_EOL;
    echo "  Existe local: " . ($exists ? 'SI' : 'NO') . PHP_EOL . PHP_EOL;
}

// 4. Payment Receipt
$receipt = DB::table('payment_receipts')->whereNotNull('pdf_path')->where('pdf_path', '!=', '')->first();
if ($receipt) {
    $logical = $receipt->pdf_path;
    $physical = Storage::disk('local')->path($logical);
    $exists = Storage::disk('local')->exists($logical);
    echo "Payment Receipt DB ID {$receipt->id}:" . PHP_EOL;
    echo "  Clave lógica BD: {$logical}" . PHP_EOL;
    echo "  Disco Laravel: local" . PHP_EOL;
    echo "  Ruta física: {$physical}" . PHP_EOL;
    echo "  Existe local: " . ($exists ? 'SI' : 'NO') . PHP_EOL . PHP_EOL;
}

// 5. Cita Comprobante PDF
$cita = DB::table('citas_medicas')->whereNotNull('comprobante_pdf_path')->where('comprobante_pdf_path', '!=', '')->first();
if ($cita) {
    $logical = $cita->comprobante_pdf_path;
    $physical = Storage::disk('local')->path($logical);
    $exists = Storage::disk('local')->exists($logical);
    echo "Cita Comprobante DB ID {$cita->id}:" . PHP_EOL;
    echo "  Clave lógica BD: {$logical}" . PHP_EOL;
    echo "  Disco Laravel: local" . PHP_EOL;
    echo "  Ruta física: {$physical}" . PHP_EOL;
    echo "  Existe local: " . ($exists ? 'SI' : 'NO') . PHP_EOL . PHP_EOL;
}

echo "=== 3. AUDIT OF r2_certificates ===" . PHP_EOL;
$r2CertDirs = [
    'r2_certificates',
    'private/r2_certificates',
    'certificates',
];

foreach ($r2CertDirs as $d) {
    $pathOnDisk = storage_path('app/' . $d);
    echo "Checking {$pathOnDisk}: " . (is_dir($pathOnDisk) ? 'EXISTS' : 'NOT FOUND') . PHP_EOL;
    if (is_dir($pathOnDisk)) {
        $files = glob($pathOnDisk . '/*');
        echo "  File count: " . count($files) . PHP_EOL;
        $totalBytes = 0;
        foreach ($files as $f) {
            if (is_file($f)) {
                $totalBytes += filesize($f);
                echo "  - File basename: " . basename($f) . " (" . filesize($f) . " bytes)" . PHP_EOL;
            }
        }
        echo "  Total size: {$totalBytes} bytes" . PHP_EOL;
    }
}
