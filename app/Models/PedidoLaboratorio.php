<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PedidoLaboratorio extends Model
{
    use HasFactory;

    public const ESTADO_PENDIENTE_TOMA = 'pendiente_toma';

    public const ESTADO_MUESTRA_TOMADA = 'muestra_tomada';

    public const ESTADO_RESULTADO_LISTO = 'resultado_listo';

    protected $table = 'pedidos_laboratorio';

    protected $fillable = [
        'cita_id',
        'paciente_id',
        'doctor_id',
        'csv',
        'examenes',
        'pdf_path',
        'pdf_disk',
        'estado',
        'resultado_path',
        'resultado_resumen',
        'resultado_publicado_at',
        'resultado_enviado_at',
        'enviado_a',
        'enviado_en',
        'envio_estado',
        'envio_error',
        'envio_intentos',
    ];

    protected $casts = [
        'examenes' => 'array',
        'resultado_publicado_at' => 'datetime',
        'resultado_enviado_at' => 'datetime',
        'enviado_en' => 'datetime',
        'envio_intentos' => 'integer',
    ];

    public function cita(): BelongsTo
    {
        return $this->belongsTo(Cita::class, 'cita_id');
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(User::class, 'paciente_id');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    public function resultados(): HasMany
    {
        return $this->hasMany(PedidoLaboratorioResultado::class, 'pedido_laboratorio_id');
    }

    public function resultadoActual(): HasOne
    {
        return $this->hasOne(PedidoLaboratorioResultado::class, 'pedido_laboratorio_id')->latestOfMany('version');
    }

    public function resultadoPublicadoActual(): HasOne
    {
        return $this->hasOne(PedidoLaboratorioResultado::class, 'pedido_laboratorio_id')
            ->ofMany('version', 'max', function ($query) {
                $query->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO);
            });
    }

    public function nombrePacienteReal(): string
    {
        return (string) ($this->cita?->nombrePacienteReal() ?: $this->paciente?->name ?: 'Paciente');
    }

    public function representanteNombre(): ?string
    {
        if (! $this->cita?->dependiente_id) {
            return null;
        }

        return $this->cita?->paciente?->name ?: null;
    }

    public function dniPacienteReal(): ?string
    {
        return $this->cita?->dniPacienteReal() ?: $this->paciente?->dni;
    }

    public function resultadoPublicado(): bool
    {
        return filled($this->resultado_publicado_at) && filled($this->resultado_path);
    }
}
