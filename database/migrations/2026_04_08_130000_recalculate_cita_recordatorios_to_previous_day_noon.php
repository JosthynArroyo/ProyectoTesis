<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('cita_recordatorios') || ! Schema::hasTable('citas_medicas')) {
            return;
        }

        $timezone = config('app.timezone', 'America/Guayaquil');

        DB::table('cita_recordatorios')
            ->select(['id', 'cita_id'])
            ->orderBy('id')
            ->chunkById(200, function ($rows) use ($timezone): void {
                $citaIds = collect($rows)->pluck('cita_id')->all();
                $citas = DB::table('citas_medicas')
                    ->select(['id', 'fecha', 'hora'])
                    ->whereIn('id', $citaIds)
                    ->get()
                    ->keyBy('id');

                foreach ($rows as $row) {
                    $cita = $citas->get($row->cita_id);
                    if (! $cita || ! $cita->fecha || ! $cita->hora) {
                        continue;
                    }

                    $inicio = Carbon::parse(
                        sprintf('%s %s', $cita->fecha, substr((string) $cita->hora, 0, 5)),
                        $timezone
                    );

                    DB::table('cita_recordatorios')
                        ->where('id', $row->id)
                        ->update([
                            'cita_inicio_at' => $inicio->toDateTimeString(),
                            'recordar_en' => $inicio->copy()->subDay()->setTime(12, 0, 0)->toDateTimeString(),
                            'updated_at' => now($timezone),
                        ]);
                }
            });
    }

    public function down(): void
    {
    }
};
