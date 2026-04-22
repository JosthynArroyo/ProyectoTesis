<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateClinicalRecordRequest;
use App\Models\Cita;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use App\Models\User;
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

        $sort = (string) $request->query('sort', 'recientes');
        $allowedSorts = ['recientes', 'alfabetico', 'laboratorios'];
        if (! in_array($sort, $allowedSorts, true)) {
            $sort = 'recientes';
        }

        $legacyPendingCounts = LaboratorioOrden::query()
            ->join('citas_medicas', 'citas_medicas.id', '=', 'laboratorio_ordenes.cita_id')
            ->where('citas_medicas.doctor_id', $doctor->id)
            ->whereIn('laboratorio_ordenes.estado', [
                LaboratorioOrden::ESTADO_ORDEN_CREADA,
                LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
                LaboratorioOrden::ESTADO_MUESTRA_TOMADA,
            ])
            ->selectRaw('citas_medicas.paciente_id as patient_id, COUNT(*) as total')
            ->groupBy('citas_medicas.paciente_id');

        $selfServicePendingCounts = LabOrder::query()
            ->where('doctor_id', $doctor->id)
            ->whereIn('status', [
                LabOrder::STATUS_PENDIENTE_TOMA,
                LabOrder::STATUS_MUESTRA_TOMADA,
                LabOrder::STATUS_EN_ANALISIS,
            ])
            ->selectRaw('patient_id, COUNT(*) as total')
            ->groupBy('patient_id');

        $patientsQuery = User::query()
            ->select([
                'users.id',
                'users.name',
                'users.email',
                'users.telefono',
                'users.dni',
                'users.fecha_nacimiento',
            ])
            ->whereIn('users.id', $this->doctorPatientAppointmentQuery((int) $doctor->id)
                ->select('paciente_id')
                ->distinct())
            ->leftJoinSub($legacyPendingCounts, 'legacy_pending_labs', function ($join) {
                $join->on('legacy_pending_labs.patient_id', '=', 'users.id');
            })
            ->leftJoinSub($selfServicePendingCounts, 'self_pending_labs', function ($join) {
                $join->on('self_pending_labs.patient_id', '=', 'users.id');
            })
            ->selectRaw('COALESCE(legacy_pending_labs.total, 0) as legacy_pending_labs_count')
            ->selectRaw('COALESCE(self_pending_labs.total, 0) as self_pending_labs_count');

        $this->applyPatientSort($patientsQuery, (int) $doctor->id, $sort);

        $patients = $patientsQuery
            ->paginate(12)
            ->withQueryString();

        $patientIds = $patients->getCollection()->pluck('id')->map(fn ($id) => (int) $id)->all();

        $latestVisits = $patientIds === []
            ? collect()
            : $this->doctorPatientAppointmentQuery((int) $doctor->id)
                ->with('especialidad:id,nombre')
                ->whereIn('paciente_id', $patientIds)
                ->orderByDesc('fecha')
                ->orderByDesc('hora')
                ->get([
                    'id',
                    'paciente_id',
                    'doctor_id',
                    'especialidad_id',
                    'fecha',
                    'hora',
                    'estado',
                ])
                ->groupBy('paciente_id')
                ->map(fn ($items) => $items->first());

        $patientRows = $patients->getCollection()->map(function (User $patient) use ($latestVisits) {
            $lastVisit = $latestVisits->get($patient->id);
            $pendingLabsCount = (int) $patient->legacy_pending_labs_count + (int) $patient->self_pending_labs_count;
            $lastVisitAt = $lastVisit?->inicioProgramado(config('app.timezone', 'America/Guayaquil'));
            $lastVisitStatus = $this->mapVisitStatus($lastVisit?->estado);
            $specialtyName = trim((string) optional($lastVisit?->especialidad)->nombre);

            return [
                'id' => $patient->id,
                'name' => (string) $patient->name,
                'code' => 'PAC-'.str_pad((string) $patient->id, 5, '0', STR_PAD_LEFT),
                'initials' => $this->initials((string) $patient->name),
                'avatar_tone' => $this->avatarTone($patient->id),
                'age' => $patient->fecha_nacimiento ? Carbon::parse($patient->fecha_nacimiento)->age : null,
                'email' => $patient->email,
                'phone' => $patient->telefono,
                'last_visit_at' => $lastVisitAt,
                'last_visit_display' => $lastVisitAt?->format('d/m/Y') ?? 'Sin consultas registradas',
                'last_visit_status_label' => $lastVisitStatus['label'],
                'last_visit_status_tone' => $lastVisitStatus['tone'],
                'last_visit_context' => $pendingLabsCount > 0
                    ? $pendingLabsCount.' laboratorio'.($pendingLabsCount === 1 ? ' pendiente' : 's pendientes')
                    : ($specialtyName !== '' ? $specialtyName : 'Seguimiento clínico'),
                'last_visit_context_tone' => $pendingLabsCount > 0 ? 'warning' : 'neutral',
                'pending_labs_count' => $pendingLabsCount,
                'record_url' => route('doctor.pacientes.historial', $patient),
                'lab_url' => route('doctor.laboratorio.create', ['paciente_id' => $patient->id]),
                'last_visit_timestamp' => $lastVisitAt?->timestamp ?? 0,
            ];
        });

        $patients->setCollection($patientRows->values());

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
        return $this->doctorPatientAppointmentQuery($doctorId)
            ->select('citas_medicas.'.$column)
            ->whereColumn('citas_medicas.paciente_id', 'users.id')
            ->orderByDesc('citas_medicas.fecha')
            ->orderByDesc('citas_medicas.hora')
            ->limit(1);
    }

    public function show(User $paciente, ClinicalRecordService $clinicalRecords)
    {
        $doctor = Auth::user();

        if (! $doctor || ! $clinicalRecords->canView($doctor, $paciente)) {
            abort(403);
        }

        $record = $clinicalRecords->ensureForPatient($paciente, $doctor->id);
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

        $record = $clinicalRecords->ensureForPatient($paciente, $doctor->id);
        $clinicalRecords->syncMasterData($record, $request->validated(), $doctor->id);

        return redirect()
            ->route('doctor.pacientes.historial', $paciente)
            ->with('success', 'Expediente clínico actualizado correctamente.');
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
