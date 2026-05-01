<?php

namespace App\Services;

use App\Models\Cita;
use App\Models\CitaRecordatorio;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class CitaRecordatorioService
{
    public function __construct(
        protected SiteSettingsService $siteSettings,
        protected WhatsAppService $whatsApp,
    ) {}

    public function pendingDueQuery(): Builder
    {
        $now = $this->now();

        return $this->pendingListQuery()
            ->whereNotNull('recordar_en')
            ->where('recordar_en', '<=', $now);
    }

    public function pendingListQuery(): Builder
    {
        return $this->pendingTrackedQuery()
            ->with([
                'cita.paciente:id,name,telefono',
                'cita.doctor:id,name',
                'cita.especialidad:id,nombre',
                'gestionadoPor:id,name',
            ])
            ->orderBy('recordar_en')
            ->orderBy('cita_inicio_at');
    }

    public function sentListQuery(): Builder
    {
        return CitaRecordatorio::query()
            ->with([
                'cita.paciente:id,name,telefono',
                'cita.doctor:id,name',
                'cita.especialidad:id,nombre',
                'gestionadoPor:id,name',
            ])
            ->where('estado', CitaRecordatorio::ESTADO_ENVIADO)
            ->whereNotNull('enviado_at')
            ->orderByDesc('enviado_at')
            ->orderByDesc('updated_at');
    }

    public function countPendingDue(): int
    {
        return $this->pendingTrackedQuery()->count();
    }

    public function pendingDueStats(): array
    {
        $recordatorios = $this->pendingTrackedQuery()
            ->with([
                'cita:id,paciente_id,estado,activo,fecha,hora',
                'cita.paciente:id,telefono',
            ])
            ->get(['id', 'cita_id']);
        $conTelefono = $recordatorios
            ->filter(fn (CitaRecordatorio $recordatorio) => $this->normalizedPhoneForCita($recordatorio->cita) !== null)
            ->count();

        return [
            'pendientes' => $recordatorios->count(),
            'con_telefono' => $conTelefono,
            'sin_telefono' => $recordatorios->count() - $conTelefono,
        ];
    }

    public function syncForCita(Cita $cita): ?CitaRecordatorio
    {
        if (! $cita->fecha || ! $cita->hora) {
            return null;
        }

        $recordatorio = CitaRecordatorio::firstOrNew([
            'cita_id' => $cita->id,
        ]);

        if (! $recordatorio->exists && ! $this->shouldPersistReminder($cita)) {
            return null;
        }

        $inicio = $cita->inicioProgramado($this->timezone());
        $inicioCambio = $recordatorio->exists
            && $recordatorio->cita_inicio_at
            && ! $recordatorio->cita_inicio_at->equalTo($inicio);

        $recordatorio->cita_inicio_at = $inicio;
        $recordatorio->recordar_en = $this->targetAt($cita);

        if (! $recordatorio->exists) {
            $recordatorio->estado = CitaRecordatorio::ESTADO_PENDIENTE;
        }

        if ($inicioCambio && in_array($recordatorio->estado, [
            CitaRecordatorio::ESTADO_ENVIADO,
            CitaRecordatorio::ESTADO_OMITIDO,
        ], true)) {
            $recordatorio->estado = CitaRecordatorio::ESTADO_PENDIENTE;
            $recordatorio->enviado_at = null;
            $recordatorio->omitido_at = null;
            $recordatorio->gestionado_por = null;
        }

        $recordatorio->save();

        return $recordatorio;
    }

    public function markAsSent(CitaRecordatorio $recordatorio, User $actor): bool
    {
        return $this->markManagedState($recordatorio, CitaRecordatorio::ESTADO_ENVIADO, $actor);
    }

    public function markAsSkipped(CitaRecordatorio $recordatorio, User $actor): bool
    {
        return $this->markManagedState($recordatorio, CitaRecordatorio::ESTADO_OMITIDO, $actor);
    }

    public function canBeManaged(CitaRecordatorio $recordatorio): bool
    {
        $recordatorio->loadMissing('cita');
        $cita = $recordatorio->cita;

        return $cita !== null
            && (bool) $cita->activo
            && in_array((string) $cita->estado, $this->trackedAppointmentStates(), true)
            && $recordatorio->estado === CitaRecordatorio::ESTADO_PENDIENTE
            && $recordatorio->recordar_en !== null
            && $recordatorio->recordar_en->lessThanOrEqualTo($this->now())
            && $recordatorio->cita_inicio_at !== null
            && $recordatorio->cita_inicio_at->greaterThan($this->now());
    }

    public function targetAt(Cita $cita): Carbon
    {
        return $cita->inicioProgramado($this->timezone())
            ->copy()
            ->subDay()
            ->setTime(12, 0, 0);
    }

    public function normalizedPhoneForCita(?Cita $cita): ?string
    {
        if (! $cita) {
            return null;
        }

        return $this->whatsApp->normalizePhone((string) ($cita->paciente?->telefono ?? ''));
    }

    public function whatsappUrlForCita(Cita $cita): ?string
    {
        $phone = $this->normalizedPhoneForCita($cita);
        $digits = $phone ? preg_replace('/\D+/', '', $phone) : null;

        if (! $digits) {
            return null;
        }

        return 'https://wa.me/'.$digits.'?text='.rawurlencode($this->messageForCita($cita));
    }

    public function messageForCita(Cita $cita): string
    {
        $inicio = $cita->inicioProgramado($this->timezone());
        $paciente = trim((string) ($cita->paciente?->name ?? 'Paciente'));
        $doctor = $this->formatDoctorName($cita->doctor?->name);
        $especialidad = trim((string) ($cita->especialidad?->nombre ?? 'Consulta medica'));
        $clinica = trim((string) app(ClinicIdentityService::class)->name());

        $lineas = [
            'Hola, '.$paciente.'.',
            'Le recordamos su cita medica con '.$doctor.' en '.$especialidad.', programada para el '.$inicio->format('d/m/Y').' a las '.$inicio->format('H:i').'.',
            'Por favor, llegue con anticipacion.',
        ];

        if ($clinica !== '') {
            $lineas[] = 'Atentamente,';
            $lineas[] = $clinica;
        }

        return implode("\n", $lineas);
    }

    public function targetDescription(): string
    {
        return 'Desde las 12:00 PM del dia anterior';
    }

    public function syncTrackedAppointments(int $chunkSize = 200): int
    {
        $now = $this->now();
        $sincronizados = 0;

        $this->trackedAppointmentsQuery()
            ->orderBy('id')
            ->chunkById($chunkSize, function ($citas) use ($now, &$sincronizados): void {
                foreach ($citas as $cita) {
                    if ($cita->inicioProgramado($this->timezone())->lessThanOrEqualTo($now)) {
                        continue;
                    }

                    if (! $this->hasMinimumLeadTime($cita)) {
                        continue;
                    }

                    if ($this->syncForCita($cita)) {
                        $sincronizados++;
                    }
                }
            });

        return $sincronizados;
    }

    public function timezone(): string
    {
        return (string) config('app.timezone', 'America/Guayaquil');
    }

    private function now(): Carbon
    {
        return Carbon::now($this->timezone());
    }

    private function shouldPersistReminder(Cita $cita): bool
    {
        return (bool) $cita->activo
            && in_array((string) $cita->estado, $this->trackedAppointmentStates(), true)
            && $this->hasMinimumLeadTime($cita);
    }

    private function pendingTrackedQuery(): Builder
    {
        $now = $this->now();

        return CitaRecordatorio::query()
            ->where('estado', CitaRecordatorio::ESTADO_PENDIENTE)
            ->whereNotNull('cita_inicio_at')
            ->where('cita_inicio_at', '>', $now)
            ->whereHas('cita', function (Builder $query): void {
                $query
                    ->where('activo', true)
                    ->whereIn('estado', $this->trackedAppointmentStates());
            });
    }

    private function trackedAppointmentsQuery(): Builder
    {
        return Cita::query()
            ->where('activo', true)
            ->whereIn('estado', $this->trackedAppointmentStates())
            ->whereNotNull('fecha')
            ->whereNotNull('hora')
            ->whereNotNull('created_at');
    }

    private function trackedAppointmentStates(): array
    {
        return [
            Cita::ESTADO_PENDIENTE,
            Cita::ESTADO_CONFIRMADA,
        ];
    }

    private function hasMinimumLeadTime(Cita $cita): bool
    {
        if (! $cita->created_at) {
            return false;
        }

        $inicio = $cita->inicioProgramado($this->timezone());
        $creadaEn = $cita->created_at instanceof Carbon
            ? $cita->created_at->copy()->setTimezone($this->timezone())
            : Carbon::parse($cita->created_at, $this->timezone());

        return $creadaEn->addDay()->lessThanOrEqualTo($inicio);
    }

    private function applyLeadTimeConstraint(Builder $query): void
    {
        $driver = $query->getConnection()->getDriverName();

        $expression = match ($driver) {
            'mysql', 'mariadb' => 'citas_medicas.created_at <= DATE_SUB(cita_recordatorios.cita_inicio_at, INTERVAL 1 DAY)',
            'pgsql' => "citas_medicas.created_at <= cita_recordatorios.cita_inicio_at - INTERVAL '1 day'",
            'sqlsrv' => 'citas_medicas.created_at <= DATEADD(day, -1, cita_recordatorios.cita_inicio_at)',
            'sqlite' => "datetime(citas_medicas.created_at) <= datetime(cita_recordatorios.cita_inicio_at, '-1 day')",
            default => 'citas_medicas.created_at <= cita_recordatorios.recordar_en',
        };

        $query->whereRaw($expression);
    }

    private function markManagedState(CitaRecordatorio $recordatorio, string $estado, User $actor): bool
    {
        $recordatorio->loadMissing('cita');
        $cita = $recordatorio->cita?->fresh();

        if (! $cita) {
            return false;
        }

        $sincronizado = $this->syncForCita($cita);
        if (! $sincronizado) {
            return false;
        }

        $sincronizado->loadMissing('cita');

        if (! $this->canBeManaged($sincronizado)) {
            return false;
        }

        $sincronizado->estado = $estado;
        $sincronizado->gestionado_por = $actor->id;
        $sincronizado->enviado_at = $estado === CitaRecordatorio::ESTADO_ENVIADO ? $this->now() : null;
        $sincronizado->omitido_at = $estado === CitaRecordatorio::ESTADO_OMITIDO ? $this->now() : null;
        $sincronizado->save();

        return true;
    }

    private function formatDoctorName(?string $name): string
    {
        $name = trim((string) $name);

        if ($name === '') {
            return 'el doctor asignado';
        }

        if (preg_match('/^dr(a)?\\.?/iu', $name) === 1) {
            return $name;
        }

        return 'el Dr. '.$name;
    }
}
