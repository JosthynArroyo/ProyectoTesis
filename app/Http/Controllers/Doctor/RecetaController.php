<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Mail\RecetaMedicaMail;
use App\Models\ClinicalRecordMedication;
use App\Models\Cita;
use App\Models\NotaSoap;
use App\Models\Receta;
use App\Services\ClinicalRecordService;
use App\Services\DocumentoCsvService;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class RecetaController extends Controller
{
    public function index(Request $request)
    {
        $recetas = Receta::with(['cita.paciente', 'cita.doctor', 'cita.especialidad'])
            ->whereHas('cita', function ($q) {
                $q->where('doctor_id', Auth::id());
            })
            ->latest('id')
            ->paginate(12);

        return view('doctor.recetas.index', compact('recetas'));
    }

    public function create($citaId)
    {
        $cita = Cita::with(['paciente', 'doctor', 'especialidad', 'receta', 'notaSoap.diagnosticos', 'notaSoap.followUpCita', 'notaSoap.clinicalRecord.medications'])->findOrFail($citaId);

        if ($cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede generar receta para citas realizadas.');
        }

        if ($cita->receta) {
            return redirect()->route('doctor.recetas.edit', $cita->id);
        }

        $defaults = $this->buildRecipeDefaults($cita);

        return view('doctor.recetas.crear', array_merge(compact('cita'), $defaults));
    }

    public function store(Request $request)
    {
        $citaData = $request->validate([
            'cita_id' => 'required|exists:citas_medicas,id',
        ]);

        $cita = Cita::with(['paciente', 'doctor', 'especialidad', 'receta', 'notaSoap.diagnosticos', 'notaSoap.clinicalRecord.medications'])->findOrFail($citaData['cita_id']);

        if (! filled($request->input('diagnostico'))) {
            $request->merge([
                'diagnostico' => $this->diagnosticoDesdeNotaClinica($cita),
            ]);
        }

        if (! filled($request->input('medicamentos'))) {
            $request->merge([
                'medicamentos' => $this->medicamentosDesdeExpediente($cita),
            ]);
        }

        $data = $request->validate([
            'cita_id' => 'required|exists:citas_medicas,id',
            'diagnostico' => 'required|string|max:2000',
            'medicamentos' => 'required|string|max:3000',
            'indicaciones' => 'nullable|string|max:3000',
        ]);

        if ($cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede generar receta para citas realizadas.');
        }

        if ($cita->receta) {
            return redirect()->route('doctor.recetas.edit', $cita->id)
                ->with('info', 'La receta ya existe. Puedes editarla.');
        }

        $csvService = app(DocumentoCsvService::class);
        $recipeService = app(\App\Services\RecipeDocumentService::class);
        $csv = $csvService->generateCsv();

        [$pdfBinary, $csv] = $recipeService->generatePdfOutput(
            $cita,
            $data['diagnostico'],
            $data['medicamentos'],
            $data['indicaciones'] ?? '',
            $csv,
            $csvService
        );

        $record = app(ClinicalRecordService::class)->ensureForPatient($cita->paciente_id, Auth::id(), $cita->dependiente_id);

        $newStorage = null;

        $receta = DB::transaction(function () use ($cita, $record, $data, $csv, $pdfBinary, $recipeService, &$newStorage) {
            $receta = Receta::create([
                'cita_id' => $cita->id,
                'nota_soap_id' => $cita->notaSoap?->id,
                'clinical_record_id' => $record->id,
                'diagnostico' => $data['diagnostico'],
                'medicamentos' => $data['medicamentos'],
                'indicaciones' => $data['indicaciones'] ?? null,
                'csv' => $csv,
                'pdf_path' => '',
                'pdf_disk' => null,
                'enviado_en' => now('America/Guayaquil'),
            ]);

            $newStorage = $recipeService->storeRecipePdf($receta, $pdfBinary);

            $receta->forceFill([
                'pdf_path' => $newStorage['pdf_path'],
                'pdf_disk' => $newStorage['pdf_disk'],
            ])->saveQuietly();

            return $receta;
        });

        $fileName = 'receta_'.$cita->id.'_'.now()->format('Ymd_His').'.pdf';

        try {
            Mail::to($cita->paciente->email)->send(new RecetaMedicaMail(
                $cita, $newStorage['pdf_path'], $pdfBinary, $fileName, motivo: 'creacion', receta: $receta
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed sending prescription email: '.$e->getMessage());
        }

        return redirect()->route('doctor.citas')->with('success', 'Receta generada y enviada al correo del paciente.');
    }

    public function edit($citaId)
    {
        $cita = Cita::with(['paciente', 'doctor', 'especialidad', 'receta', 'notaSoap.clinicalRecord.medications'])->findOrFail($citaId);

        if ($cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede editar la receta de citas realizadas.');
        }

        if (! $cita->receta) {
            return redirect()->route('doctor.recetas.create', $cita->id)
                ->with('info', 'Aun no existe receta. Genera la primera.');
        }

        if (! $cita->receta->can_edit) {
            return back()->with('error', 'El periodo de edicion (1 hora) ha expirado.');
        }

        $defaults = $this->buildRecipeDefaults($cita, $cita->receta);

        return view('doctor.recetas.editar', array_merge(['cita' => $cita, 'receta' => $cita->receta], $defaults));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cita_id' => 'required|exists:citas_medicas,id',
            'diagnostico' => 'required|string|max:2000',
            'medicamentos' => 'required|string|max:3000',
            'indicaciones' => 'nullable|string|max:3000',
            'regenerar_pdf' => 'required|boolean',
            'reenviar' => 'required|boolean',
        ]);

        $cita = Cita::with(['paciente', 'doctor', 'especialidad', 'receta'])->findOrFail($data['cita_id']);

        if ($cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede editar la receta de citas realizadas.');
        }

        if (! $cita->receta) {
            return redirect()->route('doctor.recetas.create', $cita->id)
                ->with('info', 'Aun no existe receta. Genera la primera.');
        }

        if (! $cita->receta->can_edit) {
            return back()->with('error', 'El periodo de edicion (1 hora) ha expirado.');
        }

        $receta = $cita->receta;
        $recipeService = app(\App\Services\RecipeDocumentService::class);
        $csvService = app(DocumentoCsvService::class);
        $csv = $this->obtenerCsv($receta, $csvService);

        $oldDisk = $recipeService->resolveDisk($receta->pdf_disk);
        $needRegen = $request->boolean('regenerar_pdf')
            || $request->boolean('reenviar')
            || empty($receta->pdf_path)
            || ! Storage::disk($oldDisk)->exists($receta->pdf_path);

        $pdfBinary = null;
        $newStorage = null;
        $fileName = 'receta_'.$cita->id.'_'.now()->format('Ymd_His').'.pdf';

        if ($needRegen) {
            [$pdfBinary, $csv] = $recipeService->generatePdfOutput(
                $cita,
                $data['diagnostico'],
                $data['medicamentos'],
                $data['indicaciones'] ?? '',
                $csv,
                $csvService
            );

            $oldPdfPath = (string) $receta->getRawOriginal('pdf_path');
            $oldPdfDisk = (string) $receta->getRawOriginal('pdf_disk');

            DB::transaction(function () use ($receta, $data, $cita, $csv, $pdfBinary, $recipeService, &$newStorage) {
                $receta->update([
                    'diagnostico' => $data['diagnostico'],
                    'medicamentos' => $data['medicamentos'],
                    'indicaciones' => $data['indicaciones'] ?? null,
                    'nota_soap_id' => $receta->nota_soap_id ?: $cita->notaSoap?->id,
                    'csv' => $csv,
                ]);

                $newStorage = $recipeService->storeRecipePdf($receta, $pdfBinary);

                $receta->forceFill([
                    'pdf_path' => $newStorage['pdf_path'],
                    'pdf_disk' => $newStorage['pdf_disk'],
                ])->saveQuietly();
            });

            $recipeService->cleanupOldPdf($oldPdfPath, $oldPdfDisk);
        } else {
            $receta->update([
                'diagnostico' => $data['diagnostico'],
                'medicamentos' => $data['medicamentos'],
                'indicaciones' => $data['indicaciones'] ?? null,
                'nota_soap_id' => $receta->nota_soap_id ?: $cita->notaSoap?->id,
            ]);
        }

        if ($request->boolean('reenviar')) {
            try {
                Mail::to($cita->paciente->email)->send(new RecetaMedicaMail(
                    $cita, $receta->pdf_path, $pdfBinary ?: '', $fileName, motivo: 'actualizacion', receta: $receta
                ));
                $receta->update(['enviado_en' => now('America/Guayaquil')]);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Failed resending prescription email: '.$e->getMessage());
            }

            return redirect()->route('doctor.recetas.edit', $cita->id)
                ->with('success', 'Receta actualizada y reenviada al paciente.');
        }

        return redirect()->route('doctor.recetas.edit', $cita->id)
            ->with(['success' => 'Receta actualizada correctamente.', 'ask_resend' => true]);
    }

    public function resend($citaId)
    {
        $cita = Cita::with(['paciente', 'doctor', 'especialidad', 'receta', 'notaSoap.clinicalRecord.medications'])->findOrFail($citaId);

        if ($cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede reenviar la receta de citas realizadas.');
        }

        if (! $cita->receta) {
            return back()->with('error', 'Aun no existe receta para esta cita.');
        }

        $receta = $cita->receta;
        $recipeService = app(\App\Services\RecipeDocumentService::class);
        $csvService = app(DocumentoCsvService::class);
        $csv = $this->obtenerCsv($receta, $csvService);

        [$pdfBinary, $csv] = $recipeService->generatePdfOutput(
            $cita,
            $receta->diagnostico,
            $receta->medicamentos,
            $receta->indicaciones ?? '',
            $csv,
            $csvService
        );

        $oldPdfPath = (string) $receta->getRawOriginal('pdf_path');
        $oldPdfDisk = (string) $receta->getRawOriginal('pdf_disk');

        $newStorage = $recipeService->storeRecipePdf($receta, $pdfBinary);

        $receta->forceFill([
            'csv' => $csv,
            'pdf_path' => $newStorage['pdf_path'],
            'pdf_disk' => $newStorage['pdf_disk'],
            'enviado_en' => now('America/Guayaquil'),
        ])->saveQuietly();

        $recipeService->cleanupOldPdf($oldPdfPath, $oldPdfDisk);

        $fileName = 'receta_'.$cita->id.'_'.now()->format('Ymd_His').'.pdf';

        try {
            Mail::to($cita->paciente->email)->send(new RecetaMedicaMail(
                $cita, $newStorage['pdf_path'], $pdfBinary, $fileName, motivo: 'actualizacion', receta: $receta
            ));
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Failed resending prescription email: '.$e->getMessage());
        }

        return back()->with('success', 'Receta actualizada y reenviada al paciente.');
    }

    public function download($citaId)
    {
        $cita = Cita::with(['doctor', 'paciente', 'dependiente', 'receta'])->findOrFail($citaId);

        $recipeService = app(\App\Services\RecipeDocumentService::class);
        $recipeService->ensureUserCanView($cita);

        if (! $cita->receta || ! $cita->receta->pdf_path) {
            return back()->with('error', 'No hay PDF disponible para descargar.');
        }

        $receta = $cita->receta;
        $diskName = $recipeService->resolveDisk($receta->pdf_disk);

        if (! Storage::disk($diskName)->exists($receta->pdf_path)) {
            $csvService = app(DocumentoCsvService::class);
            $csv = $this->obtenerCsv($receta, $csvService);

            [$pdfBinary, $csv] = $recipeService->generatePdfOutput(
                $cita,
                $receta->diagnostico,
                $receta->medicamentos,
                $receta->indicaciones ?? '',
                $csv,
                $csvService
            );

            $newStorage = $recipeService->storeRecipePdf($receta, $pdfBinary);
            $receta->forceFill([
                'pdf_path' => $newStorage['pdf_path'],
                'pdf_disk' => $newStorage['pdf_disk'],
            ])->saveQuietly();
        }

        return $recipeService->streamDownload($receta, 'receta_'.$cita->id.'.pdf');
    }

    private function obtenerCsv(Receta $receta, DocumentoCsvService $csvService): string
    {
        $csv = trim((string) $receta->csv);

        if ($csv !== '') {
            return strtoupper($csv);
        }

        $csv = $csvService->generateCsv();
        $receta->forceFill(['csv' => $csv])->saveQuietly();

        return $csv;
    }

    private function diagnosticoDesdeNotaClinica(Cita $cita): string
    {
        $nota = $cita->notaSoap;
        $partes = [];

        if ($nota && $nota->estado === NotaSoap::ESTADO_FIRMADA) {
            $diagnosticos = $nota->diagnosticos
                ->filter(fn ($diagnostico) => filled($diagnostico->texto))
                ->map(function ($diagnostico) {
                    $tipo = match ($diagnostico->tipo) {
                        'principal' => 'Diagnostico principal',
                        'secundario' => 'Diagnostico secundario',
                        'diferencial' => 'Diagnostico diferencial',
                        default => 'Diagnostico',
                    };

                    $cie10 = filled($diagnostico->cie10) ? ' (CIE-10: '.$diagnostico->cie10.')' : '';

                    return $tipo.': '.$diagnostico->texto.$cie10;
                })
                ->values()
                ->all();

            $partes = array_merge($partes, $diagnosticos);
        }

        return implode("\n", $partes);
    }

    private function buildRecipeDefaults(Cita $cita, ?Receta $receta = null): array
    {
        $diagnostico = $receta?->diagnostico ?: $this->diagnosticoDesdeNotaClinica($cita);
        $medicamentos = $receta?->medicamentos ?: $this->medicamentosDesdeExpediente($cita);

        return [
            'diagnosticoSugerido' => $diagnostico,
            'medicamentosSugeridos' => $medicamentos,
        ];
    }

    private function medicamentosDesdeExpediente(Cita $cita): string
    {
        $record = $this->clinicalRecordForCita($cita);
        $medications = $record->medications
            ->where('status', ClinicalRecordMedication::STATUS_ACTIVE)
            ->map(function (ClinicalRecordMedication $medication): string {
                return $this->formatMedicationForRecipe($medication);
            })
            ->filter()
            ->unique()
            ->values();

        return $medications->implode("\n");
    }

    private function formatMedicationForRecipe(ClinicalRecordMedication $medication): string
    {
        $parts = [trim((string) $medication->name)];

        if (filled($medication->presentation)) {
            $parts[] = trim((string) $medication->presentation);
        }

        if (filled($medication->dosage)) {
            $parts[] = 'Dosis: '.trim((string) $medication->dosage);
        }

        if (filled($medication->frequency)) {
            $parts[] = 'Frecuencia: '.trim((string) $medication->frequency);
        }

        if (filled($medication->route)) {
            $parts[] = 'Via: '.trim((string) $medication->route);
        }

        if (filled($medication->instructions)) {
            $parts[] = trim((string) $medication->instructions);
        }

        return collect($parts)
            ->map(fn ($part) => trim((string) $part))
            ->filter()
            ->implode(' | ');
    }

    private function clinicalRecordForCita(Cita $cita)
    {
        return app(ClinicalRecordService::class)->ensureForPatient(
            $cita->paciente_id,
            Auth::id(),
            $cita->dependiente_id
        );
    }

}
