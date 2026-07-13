<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotaSoap;
use App\Models\User;
use App\Services\ClinicalRecordPdfService;
use App\Services\ClinicalRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class HistorialController extends Controller
{
    public function index(Request $request)
    {
        $q = $this->normalizeSearchTerm($request->get('q', ''));
        $pacienteId = $this->normalizePositiveInt($request->get('paciente_id'));

        $query = NotaSoap::query()
            ->select('notas_soap.*')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->join('users as pacientes', 'citas_medicas.paciente_id', '=', 'pacientes.id')
            ->leftJoin('dependientes', 'citas_medicas.dependiente_id', '=', 'dependientes.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->when($pacienteId, fn ($qq) => $qq->where('citas_medicas.paciente_id', $pacienteId))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->where(function ($w) use ($like) {
                    $w->where('pacientes.name', 'like', $like)
                        ->orWhere('pacientes.dni', 'like', $like)
                        ->orWhere('pacientes.email', 'like', $like)
                        ->orWhere('dependientes.nombre', 'like', $like)
                        ->orWhere('dependientes.dni', 'like', $like);
                });
            })
            ->orderBy('citas_medicas.fecha', 'desc')
            ->orderBy('citas_medicas.hora', 'desc')
            ->with(['cita.paciente', 'cita.dependiente', 'cita.doctor', 'cita.especialidad']);

        $notas = $query->paginate(15)->withQueryString();

        return view('admin.historial.index', [
            'notas' => $notas,
            'q' => $q,
            'pacienteId' => $pacienteId,
        ]);
    }

    private function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }

    private function normalizePositiveInt(mixed $value): ?int
    {
        $normalized = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return $normalized === false ? null : (int) $normalized;
    }

    private function normalizeDependienteId(?string $value): ?int
    {
        return $this->normalizePositiveInt($value);
    }

    public function paciente(User $paciente)
    {
        $dependienteId = $this->normalizePositiveInt(request()->get('dependiente_id'));

        $notas = NotaSoap::query()
            ->select('notas_soap.*')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->where('citas_medicas.paciente_id', $paciente->id)
            ->when($dependienteId, function ($qq) use ($dependienteId) {
                return $qq->where('citas_medicas.dependiente_id', $dependienteId);
            }, function ($qq) {
                return $qq->whereNull('citas_medicas.dependiente_id');
            })
            ->orderBy('citas_medicas.fecha', 'desc')
            ->orderBy('citas_medicas.hora', 'desc')
            ->with(['cita.doctor', 'cita.especialidad', 'cita.paciente', 'cita.dependiente'])
            ->paginate(15)->withQueryString();

        $dependiente = null;
        if ($dependienteId) {
            $dependiente = $paciente->dependientes()->find($dependienteId);
        }

        return view('admin.historial.paciente', [
            'paciente' => $paciente,
            'dependiente' => $dependiente,
            'notas' => $notas,
        ]);
    }

    public function show(NotaSoap $nota, ClinicalRecordService $clinicalRecords)
    {
        $nota->load(['cita.paciente', 'cita.doctor', 'cita.especialidad', 'diagnosticos', 'enmiendas.autor']);

        if ($nota->estado !== NotaSoap::ESTADO_FIRMADA) {
            abort(404);
        }

        $paciente = $nota->cita?->paciente;

        if (! $paciente) {
            abort(404);
        }

        $dependienteId = $nota->cita?->dependiente_id ? (int) $nota->cita->dependiente_id : null;
        if ($dependienteId) {
            abort_unless($paciente->dependientes()->whereKey($dependienteId)->exists(), 404);
        }

        $record = $clinicalRecords->ensureForPatient($paciente, auth()->id(), $dependienteId);
        $clinicalRecords->attachExistingArtifacts($nota->cita, $record);
        $viewData = $clinicalRecords->buildRecordViewData($record);

        return view('doctor.paciente-historial', [
            'paciente' => $paciente,
            'pageLayout' => 'layouts.admin',
            'pageActiveSidebar' => 'historial',
            'pageTitle' => 'Expediente clínico del paciente',
            'pageHeaderTitle' => 'Expediente clínico del paciente',
            'pageHeaderSubtitle' => 'Resumen longitudinal del paciente en modo de solo lectura',
            'recordEditable' => false,
            'allowActionLinks' => false,
            'backUrl' => route('admin.historial.index'),
            'backLabel' => 'Volver al historial',
            'noteRouteName' => null,
            'prescriptionRouteName' => null,
        ] + $viewData);
    }

    public function exportPdf(User $paciente, ClinicalRecordService $clinicalRecords, ClinicalRecordPdfService $pdfService)
    {
        $dependienteId = $this->normalizeDependienteId(request()->query('dependiente_id'));

        if ($dependienteId) {
            abort_unless($paciente->dependientes()->whereKey($dependienteId)->exists(), 404);
        }

        $record = $clinicalRecords->ensureForPatient($paciente, auth()->id(), $dependienteId);
        $viewData = $clinicalRecords->buildRecordViewData($record);
        $pdfContent = $pdfService->generate($record, $viewData);

        $subjectName = $dependienteId && $record->dependiente ? $record->dependiente->nombre : $paciente->name;
        $filename = 'expediente_clinico_' . Str::slug($subjectName, '_') . '_' . now()->format('Ymd') . '.pdf';

        return response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    public function showNote(NotaSoap $nota)
    {
        $nota->load(['cita.paciente', 'cita.doctor', 'cita.especialidad', 'diagnosticos', 'enmiendas.autor']);

        if ($nota->estado !== NotaSoap::ESTADO_FIRMADA) {
            abort(404);
        }

        $backUrl = $nota->cita?->paciente_id
            ? route('admin.historial.paciente', $nota->cita->paciente_id).($nota->cita?->dependiente_id ? '?dependiente_id='.$nota->cita->dependiente_id : '')
            : route('admin.historial.index');

        return view('admin.historial.show', [
            'nota' => $nota,
            'backUrl' => $backUrl,
            'backLabel' => 'Volver a notas firmadas',
        ]);
    }
}
