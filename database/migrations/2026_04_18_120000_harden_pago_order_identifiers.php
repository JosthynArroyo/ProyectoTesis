<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private const TOKEN_GENERATION_ATTEMPTS = 20;

    private const FOLIO_COLLISION_ATTEMPTS = 100;

    public function up(): void
    {
        if (! Schema::hasTable('pagos')) {
            return;
        }

        Schema::table('pagos', function (Blueprint $table): void {
            if (! Schema::hasColumn('pagos', 'folio_unico')) {
                $table->string('folio_unico', 60)->nullable()->after('cita_id');
            }
            if (! Schema::hasColumn('pagos', 'token_publico')) {
                $table->string('token_publico', 120)->nullable()->after('folio_unico');
            }
        });

        $this->normalizarIdentificadoresDeOrden();

        Schema::table('pagos', function (Blueprint $table): void {
            if (! Schema::hasIndex('pagos', ['folio_unico'], 'unique')) {
                $table->unique('folio_unico', 'pagos_folio_unico_unique');
            }

            if (! Schema::hasIndex('pagos', ['token_publico'], 'unique')) {
                $table->unique('token_publico', 'pagos_token_publico_unique');
            }
        });
    }

    public function down(): void
    {
        // No se revierte: puede haber reparado datos historicos y los indices pueden preexistir.
    }

    private function normalizarIdentificadoresDeOrden(): void
    {
        $this->completarOrdenesHistoricas();
        $this->resolverFoliosDuplicados();
        $this->resolverTokensDuplicados();
    }

    private function completarOrdenesHistoricas(): void
    {
        DB::table('pagos as p')
            ->leftJoin('citas_medicas as c', 'c.id', '=', 'p.cita_id')
            ->where(function ($query): void {
                $query->where('c.estado', 'realizada')
                    ->orWhere('p.estado', 'pagado')
                    ->orWhereNotNull('p.folio_unico')
                    ->orWhereNotNull('p.token_publico');
            })
            ->orderBy('p.id')
            ->select([
                'p.id',
                'p.folio_unico',
                'p.token_publico',
                'p.created_at',
                'c.fecha as cita_fecha',
            ])
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $updates = [];

                    if (empty($row->folio_unico)) {
                        $updates['folio_unico'] = $this->generarFolioOrden($row);
                    }

                    if (empty($row->token_publico)) {
                        $updates['token_publico'] = $this->generarTokenPublico((int) $row->id);
                    }

                    if ($updates !== []) {
                        DB::table('pagos')
                            ->where('id', $row->id)
                            ->update($updates);
                    }
                }
            }, 'p.id', 'id');
    }

    private function resolverFoliosDuplicados(): void
    {
        $duplicados = DB::table('pagos')
            ->select('folio_unico')
            ->whereNotNull('folio_unico')
            ->groupBy('folio_unico')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('folio_unico');

        foreach ($duplicados as $folioDuplicado) {
            $rows = DB::table('pagos as p')
                ->leftJoin('citas_medicas as c', 'c.id', '=', 'p.cita_id')
                ->where('p.folio_unico', $folioDuplicado)
                ->orderBy('p.id')
                ->select([
                    'p.id',
                    'p.created_at',
                    'c.fecha as cita_fecha',
                ])
                ->get();

            foreach ($rows->skip(1) as $row) {
                DB::table('pagos')
                    ->where('id', $row->id)
                    ->update([
                        'folio_unico' => $this->generarFolioOrden($row),
                    ]);
            }
        }
    }

    private function resolverTokensDuplicados(): void
    {
        $duplicados = DB::table('pagos')
            ->select('token_publico')
            ->whereNotNull('token_publico')
            ->groupBy('token_publico')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('token_publico');

        foreach ($duplicados as $tokenDuplicado) {
            $rows = DB::table('pagos')
                ->where('token_publico', $tokenDuplicado)
                ->orderBy('id')
                ->get(['id']);

            foreach ($rows->skip(1) as $row) {
                DB::table('pagos')
                    ->where('id', $row->id)
                    ->update([
                        'token_publico' => $this->generarTokenPublico((int) $row->id),
                    ]);
            }
        }
    }

    private function generarFolioOrden(object $row): string
    {
        $fecha = $this->fechaFolio($row);
        $base = sprintf('OC-%s-%06d', $fecha, (int) $row->id);
        $folio = $base;
        $i = 1;

        while (
            DB::table('pagos')
                ->where('folio_unico', $folio)
                ->where('id', '!=', $row->id)
                ->exists()
        ) {
            if ($i >= self::FOLIO_COLLISION_ATTEMPTS) {
                throw new RuntimeException('No fue posible normalizar folios unicos de pagos.');
            }

            $i++;
            $folio = $base.'-'.$i;
        }

        return $folio;
    }

    private function generarTokenPublico(int $pagoId): string
    {
        for ($attempt = 1; $attempt <= self::TOKEN_GENERATION_ATTEMPTS; $attempt++) {
            $token = Str::lower(Str::random(48));

            $existe = DB::table('pagos')
                ->where('token_publico', $token)
                ->where('id', '!=', $pagoId)
                ->exists();

            if (! $existe) {
                return $token;
            }
        }

        throw new RuntimeException('No fue posible normalizar tokens publicos unicos de pagos.');
    }

    private function fechaFolio(object $row): string
    {
        $valor = $row->cita_fecha ?: ($row->created_at ?: now('America/Guayaquil'));
        $timestamp = strtotime((string) $valor);

        if ($timestamp === false) {
            return now('America/Guayaquil')->format('Ymd');
        }

        return date('Ymd', $timestamp);
    }
};
