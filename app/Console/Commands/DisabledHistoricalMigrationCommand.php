<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

abstract class DisabledHistoricalMigrationCommand extends Command
{
    public function handle(): int
    {
        $this->error(
            'Comando deshabilitado: la política actual prohíbe migrar archivos históricos o legacy a R2.'
        );

        return self::FAILURE;
    }
}
