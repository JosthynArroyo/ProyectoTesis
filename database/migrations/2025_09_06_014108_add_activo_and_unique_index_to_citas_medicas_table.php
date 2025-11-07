<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            if (!Schema::hasColumn('citas_medicas', 'activo')) {
                $table->boolean('activo')->default(1)->after('estado');
            }
        });

        DB::table('citas_medicas')->where('estado', 'cancelada')->update(['activo' => 0]);

        $rows = DB::table('citas_medicas')
            ->select('id','doctor_id','fecha','hora','activo','created_at')
            ->where('activo', 1)
            ->orderBy('doctor_id')->orderBy('fecha')->orderBy('hora')->orderBy('created_at')
            ->get();

        $seen = [];
        $toDeactivate = [];
        foreach ($rows as $r) {
            $key = $r->doctor_id.'|'.$r->fecha.'|'.$r->hora;
            if (isset($seen[$key])) {
                $toDeactivate[] = $r->id;
            } else {
                $seen[$key] = $r->id;
            }
        }
        if (!empty($toDeactivate)) {
            DB::table('citas_medicas')->whereIn('id', $toDeactivate)->update(['activo' => 0]);
        }

        $driver = DB::getDriverName();

        if ($driver === 'sqlite') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS citas_unq_doctor_fecha_hora_activo ON citas_medicas(doctor_id, fecha, hora) WHERE activo = 1');
        } elseif ($driver === 'pgsql') {
            DB::statement('CREATE UNIQUE INDEX IF NOT EXISTS citas_unq_doctor_fecha_hora_activo ON citas_medicas(doctor_id, fecha, hora) WHERE activo = true');
        } else {
            Schema::table('citas_medicas', function (Blueprint $table) {
                if (!Schema::hasColumn('citas_medicas', 'hora_unique')) {
                    $table->string('hora_unique', 8)->nullable()->storedAs('CASE WHEN activo = 1 THEN hora ELSE NULL END');
                }
            });
            Schema::table('citas_medicas', function (Blueprint $table) {
                $table->unique(['doctor_id','fecha','hora_unique'], 'citas_unq_doctor_fecha_hora_activo');
            });
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'sqlite' || $driver === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS citas_unq_doctor_fecha_hora_activo');
        } else {
            Schema::table('citas_medicas', function (Blueprint $table) {
                $table->dropUnique('citas_unq_doctor_fecha_hora_activo');
            });
            Schema::table('citas_medicas', function (Blueprint $table) {
                if (Schema::hasColumn('citas_medicas', 'hora_unique')) {
                    $table->dropColumn('hora_unique');
                }
            });
        }
    }
};
