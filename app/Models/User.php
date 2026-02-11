<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'telefono',
        'dni',
        'direccion',
        'fecha_nacimiento',
        'sexo',
        'avatar',
        'precio_consulta',
        'moneda',
        'status',
        'last_login_at',
        'last_activity_at',
        'suspended_until',
        'deactivation_reason',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password'          => 'hashed',
        'fecha_nacimiento'  => 'date',
        'precio_consulta'   => 'decimal:2',
        'last_login_at'     => 'datetime',
        'last_activity_at'  => 'datetime',
        'suspended_until'   => 'datetime',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class);
    }

    public function especialidades()
    {
        return $this->belongsToMany(Especialidad::class, 'doctor_especialidad', 'user_id', 'especialidad_id')
            ->withTimestamps();
    }

    public function facturasComoPaciente()
    {
        return $this->hasMany(Factura::class, 'paciente_id');
    }

    public function facturasComoDoctor()
    {
        return $this->hasMany(Factura::class, 'doctor_id');
    }

    public function hasRole(string $roleName): bool
    {
        return $this->roles()->where('name', $roleName)->exists();
    }

    public function isBlocked(): bool   { return $this->status === 'blocked'; }
    public function isInactive(): bool  { return $this->status === 'inactive'; }
    public function isSuspended(): bool { return $this->suspended_until && now()->lt($this->suspended_until); }
    public function isActive(): bool    { return $this->status === 'active' && !$this->isSuspended(); }

    public function scopeOnlyActive($q)
    {
        return $q->where('status', 'active')
            ->where(function ($qq) {
                $qq->whereNull('suspended_until')
                   ->orWhere('suspended_until', '<=', now());
            });
    }

    public function scopeDoctors($q)
    {
        return $q->whereHas('roles', fn ($r) => $r->where('name', 'doctor'));
    }

    public function scopeRole($q, string $role)
    {
        if (!$role) return $q;
        return $q->whereHas('roles', fn ($r) => $r->where('name', $role));
    }

    public function scopeSearch($q, string $term)
    {
        $term = trim((string) $term);
        if ($term === '') return $q;

        $like = '%'.$term.'%';
        return $q->where(function ($w) use ($like) {
            $w->where('name', 'like', $like)
              ->orWhere('email', 'like', $like)
              ->orWhere('dni', 'like', $like)
              ->orWhere('telefono', 'like', $like);
        });
    }

    public function faceProfile(): HasOne
    {
        return $this->hasOne(FaceProfile::class);
    }

    public function patientFlag(): HasOne
    {
        return $this->hasOne(PatientFlag::class);
    }

    public function featureAccessRequests()
    {
        return $this->hasMany(FeatureAccessRequest::class);
    }
}
