<?php

namespace Database\Seeders;

use App\Services\ApplicationModeService;
use App\Services\DemoExternalEffectsGuard;
use Illuminate\Database\Seeder;
use RuntimeException;

class DemoSeeder extends Seeder
{
    /**
     * Run the demo dataset seeds.
     *
     * @throws RuntimeException if executed when APP_MODE is not 'demo'.
     */
    public function run(): void
    {
        $applicationMode = app(ApplicationModeService::class);

        if (! $applicationMode->isDemo()) {
            throw new RuntimeException(
                'DemoSeeder solo puede ejecutarse cuando APP_MODE=demo. Modo actual: '.$applicationMode->getMode()
            );
        }

        app(DemoExternalEffectsGuard::class)->apply();

        // 1. Asegurar catálogo base y roles estructurales
        $this->call(ProductionSeeder::class);

        // 2. Poblar dominios con dataset de demostración coherente
        $this->call([
            DemoClinicSeeder::class,
            DemoUsersSeeder::class,
            DemoAppointmentsSeeder::class,
            DemoClinicalHistorySeeder::class,
            DemoLaboratorySeeder::class,
            DemoPaymentsSeeder::class,
            DemoPersonalizacionSolicitudesSeeder::class,
            DemoDatabaseBackupSeeder::class,
            DemoContactMessagesSeeder::class,
        ]);
    }
}
