<?php
define('LARAVEL_START', microtime(true));
require __DIR__ . '/vendor/autoload.php';
$app = require __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Especialidad;
use App\Models\SiteSetting;
use Illuminate\Support\Facades\Storage;

echo "=== SPECIALTY DB & R2 CHECK ===" . PHP_EOL;

$especialidades = Especialidad::all();
foreach ($especialidades as $esp) {
    $settingKey = "services.specialty_image.{$esp->id}";
    $settingVal = SiteSetting::where('key', $settingKey)->value('value');
    echo "ID: {$esp->id} | Nombre: {$esp->nombre}" . PHP_EOL;
    echo "  - DB setting ({$settingKey}): " . json_encode($settingVal) . PHP_EOL;
    
    if ($settingVal) {
        $thumbKey = str_replace(['/medium/', '/large/', '/original/'], '/thumb/', $settingVal);
        $mediumKey = str_replace(['/thumb/', '/large/', '/original/'], '/medium/', $settingVal);
        echo "  - R2 thumb ({$thumbKey}): " . (Storage::disk('r2_public')->exists($thumbKey) ? 'EXISTS' : 'MISSING') . PHP_EOL;
        echo "  - R2 medium ({$mediumKey}): " . (Storage::disk('r2_public')->exists($mediumKey) ? 'EXISTS' : 'MISSING') . PHP_EOL;
    }
}

echo PHP_EOL . "=== R2 ALL FILES IN images/services ===" . PHP_EOL;
try {
    $allFiles = Storage::disk('r2_public')->allFiles('images/services');
    echo "Total files: " . count($allFiles) . PHP_EOL;
    foreach ($allFiles as $f) {
        echo "  $f" . PHP_EOL;
    }
} catch (\Throwable $e) {
    echo "R2 listing error: " . $e->getMessage() . PHP_EOL;
}
