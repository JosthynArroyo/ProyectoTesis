<?php

require 'c:/Users/josth/Desktop/proyecto_clinica_JA/vendor/autoload.php';
$app = require_once 'c:/Users/josth/Desktop/proyecto_clinica_JA/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use Illuminate\Support\Facades\Storage;

echo "=== AUDITORÍA DE STORAGE PUBLIC ===" . PHP_EOL . PHP_EOL;

$publicFiles = Storage::disk('public')->allFiles();
$dirs = [];
foreach ($publicFiles as $file) {
    $dir = dirname($file);
    $dirs[$dir] = ($dirs[$dir] ?? 0) + 1;
}

echo json_encode($dirs, JSON_PRETTY_PRINT) . PHP_EOL;
