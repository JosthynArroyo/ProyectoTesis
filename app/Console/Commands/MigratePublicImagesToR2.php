<?php

namespace App\Console\Commands;

class MigratePublicImagesToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'app:migrate-public-images-to-r2
                            {--execute}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar imágenes públicas históricas a R2.';
}
