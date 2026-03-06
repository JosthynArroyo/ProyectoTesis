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
        Schema::table('citas_medicas', function (Blueprint $table) {
            if (!Schema::hasColumn('citas_medicas', 'motivo_consulta')) {
                $table->string('motivo_consulta', 80)->default('Consulta general')->after('hora');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_nivel')) {
                $table->string('prioridad_nivel', 10)->default('BAJA')->after('motivo_consulta');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_fuente')) {
                $table->string('prioridad_fuente', 40)->default('AUTOMATICA')->after('prioridad_nivel');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_red_flag')) {
                $table->boolean('prioridad_red_flag')->default(false)->after('prioridad_fuente');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_red_flag_tipo')) {
                $table->string('prioridad_red_flag_tipo', 80)->nullable()->after('prioridad_red_flag');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_comentario')) {
                $table->string('prioridad_comentario', 500)->nullable()->after('prioridad_red_flag_tipo');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_es_adulto_mayor')) {
                $table->boolean('prioridad_es_adulto_mayor')->default(false)->after('prioridad_comentario');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_es_embarazo')) {
                $table->boolean('prioridad_es_embarazo')->default(false)->after('prioridad_es_adulto_mayor');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_es_discapacidad')) {
                $table->boolean('prioridad_es_discapacidad')->default(false)->after('prioridad_es_embarazo');
            }
            if (!Schema::hasColumn('citas_medicas', 'prioridad_es_cronico')) {
                $table->boolean('prioridad_es_cronico')->default(false)->after('prioridad_es_discapacidad');
            }
        });

        $hasLegacyLevel = Schema::hasColumn('citas_medicas', 'priority_level');
        $hasLegacyScore = Schema::hasColumn('citas_medicas', 'priority_score');

        $query = DB::table('citas_medicas')
            ->select('id', 'paciente_id', 'motivo_consulta');

        if ($hasLegacyLevel) {
            $query->addSelect('priority_level');
        }
        if ($hasLegacyScore) {
            $query->addSelect('priority_score');
        }

        $query->orderBy('id')->chunkById(200, function ($citas) use ($hasLegacyLevel, $hasLegacyScore) {
            $patientIds = collect($citas)
                ->pluck('paciente_id')
                ->filter()
                ->unique()
                ->values();

            $patients = DB::table('users')
                ->whereIn('id', $patientIds)
                ->get(['id', 'fecha_nacimiento'])
                ->keyBy('id');

            $patientFlags = DB::table('patient_flags')
                ->whereIn('user_id', $patientIds)
                ->get(['user_id', 'embarazo', 'discapacidad', 'cronico'])
                ->keyBy('user_id');

            foreach ($citas as $cita) {
                $patient = $patients->get($cita->paciente_id);
                $flags = $patientFlags->get($cita->paciente_id);

                $adultoMayor = false;
                if (!empty($patient?->fecha_nacimiento)) {
                    try {
                        $adultoMayor = Carbon::parse($patient->fecha_nacimiento)->age >= 65;
                    } catch (\Throwable $e) {
                        $adultoMayor = false;
                    }
                }

                $embarazo = (bool) ($flags->embarazo ?? false);
                $discapacidad = (bool) ($flags->discapacidad ?? false);
                $cronico = (bool) ($flags->cronico ?? false);
                $vulnerable = $adultoMayor || $embarazo || $discapacidad || $cronico;

                $legacyLevel = $hasLegacyLevel ? (string) ($cita->priority_level ?? '') : '';
                $legacyScore = $hasLegacyScore ? (int) ($cita->priority_score ?? 0) : 0;
                [$nivel, $fuente] = $this->mapLegacyPriority($legacyLevel, $legacyScore, $vulnerable);

                DB::table('citas_medicas')
                    ->where('id', $cita->id)
                    ->update([
                        'motivo_consulta' => $this->sanitizeMotivo($cita->motivo_consulta),
                        'prioridad_nivel' => $nivel,
                        'prioridad_fuente' => $fuente,
                        'prioridad_red_flag' => false,
                        'prioridad_red_flag_tipo' => null,
                        'prioridad_comentario' => null,
                        'prioridad_es_adulto_mayor' => $adultoMayor,
                        'prioridad_es_embarazo' => $embarazo,
                        'prioridad_es_discapacidad' => $discapacidad,
                        'prioridad_es_cronico' => $cronico,
                    ]);
            }
        }, 'id');

        Schema::table('citas_medicas', function (Blueprint $table) {
            if (Schema::hasColumn('citas_medicas', 'last_priority_notified_at')) {
                $table->dropColumn('last_priority_notified_at');
            }
            if (Schema::hasColumn('citas_medicas', 'priority_level')) {
                $table->dropColumn('priority_level');
            }
            if (Schema::hasColumn('citas_medicas', 'priority_score')) {
                $table->dropColumn('priority_score');
            }
            if (Schema::hasColumn('citas_medicas', 'pending_since')) {
                $table->dropColumn('pending_since');
            }
        });
    }

    public function down(): void
    {
        Schema::table('citas_medicas', function (Blueprint $table) {
            if (!Schema::hasColumn('citas_medicas', 'pending_since')) {
                $table->timestamp('pending_since')->nullable()->after('hora');
            }
            if (!Schema::hasColumn('citas_medicas', 'priority_score')) {
                $table->integer('priority_score')->default(0)->after('pending_since');
            }
            if (!Schema::hasColumn('citas_medicas', 'priority_level')) {
                $table->string('priority_level', 20)->default('baja')->after('priority_score');
            }
            if (!Schema::hasColumn('citas_medicas', 'last_priority_notified_at')) {
                $table->timestamp('last_priority_notified_at')->nullable()->after('priority_level');
            }

            if (Schema::hasColumn('citas_medicas', 'prioridad_es_cronico')) {
                $table->dropColumn('prioridad_es_cronico');
            }
            if (Schema::hasColumn('citas_medicas', 'prioridad_es_discapacidad')) {
                $table->dropColumn('prioridad_es_discapacidad');
            }
            if (Schema::hasColumn('citas_medicas', 'prioridad_es_embarazo')) {
                $table->dropColumn('prioridad_es_embarazo');
            }
            if (Schema::hasColumn('citas_medicas', 'prioridad_es_adulto_mayor')) {
                $table->dropColumn('prioridad_es_adulto_mayor');
            }
            if (Schema::hasColumn('citas_medicas', 'prioridad_comentario')) {
                $table->dropColumn('prioridad_comentario');
            }
            if (Schema::hasColumn('citas_medicas', 'prioridad_red_flag_tipo')) {
                $table->dropColumn('prioridad_red_flag_tipo');
            }
            if (Schema::hasColumn('citas_medicas', 'prioridad_red_flag')) {
                $table->dropColumn('prioridad_red_flag');
            }
            if (Schema::hasColumn('citas_medicas', 'prioridad_fuente')) {
                $table->dropColumn('prioridad_fuente');
            }
            if (Schema::hasColumn('citas_medicas', 'prioridad_nivel')) {
                $table->dropColumn('prioridad_nivel');
            }
            if (Schema::hasColumn('citas_medicas', 'motivo_consulta')) {
                $table->dropColumn('motivo_consulta');
            }
        });
    }

    private function mapLegacyPriority(string $legacyLevel, int $legacyScore, bool $vulnerable): array
    {
        $legacy = mb_strtolower(trim($legacyLevel));

        if (in_array($legacy, ['critica', 'alta'], true)) {
            return ['ALTA', 'AUTOMATICA'];
        }
        if ($legacy === 'media') {
            return ['MEDIA', $vulnerable ? 'REGLA_VULNERABILIDAD' : 'AUTOMATICA'];
        }
        if ($legacy === 'baja') {
            return ['BAJA', 'AUTOMATICA'];
        }

        if ($legacyScore >= 30) {
            return ['ALTA', 'AUTOMATICA'];
        }
        if ($legacyScore >= 10) {
            return ['MEDIA', $vulnerable ? 'REGLA_VULNERABILIDAD' : 'AUTOMATICA'];
        }

        return $vulnerable
            ? ['MEDIA', 'REGLA_VULNERABILIDAD']
            : ['BAJA', 'AUTOMATICA'];
    }

    private function sanitizeMotivo(?string $motivo): string
    {
        $value = preg_replace('/\s+/u', ' ', trim(str_replace(["\r", "\n"], ' ', (string) $motivo)));
        if ($value === null || $value === '') {
            return 'Consulta general';
        }

        return mb_substr($value, 0, 80);
    }
};

