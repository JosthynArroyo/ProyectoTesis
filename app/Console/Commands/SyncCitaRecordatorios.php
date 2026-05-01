<?php

namespace App\Console\Commands;

use App\Services\CitaRecordatorioService;
use Illuminate\Console\Command;

class SyncCitaRecordatorios extends Command
{
    protected $signature = 'citas:sync-recordatorios {--chunk=200 : Numero de citas por lote}';

    protected $description = 'Sincroniza recordatorios pendientes fuera del flujo de renderizado.';

    public function handle(CitaRecordatorioService $recordatorios): int
    {
        $chunk = max(50, (int) $this->option('chunk'));
        $sincronizados = $recordatorios->syncTrackedAppointments($chunk);

        $this->info('Recordatorios sincronizados: '.$sincronizados);

        return self::SUCCESS;
    }
}
