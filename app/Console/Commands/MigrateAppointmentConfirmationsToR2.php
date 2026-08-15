<?php

namespace App\Console\Commands;

class MigrateAppointmentConfirmationsToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'appointment-confirmations:migrate-to-r2 {--dry-run} {--execute} {--verify}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar comprobantes históricos a R2.';
}
