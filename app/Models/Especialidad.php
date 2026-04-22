<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Especialidad extends Model
{
    use HasFactory;

    public const LABORATORIO_CLINICO_NORMALIZED = 'laboratorio clinico';

    protected $table = 'especialidades';

    protected $fillable = ['nombre', 'descripcion', 'icono', 'activo', 'orden'];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function doctores()
    {
        return $this->belongsToMany(User::class, 'doctor_especialidad', 'especialidad_id', 'user_id')
            ->withTimestamps();
    }

    public static function laboratorioClinicoNombres(): array
    {
        return ['Laboratorio Clínico', 'Laboratorio Clinico'];
    }

    public static function normalizedLaboratorioClinico(): string
    {
        return self::LABORATORIO_CLINICO_NORMALIZED;
    }

    public static function normalizeNombre(?string $nombre): string
    {
        return (string) Str::of(Str::ascii((string) $nombre))
            ->lower()
            ->squish();
    }

    public static function laboratorioClinicoId(): ?int
    {
        return static::query()
            ->whereIn('nombre', static::laboratorioClinicoNombres())
            ->value('id');
    }

    public function isLaboratorioClinico(): bool
    {
        return static::normalizeNombre($this->nombre) === static::normalizedLaboratorioClinico();
    }
}
