<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarCertificadoMedicoJob;
use App\Http\Requests\StoreCertificadoMedicoRequest;
use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\CitaEvento;
use App\Models\NotaSoap;
use App\Services\CertificadoMedicoPdfService;
use App\Services\ClinicalRecordService;
use App\Services\DocumentoCsvService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class CertificadoMedicoController extends Controller
{
    public function create(Cita $cita)
    {
        $this->ensureCanIssue($cita);
        $cita->loadMissing(['paciente', 'dependiente', 'doctor.especialidades', 'especialidad', 'certificadoMedico', 'notaSoap.diagnosticos']);

        if ($cita->certificadoMedico) {
            return redirect()->route('doctor.certificados.show', $cita->certificadoMedico);
        }

        $textoSugerido = $this->textoSugerido($cita);

        return view('doctor.certificados.crear', compact('cita', 'textoSugerido'));
    }

    public function store(
        StoreCertificadoMedicoRequest $request,
        Cita $cita,
        ClinicalRecordService $clinicalRecords,
        CertificadoMedicoPdfService $pdfs
    ) {
        $cita->loadMissing(['paciente', 'dependiente', 'doctor.especialidades', 'especialidad', 'certificadoMedico']);

        $this->ensureCanIssue($cita);

        if ($cita->certificadoMedico) {
            return redirect()
                ->route('doctor.certificados.show', $cita->certificadoMedico)
                ->with('info', 'Ya existe un certificado medico emitido para esta cita.');
        }

        $data = $request->validated();
        $diasReposo = (int) ($data['dias_reposo'] ?? 0);
        $csv = app(DocumentoCsvService::class)->generateCsv();

        $certificado = DB::transaction(function () use ($cita, $clinicalRecords, $data, $diasReposo, $csv): CertificadoMedico {
            $record = $clinicalRecords->ensureForPatient($cita->paciente_id, Auth::id(), $cita->dependiente_id);

            $certificado = CertificadoMedico::create([
                'codigo' => $this->generarCodigo($cita),
                'csv' => $csv,
                'cita_id' => $cita->id,
                'paciente_id' => $cita->paciente_id,
                'dependiente_id' => $cita->dependiente_id,
                'doctor_id' => $cita->doctor_id,
                'clinical_record_id' => $record->id,
                'fecha_emision' => now('America/Guayaquil'),
                'texto_constancia' => $data['texto_constancia'],
                'dias_reposo' => $diasReposo,
                'reposo_desde' => $diasReposo > 0 ? ($data['reposo_desde'] ?? null) : null,
                'reposo_hasta' => $diasReposo > 0 ? ($data['reposo_hasta'] ?? null) : null,
                'observaciones' => $data['observaciones'] ?? null,
                'envio_estado' => 'queued',
                'envio_intentos' => 0,
            ]);

            CitaEvento::create([
                'cita_id' => $cita->id,
                'user_id' => Auth::id(),
                'tipo' => 'certificado_emitido',
                'comentario' => 'Certificado medico '.$certificado->codigo.' emitido.',
            ]);

            return $certificado;
        });

        $pdfs->generarYGuardar($certificado);

        EnviarCertificadoMedicoJob::dispatch($certificado->id);

        return redirect()
            ->route('doctor.certificados.show', $certificado)
            ->with('success', 'Certificado medico emitido correctamente.');
    }

    public function show(CertificadoMedico $certificado)
    {
        $certificado->loadMissing(['cita.especialidad', 'cita.dependiente', 'paciente', 'dependiente', 'doctor.especialidades']);
        $this->ensureCanView($certificado);

        return view('doctor.certificados.show', [
            'certificado' => $certificado,
        ]);
    }

    public function download(CertificadoMedico $certificado, CertificadoMedicoPdfService $pdfs)
    {
        $docService = app(\App\Services\MedicalCertificateDocumentService::class);
        $docService->ensureUserCanView($certificado);

        $pdfs->obtenerOGenerar($certificado);

        return $docService->streamDownload($certificado);
    }

    public function resend(CertificadoMedico $certificado, CertificadoMedicoPdfService $pdfs)
    {
        $docService = app(\App\Services\MedicalCertificateDocumentService::class);
        $docService->ensureUserCanView($certificado);

        $diskName = $docService->resolveDisk($certificado->pdf_disk);
        if (! $certificado->pdf_path || ! Storage::disk($diskName)->exists($certificado->pdf_path)) {
            $pdfs->generarYGuardar($certificado);
        }

        $certificado->forceFill([
            'envio_estado' => 'queued',
            'envio_error' => null,
        ])->saveQuietly();

        EnviarCertificadoMedicoJob::dispatch($certificado->id, forceResend: true);

        return back()->with('success', 'Se reintentara el envio del certificado por correo.');
    }

    public function corregir(CertificadoMedico $certificado)
    {
        $certificado->loadMissing(['cita.paciente', 'cita.dependiente', 'cita.doctor.especialidades', 'cita.especialidad', 'dependiente']);
        $this->ensureCanView($certificado);

        if ($certificado->isReemplazado()) {
            $vigente = $certificado->cita->certificadoMedico;

            return redirect()
                ->route('doctor.certificados.show', $vigente ?: $certificado)
                ->with('info', 'Este certificado fue reemplazado por una version posterior. Para realizar correcciones, edite la version vigente.');
        }

        return view('doctor.certificados.corregir', [
            'certificado' => $certificado,
            'cita' => $certificado->cita,
        ]);
    }

    public function storeCorregido(
        \App\Http\Requests\StoreCorrectionCertificadoMedicoRequest $request,
        CertificadoMedico $certificado,
        ClinicalRecordService $clinicalRecords,
        CertificadoMedicoPdfService $pdfs
    ) {
        $certificado->loadMissing(['cita.paciente', 'cita.dependiente', 'cita.doctor.especialidades', 'cita.especialidad', 'dependiente']);
        $this->ensureCanView($certificado);

        if ($certificado->isReemplazado()) {
            $vigente = $certificado->cita->certificadoMedico;

            return redirect()
                ->route('doctor.certificados.show', $vigente ?: $certificado)
                ->with('info', 'Este certificado ya fue reemplazado por otra version y no se puede volver a corregir directamente.');
        }

        $data = $request->validated();
        $diasReposo = (int) ($data['dias_reposo'] ?? 0);

        $newCertificado = DB::transaction(function () use ($certificado, $clinicalRecords, $data, $diasReposo, $pdfs): CertificadoMedico {
            $oldCert = CertificadoMedico::query()->where('id', $certificado->id)->lockForUpdate()->firstOrFail();

            if ($oldCert->isReemplazado()) {
                throw new \DomainException('Este certificado ya fue reemplazado y no se puede corregir.');
            }

            $cita = $oldCert->cita;
            $csv = app(DocumentoCsvService::class)->generateCsv();
            $record = $clinicalRecords->ensureForPatient($cita->paciente_id, Auth::id(), $cita->dependiente_id);
            $newVersion = ((int) ($oldCert->version ?: 1)) + 1;

            $newCert = CertificadoMedico::create([
                'codigo' => $this->generarCodigo($cita),
                'csv' => $csv,
                'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
                'version' => $newVersion,
                'reemplaza_a_id' => $oldCert->id,
                'cita_id' => $cita->id,
                'paciente_id' => $cita->paciente_id,
                'dependiente_id' => $cita->dependiente_id,
                'doctor_id' => $cita->doctor_id,
                'clinical_record_id' => $record->id,
                'fecha_emision' => now('America/Guayaquil'),
                'texto_constancia' => $data['texto_constancia'],
                'dias_reposo' => $diasReposo,
                'reposo_desde' => $diasReposo > 0 ? ($data['reposo_desde'] ?? null) : null,
                'reposo_hasta' => $diasReposo > 0 ? ($data['reposo_hasta'] ?? null) : null,
                'observaciones' => $data['observaciones'] ?? null,
                'envio_estado' => 'queued',
                'envio_intentos' => 0,
            ]);

            $pdfs->generarYGuardar($newCert);

            $oldCert->forceFill([
                'estado_version' => CertificadoMedico::ESTADO_REEMPLAZADO,
                'reemplazado_por_id' => $newCert->id,
                'motivo_correccion' => $data['motivo_correccion'],
                'corregido_por' => Auth::id(),
                'fecha_correccion' => now('America/Guayaquil'),
            ])->save();

            CitaEvento::create([
                'cita_id' => $cita->id,
                'user_id' => Auth::id(),
                'tipo' => 'certificado_corregido',
                'comentario' => 'Certificado medico corregido. Nueva version '.$newCert->codigo.' (v'.$newVersion.') emitida.',
            ]);

            return $newCert;
        });

        EnviarCertificadoMedicoJob::dispatch($newCertificado->id);

        return redirect()
            ->route('doctor.certificados.show', $newCertificado)
            ->with('success', 'Certificado medico corregido y emitido correctamente.');
    }

    private function ensureCanIssue(Cita $cita): void
    {
        if ((int) $cita->doctor_id !== (int) Auth::id()) {
            abort(403);
        }

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            abort(403, 'Solo se puede emitir certificado medico para citas realizadas.');
        }
    }

    private function ensureCanView(CertificadoMedico $certificado): void
    {
        if ((int) $certificado->doctor_id !== (int) Auth::id()) {
            abort(403);
        }
    }

    private function generarCodigo(Cita $cita): string
    {
        $base = sprintf('CM-%s-%06d', now('America/Guayaquil')->format('Ymd'), $cita->id);
        $codigo = $base;
        $i = 1;

        while (CertificadoMedico::query()->where('codigo', $codigo)->exists()) {
            $i++;
            $codigo = $base.'-'.$i;
        }

        return $codigo;
    }

    private function textoSugerido(Cita $cita): string
    {
        $fecha = $cita->fecha?->format('d/m/Y') ?? 'la fecha indicada';
        $paciente = $cita->nombrePacienteReal();

        $texto = 'Se certifica que el/la paciente '.$paciente.' fue atendido(a) en esta institucion en fecha '.$fecha
            .' y, de acuerdo con la valoracion medica realizada, se emite la presente constancia para los fines pertinentes.';

        $nota = $cita->notaSoap;
        if ($nota && $nota->estado === NotaSoap::ESTADO_FIRMADA && filled($nota->assessment)) {
            $texto .= "\n\nResumen clinico: ".$nota->assessment;
        }

        return $texto;
    }
}
