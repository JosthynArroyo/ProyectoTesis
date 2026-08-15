<?php

namespace App\Console\Commands;

class MigrateRecipesToR2 extends DisabledHistoricalMigrationCommand
{
    protected $signature = 'recipes:migrate-to-r2
                            {--dry-run}
                            {--execute}
                            {--verify}';

    protected $description = 'DESHABILITADO: la política vigente no permite migrar recetas históricas a R2.';
}
