<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Mail\RecetaMedicaMail;
use App\Models\Cita;
use App\Models\Receta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Dompdf\Dompdf;
use Dompdf\Options;

class RecetaController extends Controller
{
    /**
     * Historial de recetas del doctor autenticado.
     */
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
        $cita = Cita::with(['paciente','doctor','especialidad','receta'])->findOrFail($citaId);

        if ($cita->doctor_id !== Auth::id()) abort(403);
        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede generar receta para citas realizadas.');
        }
        if ($cita->receta) {
            return redirect()->route('doctor.recetas.edit', $cita->id);
        }

        return view('doctor.recetas.crear', compact('cita'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'cita_id'      => 'required|exists:citas_medicas,id',
            'diagnostico'  => 'required|string|max:2000',
            'medicamentos' => 'required|string|max:3000',
            'indicaciones' => 'nullable|string|max:3000',
        ]);

        $cita = Cita::with(['paciente','doctor','especialidad','receta'])->findOrFail($data['cita_id']);

        if ($cita->doctor_id !== Auth::id()) abort(403);
        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede generar receta para citas realizadas.');
        }
        if ($cita->receta) {
            return redirect()->route('doctor.recetas.edit', $cita->id)
                ->with('info', 'La receta ya existe. Puedes editarla.');
        }

        [$relativePath, $pdfOutput, $fileName] = $this->generarPdfYGuardar(
            $cita, $data['diagnostico'], $data['medicamentos'], $data['indicaciones'] ?? ''
        );

        $receta = Receta::create([
            'cita_id'      => $cita->id,
            'diagnostico'  => $data['diagnostico'],
            'medicamentos' => $data['medicamentos'],
            'indicaciones' => $data['indicaciones'] ?? null,
            'pdf_path'     => $relativePath,
            'enviado_en'   => now('America/Guayaquil'),
        ]);

        Mail::to($cita->paciente->email)->send(new RecetaMedicaMail(
            $cita, $relativePath, $pdfOutput, $fileName, motivo: 'creacion'
        ));

        return redirect()->route('doctor.citas')->with('success', 'Receta generada y enviada al correo del paciente.');
    }

    public function edit($citaId)
    {
        $cita = Cita::with(['paciente','doctor','especialidad','receta'])->findOrFail($citaId);

        if ($cita->doctor_id !== Auth::id()) abort(403);
        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede editar la receta de citas realizadas.');
        }
        if (!$cita->receta) {
            return redirect()->route('doctor.recetas.create', $cita->id)
                ->with('info', 'Aún no existe receta. Genera la primera.');
        }

        if (!$cita->receta->can_edit) {
            return back()->with('error', 'El periodo de edición (1 hora) ha expirado.');
        }

        return view('doctor.recetas.editar', ['cita' => $cita, 'receta' => $cita->receta]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'cita_id'       => 'required|exists:citas_medicas,id',
            'diagnostico'   => 'required|string|max:2000',
            'medicamentos'  => 'required|string|max:3000',
            'indicaciones'  => 'nullable|string|max:3000',
            'regenerar_pdf' => 'nullable|boolean',
            'reenviar'      => 'nullable|boolean',
        ]);

        $cita = Cita::with(['paciente','doctor','especialidad','receta'])->findOrFail($data['cita_id']);

        if ($cita->doctor_id !== Auth::id()) abort(403);
        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede editar la receta de citas realizadas.');
        }
        if (!$cita->receta) {
            return redirect()->route('doctor.recetas.create', $cita->id)
                ->with('info', 'Aún no existe receta. Genera la primera.');
        }
        if (!$cita->receta->can_edit) {
            return back()->with('error', 'El periodo de edición (1 hora) ha expirado.');
        }

        $receta = $cita->receta;

        // Guardar primero
        $receta->update([
            'diagnostico'  => $data['diagnostico'],
            'medicamentos' => $data['medicamentos'],
            'indicaciones' => $data['indicaciones'] ?? null,
        ]);

        // ¿Regenerar PDF?
        $needRegen = $request->boolean('regenerar_pdf')
            || $request->boolean('reenviar')
            || empty($receta->pdf_path)
            || !Storage::exists($receta->pdf_path);

        $relativePath = $receta->pdf_path;
        $pdfOutput = '';
        $fileName  = 'receta_'.$cita->id.'_'.now()->format('Ymd_His').'.pdf';

        if ($needRegen) {
            [$relativePath, $pdfOutput, $fileName] = $this->generarPdfYGuardar(
                $cita, $receta->diagnostico, $receta->medicamentos, $receta->indicaciones ?? ''
            );
            $receta->update(['pdf_path' => $relativePath]);
        }

        // ¿Reenviar?
        if ($request->boolean('reenviar')) {
            if (!$pdfOutput && $relativePath && Storage::exists($relativePath)) {
                $pdfOutput = Storage::get($relativePath);
            }

            Mail::to($cita->paciente->email)->send(new RecetaMedicaMail(
                $cita, $relativePath, $pdfOutput ?: '', $fileName, motivo: 'actualizacion'
            ));
            $receta->update(['enviado_en' => now('America/Guayaquil')]);

            return redirect()->route('doctor.recetas.edit', $cita->id)
                ->with('success', 'Receta actualizada y reenviada al paciente.');
        }

        return redirect()->route('doctor.recetas.edit', $cita->id)
            ->with(['success' => 'Receta actualizada correctamente.', 'ask_resend' => true]);
    }

    public function resend($citaId)
    {
        $cita = Cita::with(['paciente','doctor','especialidad','receta'])->findOrFail($citaId);
        if ($cita->doctor_id !== Auth::id()) abort(403);
        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede reenviar la receta de citas realizadas.');
        }
        if (!$cita->receta) {
            return back()->with('error', 'Aún no existe receta para esta cita.');
        }

        $receta = $cita->receta;

        // Regenerar siempre para garantizar la última versión
        [$relativePath, $pdfOutput, $fileName] = $this->generarPdfYGuardar(
            $cita, $receta->diagnostico, $receta->medicamentos, $receta->indicaciones ?? ''
        );
        $receta->update([
            'pdf_path'   => $relativePath,
            'enviado_en' => now('America/Guayaquil'),
        ]);

        Mail::to($cita->paciente->email)->send(new RecetaMedicaMail(
            $cita, $relativePath, $pdfOutput, $fileName, motivo: 'actualizacion'
        ));

        return back()->with('success', 'Receta actualizada y reenviada al paciente.');
    }

    public function download($citaId)
    {
        $cita = Cita::with(['doctor','receta'])->findOrFail($citaId);
        if ($cita->doctor_id !== Auth::id()) abort(403);

        if (!$cita->receta || !$cita->receta->pdf_path) {
            return back()->with('error', 'No hay PDF disponible para descargar.');
        }

        $path = $cita->receta->pdf_path;

        if (!Storage::exists($path)) {
            [$path] = $this->generarPdfYGuardar(
                $cita,
                $cita->receta->diagnostico,
                $cita->receta->medicamentos,
                $cita->receta->indicaciones ?? ''
            );
            $cita->receta->update(['pdf_path' => $path]);
        }

        $downloadName = 'receta_'.$cita->id.'.pdf';
        return Storage::download($path, $downloadName);
    }

    /**
     * Genera el PDF y lo guarda. Embebe el logo para evitar “type unknown”.
     */
    private function generarPdfYGuardar(Cita $cita, string $diagnostico, string $medicamentos, string $indicaciones = ''): array
    {
        $viewData = [
            'cita'         => $cita,
            'diagnostico'  => $diagnostico,
            'medicamentos' => $medicamentos,
            'indicaciones' => $indicaciones,
            'fechaPdf'     => now('America/Guayaquil'),
            'logoBase64'   => $this->logoBase64(), // <- se pasa a la vista
        ];

        $html = view('pdf.receta', $viewData)->render();

        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4');
        $dompdf->render();

        $pdfOutput = $dompdf->output();

        $dir = 'recetas';
        if (!Storage::exists($dir)) Storage::makeDirectory($dir);

        $fileName     = 'receta_'.$cita->id.'_'.now()->format('Ymd_His').'.pdf';
        $relativePath = $dir.'/'.$fileName;

        Storage::put($relativePath, $pdfOutput);

        return [$relativePath, $pdfOutput, $fileName];
    }

    /**
     * Devuelve el logo /public/img/logo_clinica.png en data-URI base64.
     */
    private function logoBase64(): ?string
    {
        $path = public_path('img/logo_clinica.png');
        if (!is_file($path)) return null;

        // Ajusta MIME si tu archivo es jpg/webp.
        $mime = 'image/png';
        $data = base64_encode(file_get_contents($path));
        return "data:{$mime};base64,{$data}";
    }
}
