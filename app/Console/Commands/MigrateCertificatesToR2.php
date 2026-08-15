<?php

namespace App\Console\Commands;

class MigrateCertificatesToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'certificates:migrate-to-r2
                            {--dry-run}
                            {--execute}
                            {--verify}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar certificados históricos a R2.';
}
