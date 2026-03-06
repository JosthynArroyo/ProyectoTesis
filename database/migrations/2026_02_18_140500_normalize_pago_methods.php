<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('pagos')) {
            return;
        }

        $allowed = ['efectivo', 'transferencia'];

        DB::table('pagos')
            ->select(['id', 'metodo_pago'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($allowed): void {
                foreach ($rows as $row) {
                    $current = $row->metodo_pago;
                    $normalized = $current !== null ? strtolower(trim((string) $current)) : null;
                    $value = in_array($normalized, $allowed, true) ? $normalized : null;

                    if ($value === $current) {
                        continue;
                    }

                    DB::table('pagos')
                        ->where('id', $row->id)
                        ->update(['metodo_pago' => $value]);
                }
            });
    }

    public function down(): void
    {
        // no-op: normalized values cannot be restored safely.
    }
};
