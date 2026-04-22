<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cita_recordatorios', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('cita_id')->constrained('citas_medicas')->cascadeOnDelete();
            $table->string('estado', 20)->default('pendiente');
            $table->timestamp('cita_inicio_at')->nullable();
            $table->timestamp('recordar_en')->nullable();
            $table->timestamp('enviado_at')->nullable();
            $table->timestamp('omitido_at')->nullable();
            $table->foreignId('gestionado_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique('cita_id');
            $table->index(['estado', 'recordar_en']);
            $table->index('cita_inicio_at');
        });

        $this->backfill();
    }

    public function down(): void
    {
        Schema::dropIfExists('cita_recordatorios');
    }

    private function backfill(): void
    {
        if (! Schema::hasTable('citas_medicas')) {
            return;
        }

        $timezone = config('app.timezone', 'America/Guayaquil');
        $timestamp = now($timezone);

        DB::table('citas_medicas')
            ->select(['id', 'fecha', 'hora', 'estado', 'activo'])
            ->where('activo', true)
            ->where('estado', 'confirmada')
            ->whereNotNull('fecha')
            ->whereNotNull('hora')
            ->orderBy('id')
            ->chunkById(200, function ($citas) use ($timezone, $timestamp): void {
                foreach ($citas as $cita) {
                    $inicio = Carbon::parse(
                        sprintf('%s %s', $cita->fecha, substr((string) $cita->hora, 0, 5)),
                        $timezone
                    );

                    DB::table('cita_recordatorios')->updateOrInsert(
                        ['cita_id' => $cita->id],
                        [
                            'estado' => 'pendiente',
                            'cita_inicio_at' => $inicio->toDateTimeString(),
                            'recordar_en' => $inicio->copy()->subDay()->setTime(12, 0, 0)->toDateTimeString(),
                            'created_at' => $timestamp,
                            'updated_at' => $timestamp,
                        ]
                    );
                }
            });
    }
};
