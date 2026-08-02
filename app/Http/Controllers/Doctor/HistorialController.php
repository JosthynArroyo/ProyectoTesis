<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClinicalRecordRequest;
use App\Models\Cita;
use App\Models\LaboratorioOrden;
use App\Models\LabOrder;
use App\Models\User;
use App\Services\ClinicalRecordPdfService;
use App\Services\ClinicalRecordService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class HistorialController extends Controller
{
    public function index(Request $request)
    {
        $doctor = Auth::user();

        abort_unless($doctor?->hasRole('doctor'), 403);

        $sort = $this->normalizeSort($request->query('sort', 'recientes'));

        // 1. Obtener pares únicos de (paciente_id, dependiente_id) de las citas activas del doctor
        $distinctPairs = Cita::query()
            ->where('doctor_id', $doctor->id)
            ->where('activo', true)
            ->whereNotNull('paciente_id')
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA])
            ->select(['paciente_id', 'dependiente_id'])
            ->distinct()
            ->get();

        // 2. Extraer IDs únicos para consultas masivas (bulk load)
        $pacienteIds = $distinctPairs->pluck('paciente_id')->unique()->all();
        $dependienteIds = $distinctPairs->pluck('dependiente_id')->filter()->unique()->all();

        // 3. Consultar los titulares y dependientes vinculados
        $users = User::whereIn('id', $pacienteIds)->get()->keyBy('id');
        $dependientes = \App\Models\Dependiente::with('responsable')->whereIn('id', $dependienteIds)->get()->keyBy('id');

        // 4. Obtener todas las citas para estos pares en una única consulta
        $allAppointments = Cita::query()
            ->where('doctor_id', $doctor->id)
            ->where('activo', true)
            ->whereNotIn('estado', [Cita::ESTADO_CANCELADA])
            ->with('especialidad:id,nombre')
            ->get(['id', 'paciente_id', 'dependiente_id', 'especialidad_id', 'fecha', 'hora', 'estado']);

        // 5. Obtener órdenes de laboratorio pendientes de forma masiva
        $legacyPendingCounts = LaboratorioOrden::query()
            ->join('citas_medicas', 'citas_medicas.id', '=', 'laboratorio_ordenes.cita_id')
            ->where('citas_medicas.doctor_id', $doctor->id)
            ->whereIn('laboratorio_ordenes.estado', [
                LaboratorioOrden::ESTADO_ORDEN_CREADA,
                LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
                LaboratorioOrden::ESTADO_MUESTRA_TOMADA,
            ])
            ->get(['citas_medicas.paciente_id', 'citas_medicas.dependiente_id']);

        $selfServicePendingCounts = LabOrder::query()
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', [
                LabOrder::STATUS_PENDIENTE_TOMA,
                LabOrder::STATUS_MUESTRA_TOMADA,
                LabOrder::STATUS_EN_ANALISIS,
            ])
            ->get(['patient_id']);

        // Helper para convertir fecha + hora en Carbon datetime
        $dt = function ($c) {
            if (!$c) return Carbon::parse('1970-01-01');
            $d = Carbon::parse($c->fecha, 'America/Guayaquil');
            if (! empty($c->hora)) {
                $hhmm = substr($c->hora, 0, 5);
                [$H,$M] = array_map('intval', explode(':', $hhmm));
                $d->setTime($H, $M, 0);
            }
            return $d;
        };

        // 6. Mapear y construir la lista unificada
        $allPatients = collect();

        foreach ($distinctPairs as $pair) {
            $isDependiente = !empty($pair->dependiente_id);
            
            if ($isDependiente) {
                $dep = $dependientes->get($pair->dependiente_id);
                if (!$dep) continue;

                $patientName = $dep->nombre;
                $patientDni = $dep->dni;
                $patientDob = $dep->fecha_nacimiento;
                $patientEmail = $dep->responsable?->email;
                $patientPhone = $dep->responsable?->telefono;
                
                $code = 'DEP-'.str_pad((string) $dep->id, 5, '0', STR_PAD_LEFT);
                $recordUrl = route('doctor.pacientes.historial', $pair->paciente_id) . '?dependiente_id=' . $dep->id;
                $initials = $this->initials((string) $patientName);
                $avatarTone = $this->avatarTone($dep->id);
                $age = $patientDob ? Carbon::parse($patientDob)->age : null;
                $avatarId = $dep->id;
                $avatarThumbUrl = $dep->avatar_thumb_url;
            } else {
                $user = $users->get($pair->paciente_id);
                if (!$user) continue;

                $patientName = $user->name;
                $patientDni = $user->dni;
                $patientDob = $user->fecha_nacimiento;
                $patientEmail = $user->email;
                $patientPhone = $user->telefono;

                $code = 'PAC-'.str_pad((string) $user->id, 5, '0', STR_PAD_LEFT);
                $recordUrl = route('doctor.pacientes.historial', $user);
                $initials = $this->initials((string) $patientName);
                $avatarTone = $this->avatarTone($user->id);
                $age = $patientDob ? Carbon::parse($patientDob)->age : null;
                $avatarId = $user->id;
                $avatarThumbUrl = $user->avatar_thumb_url;
            }

            // Filtrar citas correspondientes a este paciente/dependiente
            $appointments = $allAppointments->filter(function ($c) use ($pair) {
                return $c->paciente_id == $pair->paciente_id && $c->dependiente_id == $pair->dependiente_id;
            });

            $lastVisit = $appointments
                ->sortByDesc(fn ($c) => $dt($c)->timestamp)
                ->first();

            $lastVisitAt = $lastVisit?->inicioProgramado(config('app.timezone', 'America/Guayaquil'));
            $lastVisitStatus = $this->mapVisitStatus($lastVisit?->estado);
            $specialtyName = trim((string) optional($lastVisit?->especialidad)->nombre);

            // Contar laboratorios pendientes
            $legacyCount = $legacyPendingCounts->filter(function ($lo) use ($pair) {
                return $lo->paciente_id == $pair->paciente_id && $lo->dependiente_id == $pair->dependiente_id;
            })->count();

            $selfServiceCount = 0;
            if (!$isDependiente) {
                $selfServiceCount = $selfServicePendingCounts->filter(function ($so) use ($pair) {
                    return $so->patient_id == $pair->paciente_id;
                })->count();
            }

            $pendingLabsCount = $legacyCount + $selfServiceCount;

            $allPatients->push([
                'id' => $avatarId,
                'name' => (string) $patientName,
                'code' => $code,
                'initials' => $initials,
                'avatar_tone' => $avatarTone,
                'avatar_thumb_url' => $avatarThumbUrl,
                'age' => $age,
                'email' => $patientEmail,
                'phone' => $patientPhone,
                'last_visit_at' => $lastVisitAt,
                'last_visit_display' => $lastVisitAt?->format('d/m/Y') ?? 'Sin consultas registradas',
                'last_visit_status_label' => $lastVisitStatus['label'],
                'last_visit_status_tone' => $lastVisitStatus['tone'],
                'last_visit_context' => $pendingLabsCount > 0
                    ? $pendingLabsCount.' laboratorio'.($pendingLabsCount === 1 ? ' pendiente' : 's pendientes')
                    : ($specialtyName !== '' ? $specialtyName : 'Seguimiento clínico'),
                'last_visit_context_tone' => $pendingLabsCount > 0 ? 'warning' : 'neutral',
                'pending_labs_count' => $pendingLabsCount,
                'record_url' => $recordUrl,
                'last_visit_timestamp' => $lastVisitAt?->timestamp ?? 0,
                'latest_visit' => $lastVisit,
            ]);
        }

        // 7. Aplicar el ordenamiento sobre la colección mapeada
        if ($sort === 'alfabetico') {
            $allPatients = $allPatients->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE);
        } elseif ($sort === 'laboratorios') {
            $allPatients = $allPatients->sort(function ($a, $b) use ($dt) {
                if ($a['pending_labs_count'] != $b['pending_labs_count']) {
                    return $b['pending_labs_count'] <=> $a['pending_labs_count'];
                }
                $dateA = $a['last_visit_timestamp'];
                $dateB = $b['last_visit_timestamp'];
                if ($dateA == $dateB) {
                    return strcasecmp($a['name'], $b['name']);
                }
                return $dateB <=> $dateA;
            });
        } else {
            // recientes
            $allPatients = $allPatients->sort(function ($a, $b) {
                $dateA = $a['last_visit_timestamp'];
                $dateB = $b['last_visit_timestamp'];
                if ($dateA == $dateB) {
                    return strcasecmp($a['name'], $b['name']);
                }
                return $dateB <=> $dateA;
            });
        }

        // 8. Paginación manual de la colección
        $perPage = 12;
        $currentPage = \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPage();
        $currentPageItems = $allPatients->slice(($currentPage - 1) * $perPage, $perPage)->values();

        $patients = new \Illuminate\Pagination\LengthAwarePaginator(
            $currentPageItems,
            $allPatients->count(),
            $perPage,
            $currentPage,
            ['path' => \Illuminate\Pagination\LengthAwarePaginator::resolveCurrentPath()]
        );
        $patients->withQueryString();

        // 9. Totales para las tarjetas superiores de métricas
        $today = now(config('app.timezone', 'America/Guayaquil'))->toDateString();
        $todayVisits = Cita::query()
            ->where('doctor_id', $doctor->id)
            ->whereDate('fecha', $today)
            ->whereIn('estado', [
                Cita::ESTADO_PENDIENTE,
                Cita::ESTADO_CONFIRMADA,
                Cita::ESTADO_REALIZADA,
            ])
            ->count();

        $legacyPendingTotal = LaboratorioOrden::query()
            ->join('citas_medicas', 'citas_medicas.id', '=', 'laboratorio_ordenes.cita_id')
            ->where('citas_medicas.doctor_id', $doctor->id)
            ->whereIn('laboratorio_ordenes.estado', [
                LaboratorioOrden::ESTADO_ORDEN_CREADA,
                LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
                LaboratorioOrden::ESTADO_MUESTRA_TOMADA,
            ])
            ->count();

        $selfServicePendingTotal = LabOrder::query()
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', [
                LabOrder::STATUS_PENDIENTE_TOMA,
                LabOrder::STATUS_MUESTRA_TOMADA,
                LabOrder::STATUS_EN_ANALISIS,
            ])
            ->count();

        return view('doctor.pacientes.index', [
            'patients' => $patients,
            'sort' => $sort,
            'stats' => [
                'total_active' => $patients->total(),
                'pending_labs' => (int) $legacyPendingTotal + (int) $selfServicePendingTotal,
                'today_visits' => $todayVisits,
            ],
        ]);
    }

    private function doctorPatientAppointmentQuery(int $doctorId)
    {
        return Cita::query()
            ->where('citas_medicas.doctor_id', $doctorId)
            ->where('citas_medicas.activo', true)
            ->whereNotNull('citas_medicas.paciente_id')
            ->whereNotIn('citas_medicas.estado', [Cita::ESTADO_CANCELADA]);
    }

    private function applyPatientSort($query, int $doctorId, string $sort): void
    {
        $lastDate = $this->latestPatientAppointmentValue($doctorId, 'fecha');
        $lastTime = $this->latestPatientAppointmentValue($doctorId, 'hora');

        match ($sort) {
            'alfabetico' => $query->orderBy('users.name'),
            'laboratorios' => $query
                // Static SQL fragment only; do not accept sort expressions from request.
                ->orderByRaw('(COALESCE(legacy_pending_labs.total, 0) + COALESCE(self_pending_labs.total, 0)) DESC')
                ->orderByDesc($lastDate)
                ->orderByDesc($lastTime)
                ->orderBy('users.name'),
            default => $query
                ->orderByDesc($lastDate)
                ->orderByDesc($lastTime)
                ->orderBy('users.name'),
        };
    }

    private function latestPatientAppointmentValue(int $doctorId, string $column)
    {
        $column = in_array($column, ['fecha', 'hora'], true) ? $column : 'fecha';

        return $this->doctorPatientAppointmentQuery($doctorId)
            // Column name is restricted internally; never forward a request value here.
            ->select('citas_medicas.'.$column)
            ->whereColumn('citas_medicas.paciente_id', 'users.id')
            ->orderByDesc('citas_medicas.fecha')
            ->orderByDesc('citas_medicas.hora')
            ->limit(1);
    }

    private function normalizeSort(mixed $value): string
    {
        $sort = trim((string) $value);
        $allowedSorts = ['recientes', 'alfabetico', 'laboratorios'];

        return in_array($sort, $allowedSorts, true) ? $sort : 'recientes';
    }

    public function show(User $paciente, Request $request, ClinicalRecordService $clinicalRecords)
    {
        $doctor = Auth::user();

        if (! $doctor || ! $clinicalRecords->canView($doctor, $paciente)) {
            abort(403);
        }

        $dependienteId = $this->resolveDependienteId($paciente, $request);
        $record = $clinicalRecords->ensureForPatient($paciente, $doctor->id, $dependienteId);
        $viewData = $clinicalRecords->buildRecordViewData($record);

        return view('doctor.paciente-historial', [
            'paciente' => $paciente,
        ] + $viewData);
    }

    public function update(UpdateClinicalRecordRequest $request, User $paciente, ClinicalRecordService $clinicalRecords)
    {
        $doctor = Auth::user();

        if (! $doctor || ! $clinicalRecords->canView($doctor, $paciente)) {
            abort(403);
        }

        $dependienteId = $this->resolveDependienteId($paciente, $request);
        $record = $clinicalRecords->ensureForPatient($paciente, $doctor->id, $dependienteId);
        $clinicalRecords->syncMasterData($record, $request->validated(), $doctor->id);

        $redirectUrl = route('doctor.pacientes.historial', $paciente);
        if ($dependienteId) {
            $redirectUrl .= '?dependiente_id=' . $dependienteId;
        }

        return redirect($redirectUrl)
            ->with('success', 'Expediente clínico actualizado correctamente.');
    }

    public function exportPdf(User $paciente, Request $request, ClinicalRecordService $clinicalRecords, ClinicalRecordPdfService $pdfService)
    {
        $doctor = Auth::user();

        if (! $doctor || ! $clinicalRecords->canView($doctor, $paciente)) {
            abort(403);
        }

        $dependienteId = $this->resolveDependienteId($paciente, $request);
        $record = $clinicalRecords->ensureForPatient($paciente, $doctor->id, $dependienteId);
        $viewData = $clinicalRecords->buildRecordViewData($record);
        $pdfContent = $pdfService->generate($record, $viewData);

        $patientName = $dependienteId && $record->dependiente ? $record->dependiente->nombre : $paciente->name;
        $filename = 'expediente_clinico_' . Str::slug($patientName, '_') . '_' . now()->format('Ymd') . '.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function resolveDependienteId(User $paciente, Request $request): ?int
    {
        $dependienteId = $this->normalizePositiveInt($request->query('dependiente_id'));

        if (! $dependienteId) {
            return null;
        }

        abort_unless($paciente->dependientes()->whereKey($dependienteId)->exists(), 404);

        return $dependienteId;
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $normalized === false ? null : (int) $normalized;
    }

    private function initials(string $name): string
    {
        $segments = preg_split('/\s+/', trim($name)) ?: [];

        return collect($segments)
            ->filter()
            ->take(2)
            ->map(fn ($segment) => Str::upper(Str::substr($segment, 0, 1)))
            ->implode('');
    }

    private function avatarTone(int $patientId): string
    {
        $tones = ['blue', 'amber', 'emerald', 'violet', 'rose', 'cyan'];

        return $tones[$patientId % count($tones)];
    }

    private function mapVisitStatus(?string $status): array
    {
        return match ($status) {
            Cita::ESTADO_REALIZADA => ['label' => 'Atendida', 'tone' => 'success'],
            Cita::ESTADO_CONFIRMADA => ['label' => 'Confirmada', 'tone' => 'info'],
            Cita::ESTADO_NO_SE_PRESENTO => ['label' => 'No se presentó', 'tone' => 'danger'],
            default => ['label' => 'Pendiente', 'tone' => 'warning'],
        };
    }
}
