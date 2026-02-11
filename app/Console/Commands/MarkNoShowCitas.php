<?php

namespace App\Console\Commands;

use App\Services\CitaNoShowService;
use Illuminate\Console\Command;

class MarkNoShowCitas extends Command
{
    protected $signature = 'citas:marcar-no-show';

    protected $description = 'Marca citas vencidas como no se presento y notifica por correo.';

    public function handle(CitaNoShowService $service): int
    {
        $citas = $service->marcarVencidas();
        $this->info("Citas actualizadas: {$citas->count()}");

        return self::SUCCESS;
    }
}
