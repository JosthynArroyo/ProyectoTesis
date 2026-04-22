<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\User;
use Carbon\Carbon;

class PriorityEvaluator
{
    public const NIVEL_BAJA = 'BAJA';

    public const NIVEL_MEDIA = 'MEDIA';

    public const NIVEL_ALTA = 'ALTA';

    public const FUENTE_AUTOMATICA = 'AUTOMATICA';

    public const FUENTE_REGLA_RED_FLAG = 'REGLA_RED_FLAG';

    public const FUENTE_REGLA_VULNERABILIDAD = 'REGLA_VULNERABILIDAD';

    public const FUENTE_MANUAL = 'MANUAL';

    private const RED_FLAGS = [
        'dolor_pecho' => [
            'dolor de pecho',
            'dolor toracico',
        ],
        'dificultad_respiratoria' => [
            'falta de aire',
            'dificultad para respirar',
        ],
        'desmayo_perdida_conciencia' => [
            'desmayo',
            'perdida de conciencia',
        ],
        'convulsion' => [
            'convulsion',
        ],
        'sangrado_abundante' => [
            'sangrado abundante',
        ],
    ];

    public function apply(Cita $cita, ?string $motivoConsulta = null): void
    {
        $cita->fill($this->evaluate($cita, $motivoConsulta));
    }

    /**
     * Reglas de prioridad auditables y deterministas (sin IA).
     */
    public function evaluate(Cita $cita, ?string $motivoConsulta = null): array
    {
        $patient = $this->resolvePatient($cita);
        $vulnerability = $this->resolveVulnerability($patient);

        $motivo = $this->sanitizeMotivo($motivoConsulta ?? (string) $cita->motivo_consulta);
        $redFlagType = $this->detectRedFlagType($motivo);

        $prioridadNivel = self::NIVEL_BAJA;
        $prioridadFuente = self::FUENTE_AUTOMATICA;
        $prioridadRedFlag = false;
        $prioridadRedFlagTipo = null;

        if ($redFlagType !== null) {
            $prioridadNivel = self::NIVEL_ALTA;
            $prioridadFuente = self::FUENTE_REGLA_RED_FLAG;
            $prioridadRedFlag = true;
            $prioridadRedFlagTipo = $redFlagType;
        } elseif ($vulnerability['es_vulnerable']) {
            $prioridadNivel = self::NIVEL_MEDIA;
            $prioridadFuente = self::FUENTE_REGLA_VULNERABILIDAD;
        }

        return [
            'motivo_consulta' => $motivo,
            'prioridad_nivel' => $prioridadNivel,
            'prioridad_fuente' => $prioridadFuente,
            'prioridad_red_flag' => $prioridadRedFlag,
            'prioridad_red_flag_tipo' => $prioridadRedFlagTipo,
            'prioridad_comentario' => null,
            'prioridad_es_adulto_mayor' => $vulnerability['adulto_mayor'],
            'prioridad_es_embarazo' => $vulnerability['embarazo'],
            'prioridad_es_discapacidad' => $vulnerability['discapacidad'],
            'prioridad_es_cronico' => $vulnerability['cronico'],
        ];
    }

    public function sanitizeMotivo(?string $motivo): string
    {
        $value = str_replace(["\r", "\n"], ' ', (string) $motivo);
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '' || mb_strlen($value) < 3) {
            return 'Consulta general';
        }

        return mb_substr($value, 0, 80);
    }

    private function resolvePatient(Cita $cita): ?User
    {
        if ($cita->relationLoaded('paciente')) {
            /** @var User|null $patient */
            $patient = $cita->paciente;

            return $patient?->relationLoaded('patientFlag')
                ? $patient
                : $patient?->load('patientFlag');
        }

        return User::query()
            ->with('patientFlag')
            ->find($cita->paciente_id);
    }

    private function resolveVulnerability(?User $patient): array
    {
        $adultoMayor = false;
        if (! empty($patient?->fecha_nacimiento)) {
            try {
                $adultoMayor = Carbon::parse($patient->fecha_nacimiento)->age >= 65;
            } catch (\Throwable $e) {
                $adultoMayor = false;
            }
        }

        $embarazo = (bool) ($patient?->patientFlag?->embarazo ?? false);
        $discapacidad = (bool) ($patient?->patientFlag?->discapacidad ?? false);
        $cronico = (bool) ($patient?->patientFlag?->cronico ?? false);

        return [
            'adulto_mayor' => $adultoMayor,
            'embarazo' => $embarazo,
            'discapacidad' => $discapacidad,
            'cronico' => $cronico,
            'es_vulnerable' => $adultoMayor || $embarazo || $discapacidad || $cronico,
        ];
    }

    private function detectRedFlagType(string $motivoConsulta): ?string
    {
        $normalized = $this->normalize($motivoConsulta);
        if ($normalized === '') {
            return null;
        }

        foreach (self::RED_FLAGS as $key => $phrases) {
            foreach ($phrases as $phrase) {
                if (str_contains($normalized, $this->normalize($phrase))) {
                    return $key;
                }
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $normalized = mb_strtolower(trim($value));
        $transliterated = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $normalized);
        if ($transliterated !== false) {
            $normalized = $transliterated;
        }
        $normalized = preg_replace('/[^a-z0-9\s]/', ' ', $normalized) ?? '';
        $normalized = preg_replace('/\s+/', ' ', $normalized) ?? '';

        return trim($normalized);
    }
}
