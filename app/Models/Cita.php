<?php

namespace App\Models;

use App\Services\PriorityEvaluator;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Cita extends Model
{
    use HasFactory;

    protected $table = 'citas_medicas';

    protected $fillable = [
        'paciente_id',
        'dependiente_id',
        'source_nota_soap_id',
        'doctor_id',
        'especialidad_id',
        'fecha',
        'hora',
        'motivo_consulta',
        'estado',
        'activo',
        'folio_cita',
        'token_validacion',
        'csv',
        'comprobante_pdf_path',
        'comprobante_pdf_disk',
        'comprobante_emitido_en',
        'comprobante_actualizado_en',
        'prioridad_nivel',
        'prioridad_fuente',
        'prioridad_red_flag',
        'prioridad_red_flag_tipo',
        'prioridad_comentario',
        'prioridad_es_adulto_mayor',
        'prioridad_es_embarazo',
        'prioridad_es_discapacidad',
        'prioridad_es_cronico',
    ];

    public const ESTADO_PENDIENTE = 'pendiente';

    public const ESTADO_CONFIRMADA = 'confirmada';

    public const ESTADO_CANCELADA = 'cancelada';

    public const ESTADO_REALIZADA = 'realizada';

    public const ESTADO_NO_SE_PRESENTO = 'no_se_presento';

    public const PRIORIDAD_BAJA = 'BAJA';

    public const PRIORIDAD_MEDIA = 'MEDIA';

    public const PRIORIDAD_ALTA = 'ALTA';

    public const PRIORIDAD_NIVELES = [
        self::PRIORIDAD_BAJA,
        self::PRIORIDAD_MEDIA,
        self::PRIORIDAD_ALTA,
    ];

    public const FUENTE_PRIORIDAD_AUTOMATICA = 'AUTOMATICA';

    public const FUENTE_PRIORIDAD_REGLA_RED_FLAG = 'REGLA_RED_FLAG';

    public const FUENTE_PRIORIDAD_REGLA_VULNERABILIDAD = 'REGLA_VULNERABILIDAD';

    public const FUENTE_PRIORIDAD_MANUAL = 'MANUAL';

    public const ESTADOS_TERMINALES = [
        self::ESTADO_CANCELADA,
        self::ESTADO_REALIZADA,
        self::ESTADO_NO_SE_PRESENTO,
    ];

    public const ESTADOS_REPROGRAMABLES = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADA,
    ];

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_REALIZADA,
        self::ESTADO_NO_SE_PRESENTO,
    ];

    protected $casts = [
        'fecha' => 'date',
        'hora' => 'string',
        'activo' => 'boolean',
        'motivo_consulta' => 'string',
        'comprobante_emitido_en' => 'datetime',
        'comprobante_actualizado_en' => 'datetime',
        'prioridad_nivel' => 'string',
        'prioridad_fuente' => 'string',
        'prioridad_red_flag' => 'boolean',
        'prioridad_red_flag_tipo' => 'string',
        'prioridad_comentario' => 'string',
        'prioridad_es_adulto_mayor' => 'boolean',
        'prioridad_es_embarazo' => 'boolean',
        'prioridad_es_discapacidad' => 'boolean',
        'prioridad_es_cronico' => 'boolean',
    ];

    public function paciente()
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function dependiente()
    {
        return $this->belongsTo(Dependiente::class, 'dependiente_id');
    }

    public function sourceNotaSoap()
    {
        return $this->belongsTo(NotaSoap::class, 'source_nota_soap_id');
    }

    public function nombrePacienteReal(): string
    {
        if ($this->dependiente_id && $this->dependiente) {
            return (string) ($this->dependiente->nombre ?: 'N/D');
        }

        return (string) ($this->paciente?->name ?: 'N/D');
    }

    public function dniPacienteReal(): string
    {
        if ($this->dependiente_id && $this->dependiente) {
            return (string) ($this->dependiente->dni ?: 'N/D');
        }

        return (string) ($this->paciente?->dni ?: 'N/D');
    }

    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function especialidad()
    {
        return $this->belongsTo(Especialidad::class);
    }

    /** Relación con factura (una factura por cita) */
    public function factura()
    {
        return $this->hasOne(Factura::class, 'cita_id');
    }

    public function pago()
    {
        return $this->hasOne(Pago::class, 'cita_id');
    }

    /** Relación con receta (una receta por cita) */
    public function receta()
    {
        return $this->hasOne(Receta::class, 'cita_id');
    }

    /** Relación con nota SOAP (una nota por cita) */
    public function notaSoap()
    {
        return $this->hasOne(NotaSoap::class, 'cita_id');
    }

    /** Relación con Pedido de Laboratorio MVP */
    public function pedidoLaboratorio()
    {
        return $this->hasOne(PedidoLaboratorio::class, 'cita_id');
    }

    public function certificadoMedico()
    {
        return $this->hasOne(CertificadoMedico::class, 'cita_id')
            ->where(function ($query) {
                $query->whereNull('certificados_medicos.estado_version')
                    ->orWhere('certificados_medicos.estado_version', '!=', 'reemplazado');
            })
            ->latestOfMany('id');
    }

    public function certificadosMedicos()
    {
        return $this->hasMany(CertificadoMedico::class, 'cita_id')->orderBy('version', 'desc');
    }

    public function laboratorioOrden()
    {
        return $this->hasOne(LaboratorioOrden::class, 'cita_id');
    }

    public function recordatorio()
    {
        return $this->hasOne(CitaRecordatorio::class, 'cita_id');
    }

    public function refreshPriority(): bool
    {
        $original = [
            'prioridad_nivel' => (string) $this->prioridad_nivel,
            'prioridad_fuente' => (string) $this->prioridad_fuente,
            'prioridad_red_flag' => (bool) $this->prioridad_red_flag,
            'prioridad_red_flag_tipo' => $this->prioridad_red_flag_tipo,
            'prioridad_es_adulto_mayor' => (bool) $this->prioridad_es_adulto_mayor,
            'prioridad_es_embarazo' => (bool) $this->prioridad_es_embarazo,
            'prioridad_es_discapacidad' => (bool) $this->prioridad_es_discapacidad,
            'prioridad_es_cronico' => (bool) $this->prioridad_es_cronico,
        ];

        app(PriorityEvaluator::class)->apply($this);

        return $original['prioridad_nivel'] !== (string) $this->prioridad_nivel
            || $original['prioridad_fuente'] !== (string) $this->prioridad_fuente
            || $original['prioridad_red_flag'] !== (bool) $this->prioridad_red_flag
            || $original['prioridad_red_flag_tipo'] !== $this->prioridad_red_flag_tipo
            || $original['prioridad_es_adulto_mayor'] !== (bool) $this->prioridad_es_adulto_mayor
            || $original['prioridad_es_embarazo'] !== (bool) $this->prioridad_es_embarazo
            || $original['prioridad_es_discapacidad'] !== (bool) $this->prioridad_es_discapacidad
            || $original['prioridad_es_cronico'] !== (bool) $this->prioridad_es_cronico;
    }

    public static function prioridadRank(string $nivel): int
    {
        return match (strtoupper($nivel)) {
            self::PRIORIDAD_ALTA => 3,
            self::PRIORIDAD_MEDIA => 2,
            default => 1,
        };
    }

    public static function prioridadOrderSql(string $column = 'prioridad_nivel'): string
    {
        return "CASE {$column} WHEN 'ALTA' THEN 1 WHEN 'MEDIA' THEN 2 ELSE 3 END";
    }

    public function inicioProgramado(string $tz = 'America/Guayaquil'): Carbon
    {
        $fecha = $this->fecha instanceof Carbon
            ? $this->fecha->copy()
            : Carbon::parse($this->fecha, $tz);
        $hora = $this->hora ? substr((string) $this->hora, 0, 5) : '00:00';

        return Carbon::parse($fecha->format('Y-m-d').' '.$hora, $tz);
    }

    public function finProgramado(string $tz = 'America/Guayaquil', int $duracionMin = 30): Carbon
    {
        return $this->inicioProgramado($tz)->addMinutes($duracionMin);
    }

    public function estaVencida(string $tz = 'America/Guayaquil', int $duracionMin = 30): bool
    {
        return Carbon::now($tz)->greaterThanOrEqualTo(
            $this->finProgramado($tz, $duracionMin)
        );
    }

    public function esReprogramable(string $tz = 'America/Guayaquil', int $duracionMin = 30): bool
    {
        if (! $this->activo) {
            return false;
        }

        if (! in_array($this->estado, self::ESTADOS_REPROGRAMABLES, true)) {
            return false;
        }

        if ($this->estaVencida($tz, $duracionMin)) {
            return false;
        }

        return true;
    }

    public function esTerminal(): bool
    {
        return in_array($this->estado, self::ESTADOS_TERMINALES, true);
    }

    public function tieneComprobanteCita(): bool
    {
        return ! empty($this->folio_cita) && ! empty($this->token_validacion);
    }

    public function comprobanteEstaVigente(): bool
    {
        return in_array($this->estado, [
            self::ESTADO_PENDIENTE,
            self::ESTADO_CONFIRMADA,
        ], true);
    }

    public function estadoComprobante(): string
    {
        return match ($this->estado) {
            self::ESTADO_CONFIRMADA => 'Confirmada',
            self::ESTADO_CANCELADA => 'Cancelada',
            self::ESTADO_REALIZADA => 'Atendida',
            self::ESTADO_NO_SE_PRESENTO => 'No se presento',
            default => 'Pendiente',
        };
    }
}
