<?php

namespace App\Console\Commands;

class MigrateLaboratoryOrdersToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'laboratory-orders:migrate-to-r2
                            {--dry-run}
                            {--execute}
                            {--verify}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar órdenes históricas a R2.';
}
