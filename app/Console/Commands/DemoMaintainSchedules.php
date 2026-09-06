<?php

namespace App\Console\Commands;

use App\Services\ApplicationModeService;
use Database\Seeders\DemoLaboratorySeeder;
use Database\Seeders\DemoUsersSeeder;
use Illuminate\Console\Command;

class DemoMaintainSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'demo:maintain-schedules';

    /**
     * The console command aliases.
     *
     * @var array<int, string>
     */
    protected $aliases = ['demo:maintain-laboratory-schedule'];

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mantiene la ventana rodante de disponibilidad médica y de laboratorio en modo demo';

    /**
     * Execute the console command.
     */
    public function handle(ApplicationModeService $applicationMode): int
    {
        if (! $applicationMode->isDemo()) {
            $this->comment('Comando exclusivo para APP_MODE=demo. Operación omitida en producción.');

            return self::SUCCESS;
        }

        // 1. Mantenimiento del horario de laboratorio
        $labSeeder = new DemoLaboratorySeeder;
        $labSeeder->run();

        // 2. Mantenimiento del horario de doctores
        DemoUsersSeeder::seedDoctorSchedules();

        $this->info('Ventana de disponibilidad del laboratorio actualizada.');
        $this->info('Ventana de disponibilidad médica actualizada.');

        return self::SUCCESS;
    }
}

