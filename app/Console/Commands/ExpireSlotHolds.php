<?php

namespace App\Console\Commands;

use App\Services\SlotHoldService;
use Illuminate\Console\Command;

class ExpireSlotHolds extends Command
{
    protected $signature = 'citas:expirar-slot-holds';

    protected $description = 'Expira reservas temporales de horarios vencidas.';

    public function handle(SlotHoldService $slotHoldService): int
    {
        $expired = $slotHoldService->expireStaleHolds();
        $this->info("Holds expirados: {$expired}");

        return self::SUCCESS;
    }
}
