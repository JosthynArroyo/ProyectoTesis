<?php

namespace App\Models;

use App\Notifications\CustomResetPasswordNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Log;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    private const DASHBOARD_PATHS_BY_ROLE = [
        'superadmin' => '/superadmin/dashboard',
        'administrador' => '/admin/dashboard',
        'doctor' => '/doctor/dashboard',
        'laboratorio' => '/laboratorio/dashboard',
        'paciente' => '/paciente/dashboard',
    ];

    private const DASHBOARD_ROUTE_NAMES_BY_ROLE = [
        'superadmin' => 'superadmin.dashboard',
        'administrador' => 'admin.dashboard',
        'doctor' => 'doctor.dashboard',
        'laboratorio' => 'laboratorio.dashboard',
        'paciente' => 'paciente.dashboard',
    ];

    public const STATUS_ACTIVE = 'active';

    public const STATUS_INACTIVE = 'inactive';

    public const STATUS_BLOCKED = 'blocked';

    protected $fillable = [
        'name',
        'email',
        'password',
        'active',
        'telefono',
        'tipo_documento',
        'nacionalidad',
        'dni',
        'direccion',
        'fecha_nacimiento',
        'sexo',
        'avatar',
        'theme_preference',
        'precio_consulta',
        'moneda',
        'status',
        'last_login_at',
        'last_activity_at',
        'suspended_until',
        'deactivation_reason',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected ?array $resolvedRoleNames = null;

    public function setDniAttribute($value): void
    {
        $tipo = $this->attributes['tipo_documento'] ?? 'cedula';
        $this->attributes['dni'] = \App\Services\IdentityDocumentService::normalize($value, $tipo);
    }

    protected $casts = [
        'active' => 'boolean',
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'fecha_nacimiento' => 'date',
        'theme_preference' => 'string',
        'precio_consulta' => 'decimal:2',
        'last_login_at' => 'datetime',
        'last_activity_at' => 'datetime',
        'suspended_until' => 'datetime',
        'must_change_password' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::saved(function (User $user): void {
            if (filled($user->dni)) {
                app(\App\Services\IdentityDocumentService::class)->sync(
                    $user,
                    $user->dni,
                    $user->tipo_documento ?? 'cedula',
                    $user->nacionalidad
                );
            }
        });

        static::saving(function (self $user): void {
            $statusDirty = $user->isDirty('status');
            $activeDirty = $user->isDirty('active');

            $status = $statusDirty
                ? $user->normalizeStatus($user->status)
                : (string) ($user->status ?? '');

            if ($status === '') {
                if ($activeDirty) {
                    $status = $user->active ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
                } else {
                    $status = self::STATUS_ACTIVE;
                }
            } elseif ($activeDirty && ! $statusDirty) {
                $status = $user->active ? self::STATUS_ACTIVE : self::STATUS_INACTIVE;
            }

            $user->status = $status;
            $user->active = $status === self::STATUS_ACTIVE;

            if ($status !== self::STATUS_ACTIVE && ! $user->isDirty('suspended_until')) {
                $user->suspended_until = null;
            }
        });

        static::updating(function (self $user): void {
            if ($user->isDirty('status') && $user->status !== self::STATUS_ACTIVE && $user->hasRole('superadmin')) {
                $activeCount = self::whereHas('roles', function ($q) {
                    $q->where('name', 'superadmin');
                })->where('status', self::STATUS_ACTIVE)->where('id', '!=', $user->id)->count();

                if ($activeCount === 0) {
                    \Illuminate\Support\Facades\Log::warning('AUDIT_REJECTED: Attempt to block/deactivate the last active superadmin', [
                        'action' => 'prevent_last_superadmin_deactivation',
                        'timestamp' => now()->toIso8601String(),
                        'channel' => app()->runningInConsole() ? 'console' : 'web',
                        'actor' => auth()->id() ?? 'system',
                        'target_user_id' => $user->id,
                        'reason' => 'No se puede bloquear o desactivar el único superadministrador activo.',
                    ]);
                    throw new \RuntimeException('No se puede desactivar o bloquear el único superadministrador activo del sistema.');
                }
            }
        });

        static::deleting(function (self $user): void {
            if ($user->hasRole('superadmin')) {
                if (auth()->check() && auth()->id() === $user->id) {
                    \Illuminate\Support\Facades\Log::warning('AUDIT_REJECTED: Superadmin tried to delete themselves', [
                        'action' => 'prevent_self_deletion',
                        'timestamp' => now()->toIso8601String(),
                        'channel' => 'web',
                        'actor' => auth()->id(),
                        'target_user_id' => $user->id,
                        'reason' => 'No puedes eliminarte a ti mismo.',
                    ]);
                    throw new \RuntimeException('No puedes eliminarte a ti mismo.');
                }

                $activeCount = self::whereHas('roles', function ($q) {
                    $q->where('name', 'superadmin');
                })->where('status', self::STATUS_ACTIVE)->count();

                if ($activeCount <= 1 && $user->status === self::STATUS_ACTIVE) {
                    \Illuminate\Support\Facades\Log::warning('AUDIT_REJECTED: Attempt to delete the last active superadmin', [
                        'action' => 'prevent_last_superadmin_deletion',
                        'timestamp' => now()->toIso8601String(),
                        'channel' => app()->runningInConsole() ? 'console' : 'web',
                        'actor' => auth()->id() ?? 'system',
                        'target_user_id' => $user->id,
                        'reason' => 'No se puede eliminar el único superadministrador activo.',
                    ]);
                    throw new \RuntimeException('No se puede eliminar el único superadministrador activo del sistema.');
                }
            }
        });
    }

    public function avatarUrl(string $variant = 'thumb'): string
    {
        return app(\App\Services\ProfileAvatarService::class)->avatarUrl($this, $variant);
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

    public function pagos(): HasMany
    {
        return $this->hasMany(Pago::class, 'paciente_id');
    }

    public function pagosGestionados(): HasMany
    {
        return $this->hasMany(Pago::class, 'aprobado_por');
    }

    public function pagosCreados(): HasMany
    {
        return $this->hasMany(Pago::class, 'creado_por');
    }

    public function recibosEmitidos(): HasMany
    {
        return $this->hasMany(PaymentReceipt::class, 'emitido_por');
    }

    public function pedidosLaboratorioComoPaciente(): HasMany
    {
        return $this->hasMany(PedidoLaboratorio::class, 'paciente_id');
    }

    public function pedidosLaboratorioComoDoctor(): HasMany
    {
        return $this->hasMany(PedidoLaboratorio::class, 'doctor_id');
    }

    public function hasRole(string $roleName): bool
    {
        $roleName = trim($roleName);
        if ($roleName === '') {
            return false;
        }

        return in_array($roleName, $this->resolvedRoleNames(), true);
    }

    public function primaryRoleName(): ?string
    {
        $roleNames = $this->resolvedRoleNames();

        foreach (array_keys(self::DASHBOARD_PATHS_BY_ROLE) as $roleName) {
            if (in_array($roleName, $roleNames, true)) {
                return $roleName;
            }
        }

        return null;
    }

    public function dashboardPath(): string
    {
        $primaryRole = $this->primaryRoleName();

        return $primaryRole ? self::DASHBOARD_PATHS_BY_ROLE[$primaryRole] : '/';
    }

    public function dashboardRouteName(): ?string
    {
        $primaryRole = $this->primaryRoleName();

        return $primaryRole ? self::DASHBOARD_ROUTE_NAMES_BY_ROLE[$primaryRole] : null;
    }

    public function hasPendingPaymentBlocks(): bool
    {
        return $this->pagos()->conBloqueoAgendamiento()->exists();
    }

    public function isBlocked(): bool
    {
        return $this->status === self::STATUS_BLOCKED;
    }

    public function isInactive(): bool
    {
        return $this->status === self::STATUS_INACTIVE;
    }

    public function isSuspended(): bool
    {
        return $this->suspended_until && now()->lt($this->suspended_until);
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE && ! $this->isSuspended();
    }

    public function scopeOnlyActive($q)
    {
        return $q->where('status', self::STATUS_ACTIVE)
            ->where(function ($qq) {
                $qq->whereNull('suspended_until')
                    ->orWhere('suspended_until', '<=', now());
            });
    }

    protected function normalizeStatus(?string $status): string
    {
        return match (trim(strtolower((string) $status))) {
            self::STATUS_INACTIVE => self::STATUS_INACTIVE,
            self::STATUS_BLOCKED => self::STATUS_BLOCKED,
            default => self::STATUS_ACTIVE,
        };
    }

    public function scopeDoctors($q)
    {
        return $q->whereHas('roles', fn ($r) => $r->where('name', 'doctor'));
    }

    public function scopeRole($q, string $role)
    {
        if (! $role) {
            return $q;
        }

        return $q->whereHas('roles', fn ($r) => $r->where('name', $role));
    }

    public function scopeSearch($q, string $term)
    {
        $term = trim(mb_substr((string) $term, 0, 100));
        if ($term === '') {
            return $q;
        }

        $like = '%'.$term.'%';

        return $q->where(function ($w) use ($like) {
            $w->where('name', 'like', $like)
                ->orWhere('email', 'like', $like)
                ->orWhere('dni', 'like', $like)
                ->orWhere('telefono', 'like', $like);
        });
    }

    public function patientFlag(): HasOne
    {
        return $this->hasOne(PatientFlag::class);
    }

    public function clinicalRecord(): HasOne
    {
        return $this->hasOne(ClinicalRecord::class, 'patient_id');
    }

    public function dependientes(): HasMany
    {
        return $this->hasMany(Dependiente::class, 'user_id')
            ->where('activo', true)
            ->orderBy('nombre');
    }

    public function featureAccessRequests()
    {
        return $this->hasMany(FeatureAccessRequest::class);
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

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomResetPasswordNotification($token));
    }

    /**
     * @return list<string>
     */
    protected function resolvedRoleNames(): array
    {
        if ($this->resolvedRoleNames !== null) {
            return $this->resolvedRoleNames;
        }

        if ($this->relationLoaded('roles')) {
            $this->resolvedRoleNames = $this->roles
                ->pluck('name')
                ->filter()
                ->map(fn ($name) => trim((string) $name))
                ->filter()
                ->values()
                ->all();

            return $this->resolvedRoleNames;
        }

        $startedAt = microtime(true);
        $this->resolvedRoleNames = $this->roles()
            ->orderBy('name')
            ->pluck('roles.name')
            ->filter()
            ->map(fn ($name) => trim((string) $name))
            ->filter()
            ->values()
            ->all();

        return $this->resolvedRoleNames;
    }
}
