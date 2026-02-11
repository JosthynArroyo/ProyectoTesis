<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Cita extends Model
{
    use HasFactory;

    protected $table = 'citas_medicas';

    protected $fillable = [
        'paciente_id',
        'doctor_id',
        'especialidad_id',
        'fecha',
        'hora',
        'estado',
        'activo',
        'pending_since',
        'priority_score',
        'priority_level',
        'last_priority_notified_at',
    ];

    public const ESTADO_PENDIENTE  = 'pendiente';
    public const ESTADO_CONFIRMADA = 'confirmada';
    public const ESTADO_CANCELADA  = 'cancelada';
    public const ESTADO_REALIZADA  = 'realizada';
    public const ESTADO_NO_SE_PRESENTO = 'no_se_presento';

    public const ESTADOS = [
        self::ESTADO_PENDIENTE,
        self::ESTADO_CONFIRMADA,
        self::ESTADO_CANCELADA,
        self::ESTADO_REALIZADA,
        self::ESTADO_NO_SE_PRESENTO,
    ];

    protected $casts = [
        'fecha'  => 'date',
        'hora'   => 'string',
        'activo' => 'boolean',
        'pending_since' => 'datetime',
        'priority_score' => 'integer',
        'priority_level' => 'string',
        'last_priority_notified_at' => 'datetime',
    ];

    public function paciente()
    {
        return $this->belongsTo(User::class, 'paciente_id');
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

    public function laboratorioOrden()
    {
        return $this->hasOne(LaboratorioOrden::class, 'cita_id');
    }

    public function calculatePriorityScore(): int
    {
        if ($this->estado !== self::ESTADO_PENDIENTE || !$this->activo) {
            return 0;
        }

        $score = 0;
        $flags = $this->paciente?->patientFlag;
        if ($flags) {
            $score += $flags->adulto_mayor ? 15 : 0;
            $score += $flags->embarazo ? 20 : 0;
            $score += $flags->discapacidad ? 20 : 0;
            $score += $flags->cronico ? 15 : 0;
        }

        $pendingSince = $this->pending_since ?: $this->created_at;
        if ($pendingSince) {
            $hours = Carbon::now(config('app.timezone', 'UTC'))->diffInHours($pendingSince, false);
            $hours = $hours < 0 ? 0 : $hours;

            if ($hours >= 48) {
                $score += 60;
            } elseif ($hours >= 24) {
                $score += 30;
            } elseif ($hours >= 6) {
                $score += 10;
            }
        }

        return $score;
    }

    public function calculatePriorityLevel(int $score = null): string
    {
        $score = $score ?? (int) $this->priority_score;

        if ($score >= 60) {
            return 'critica';
        }
        if ($score >= 30) {
            return 'alta';
        }
        if ($score >= 10) {
            return 'media';
        }
        return 'baja';
    }

    public function refreshPriority(): bool
    {
        $originalScore = (int) $this->priority_score;
        $originalLevel = (string) $this->priority_level;

        if ($this->estado !== self::ESTADO_PENDIENTE || !$this->activo) {
            $this->priority_score = 0;
            $this->priority_level = 'baja';
            return $this->priority_score !== $originalScore
                || $this->priority_level !== $originalLevel;
        }

        if (!$this->pending_since) {
            $this->pending_since = $this->created_at ?: Carbon::now(config('app.timezone', 'UTC'));
        }

        $score = $this->calculatePriorityScore();
        $this->priority_score = $score;
        $this->priority_level = $this->calculatePriorityLevel($score);

        return $this->priority_score !== $originalScore
            || $this->priority_level !== $originalLevel;
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
}
