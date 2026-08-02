<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Dependiente extends Model
{
    use HasFactory;

    public const MAX_POR_USUARIO = 10;

    public const PARENTESCOS = [
        'hijo', 'hija',
        'padre', 'madre',
        'abuelo', 'abuela',
        'nieto', 'nieta',
        'otro',
    ];

    protected $fillable = [
        'user_id',
        'nombre',
        'tipo_documento',
        'nacionalidad',
        'dni',
        'fecha_nacimiento',
        'sexo',
        'parentesco',
        'telefono_emergencia',
        'notas',
        'activo',
        'avatar',
    ];

    public function avatarUrl(string $variant = 'thumb'): string
    {
        return app(\App\Services\ProfileAvatarService::class)->dependentAvatarUrl($this, $variant);
    }

    public function getAvatarUrlAttribute(): string
    {
        return $this->avatarUrl('medium');
    }

    public function getAvatarThumbUrlAttribute(): string
    {
        return $this->avatarUrl('thumb');
    }

    public function getAvatarMediumUrlAttribute(): string
    {
        return $this->avatarUrl('medium');
    }

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'activo' => 'boolean',
    ];

    public function setDniAttribute($value): void
    {
        $tipo = $this->attributes['tipo_documento'] ?? 'cedula';
        $this->attributes['dni'] = \App\Services\IdentityDocumentService::normalize($value, $tipo);
    }

    protected static function booted(): void
    {
        static::saved(function (Dependiente $dep): void {
            if (filled($dep->dni)) {
                app(\App\Services\IdentityDocumentService::class)->sync(
                    $dep,
                    $dep->dni,
                    $dep->tipo_documento ?? 'cedula',
                    $dep->nacionalidad
                );
            }
        });
    }

    /* ============================================================
     * Relaciones
     * ============================================================ */

    /** El usuario titular / responsable. */
    public function responsable(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** Citas agendadas para este dependiente. */
    public function citas(): HasMany
    {
        return $this->hasMany(Cita::class, 'dependiente_id');
    }

    /** Historial clínico propio del dependiente. */
    public function clinicalRecord(): HasOne
    {
        return $this->hasOne(ClinicalRecord::class, 'dependiente_id');
    }

    /* ============================================================
     * Helpers de edad
     * ============================================================ */

    /** Edad actual en años. */
    public function edad(): int
    {
        return (int) $this->fecha_nacimiento->age;
    }

    /** ¿Es menor de 18 años? */
    public function esMenor(): bool
    {
        return $this->edad() < 18;
    }

    /** ¿Es adulto mayor (> 65 años)? */
    public function esAdultoMayor(): bool
    {
        return $this->edad() > 65;
    }

    /**
     * Valida si la fecha de nacimiento cumple la regla de edad.
     * Solo se permiten menores de 18 O mayores de 65.
     */
    public static function edadPermitida(string $fechaNacimiento): bool
    {
        try {
            $edad = Carbon::parse($fechaNacimiento)->age;
        } catch (\Throwable) {
            return false;
        }

        return $edad < 18 || $edad > 65;
    }

    /* ============================================================
     * Helpers de presentación
     * ============================================================ */

    /** Nombre con parentesco. Ej: "María Pérez (Hija)" */
    public function nombreConParentesco(): string
    {
        return $this->nombre.' ('.ucfirst($this->parentesco).')';
    }

    /** Etiqueta corta de edad. Ej: "8 años" o "72 años" */
    public function etiquetaEdad(): string
    {
        $edad = $this->edad();

        return $edad === 1 ? '1 año' : $edad.' años';
    }

    /* ============================================================
     * Scopes
     * ============================================================ */

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function etiquetaTipoDocumento(): string
    {
        return strtolower($this->tipo_documento ?? 'cedula') === 'pasaporte' ? 'Pasaporte' : 'Cédula';
    }

    public function etiquetaNacionalidad(): string
    {
        if (strtolower($this->tipo_documento ?? 'cedula') !== 'pasaporte' || ! $this->nacionalidad) {
            return '';
        }
        return \App\Support\CountryCatalog::getDemonym($this->nacionalidad);
    }

    public function etiquetaDocumentoCompleta(): string
    {
        if (! $this->dni) {
            return 'Documento no registrado';
        }
        if (strtolower($this->tipo_documento ?? 'cedula') === 'pasaporte') {
            $nac = $this->etiquetaNacionalidad();
            return 'Pasaporte: ' . strtoupper($this->dni) . ($nac ? " ({$nac})" : '');
        }
        return 'Cédula: ' . $this->dni;
    }
}
