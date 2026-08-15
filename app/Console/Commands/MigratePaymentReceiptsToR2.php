<?php

namespace App\Console\Commands;

class MigratePaymentReceiptsToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'payment-receipts:migrate-to-r2
                            {--dry-run}
                            {--execute}
                            {--verify}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar recibos históricos a R2.';
}
