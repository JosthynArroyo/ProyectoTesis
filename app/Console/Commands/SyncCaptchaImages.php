<?php

namespace App\Console\Commands;

use App\Services\CaptchaImageSynchronizer;
use Illuminate\Console\Command;

class SyncCaptchaImages extends Command
{
    protected $signature = 'captcha:sync';
    protected $description = 'Sincroniza e importa las imágenes de CAPTCHA privadas de la clínica a la base de datos';

    public function handle(CaptchaImageSynchronizer $synchronizer): int
    {
        $this->info('Sincronizando imágenes de CAPTCHA...');

        try {
            $total = $synchronizer->ensureSynchronized(function (string $msg, string $type) {
                if ($type === 'error') {
                    $this->error($msg);
                } elseif ($type === 'warning') {
                    $this->warn($msg);
                } else {
                    $this->line($msg);
                }
            });

            $this->info("¡Completado! Total de imágenes registradas: {$total}");
            return 0;
        } catch (\Throwable $e) {
            $this->error("Error al sincronizar: {$e->getMessage()}");
            return 1;
        }
    }
}
