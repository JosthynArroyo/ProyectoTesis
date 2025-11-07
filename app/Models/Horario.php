<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;

class Horario extends Model
{
    use HasFactory;

    /**
     * Tabla asociada al modelo
     */
    protected $table = 'horarios';

    /**
     * Campos asignables masivamente
     */
    protected $fillable = [
        'doctor_id',
        'fecha',
        'hora_inicio',
        'hora_fin',
    ];

    /**
     * Casteo automático de atributos
     */
    protected $casts = [
        'fecha' => 'date',
        'doctor_id' => 'integer',
    ];

    /**
     * Relación: Un horario pertenece a un doctor (User)
     */
    public function doctor()
    {
        return $this->belongsTo(User::class, 'doctor_id');
    }

    /**
     * Relación: Un horario puede tener muchas citas
     * (Comentar si no tienes modelo Cita todavía)
     */
    public function citas()
    {
        return $this->hasMany(Cita::class, 'horario_id');
    }

    /**
     * Scope: Filtrar horarios de una semana específica
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param \Carbon\Carbon $startDate
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeWeek($query, Carbon $startDate)
    {
        $start = $startDate->copy()->startOfWeek(Carbon::MONDAY);
        $end = $start->copy()->endOfWeek(Carbon::SUNDAY);

        return $query->whereBetween('fecha', [$start->toDateString(), $end->toDateString()]);
    }

    /**
     * Scope: Filtrar horarios de un doctor específico
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $doctorId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeForDoctor($query, int $doctorId)
    {
        return $query->where('doctor_id', $doctorId);
    }

    /**
     * Scope: Horarios disponibles (sin citas confirmadas o pendientes)
     * 
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeAvailable($query)
    {
        return $query->whereDoesntHave('citas', function($q) {
            $q->whereIn('estado', ['confirmada', 'pendiente']);
        });
    }

    /**
     * Accessor: Hora de inicio formateada (HH:MM)
     * 
     * @return string
     */
    public function getHoraInicioFormattedAttribute(): string
    {
        return Carbon::parse($this->hora_inicio)->format('H:i');
    }

    /**
     * Accessor: Hora de fin formateada (HH:MM)
     * 
     * @return string
     */
    public function getHoraFinFormattedAttribute(): string
    {
        return Carbon::parse($this->hora_fin)->format('H:i');
    }

    /**
     * Accessor: Duración del horario en minutos
     * 
     * @return int
     */
    public function getDuracionMinutosAttribute(): int
    {
        $inicio = Carbon::parse($this->hora_inicio);
        $fin = Carbon::parse($this->hora_fin);
        return $inicio->diffInMinutes($fin);
    }

    /**
     * Accessor: Nombre completo del día de la semana
     * 
     * @return string
     */
    public function getDiaNombreAttribute(): string
    {
        return $this->fecha->locale('es')->isoFormat('dddd');
    }

    /**
     * Método: Verificar si el horario está disponible
     * 
     * @return bool
     */
    public function estaDisponible(): bool
    {
        // Si no existe la relación citas, considerar disponible
        if (!method_exists($this, 'citas')) {
            return true;
        }

        return !$this->citas()
            ->whereIn('estado', ['confirmada', 'pendiente'])
            ->exists();
    }

    /**
     * Método: Obtener información completa del horario formateada
     * 
     * @return string
     */
    public function getInfoCompleta(): string
    {
        return sprintf(
            '%s - %s a %s (%s)',
            $this->fecha->format('d/m/Y'),
            $this->hora_inicio_formatted,
            $this->hora_fin_formatted,
            $this->doctor->name ?? 'Sin doctor'
        );
    }

    /**
     * Método: Verificar si hay solapamiento con otro horario
     * 
     * @param string $horaInicio
     * @param string $horaFin
     * @param int|null $excludeId
     * @return bool
     */
    public function hasSolapamiento(string $horaInicio, string $horaFin, ?int $excludeId = null): bool
    {
        return static::where('doctor_id', $this->doctor_id)
            ->where('fecha', $this->fecha)
            ->when($excludeId, fn($q) => $q->where('id', '!=', $excludeId))
            ->where(function ($q) use ($horaInicio, $horaFin) {
                $q->whereBetween('hora_inicio', [$horaInicio, $horaFin])
                  ->orWhereBetween('hora_fin', [$horaInicio, $horaFin])
                  ->orWhere(function($qq) use ($horaInicio, $horaFin) {
                      $qq->where('hora_inicio', '<=', $horaInicio)
                         ->where('hora_fin', '>=', $horaFin);
                  });
            })->exists();
    }
}