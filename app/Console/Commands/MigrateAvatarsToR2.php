<?php

namespace App\Console\Commands;

class MigrateAvatarsToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'avatars:migrate-to-r2 {--dry-run} {--execute} {--verify}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar avatares históricos a R2.';
}
