<?php

namespace App\Console\Commands;

class MigrateLabResultsToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'laboratory-results:migrate-to-r2
                            {--dry-run}
                            {--execute}
                            {--verify}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar resultados históricos a R2.';
}
