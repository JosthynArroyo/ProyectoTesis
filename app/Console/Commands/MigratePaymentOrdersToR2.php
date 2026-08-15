<?php

namespace App\Console\Commands;

class MigratePaymentOrdersToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'payment-orders:migrate-to-r2
                            {--dry-run}
                            {--execute}
                            {--verify}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar órdenes de pago históricas a R2.';
}
