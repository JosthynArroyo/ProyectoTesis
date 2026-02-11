<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Mail\ResultadoLaboratorioMail;
use App\Models\LaboratorioOrden;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

class OrdenController extends Controller
{
    public function index()
    {
        $ordenes = LaboratorioOrden::with(['cita.paciente', 'cita.doctor', 'cita.especialidad'])
            ->whereHas('cita', function ($q) {
                $q->where('doctor_id', Auth::id());
            })
            ->orderByDesc('id')
            ->paginate(12);

        return view('laboratorio.ordenes.index', compact('ordenes'));
    }

    public function marcarMuestra(LaboratorioOrden $orden)
    {
        $orden->load('cita');
        if ($orden->cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if ($orden->estado === LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE) {
            return back()->withErrors(['error' => 'Los resultados ya fueron publicados.']);
        }

        $orden->estado = LaboratorioOrden::ESTADO_MUESTRA_TOMADA;
        $orden->save();

        return back()->with('success', 'Muestra registrada.');
    }

    public function subirResultado(Request $request, LaboratorioOrden $orden)
    {
        $orden->load(['cita.paciente', 'cita.doctor', 'cita.especialidad']);
        if ($orden->cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        $data = $request->validate(
            [
                'resultado_pdf'     => 'required|file|mimes:pdf|max:5120',
                'resultado_resumen' => 'required|string|max:2000',
            ],
            [
                'resultado_pdf.required' => 'Adjunta el PDF de resultados.',
            ]
        );

        $path = $request->file('resultado_pdf')->store('laboratorio_resultados');

        $orden->resultado_path = $path;
        $orden->resultado_resumen = $data['resultado_resumen'] ?? null;
        $orden->resultado_publicado_at = now('America/Guayaquil');
        $orden->estado = LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE;

        if ($orden->cita->paciente && $orden->cita->paciente->email) {
            Mail::to($orden->cita->paciente->email)->send(new ResultadoLaboratorioMail($orden));
            $orden->resultado_enviado_at = now('America/Guayaquil');
        }

        $orden->save();

        return back()->with('success', 'Resultados subidos y notificados al paciente.');
    }

    public function download(LaboratorioOrden $orden)
    {
        $orden->load('cita');
        if ($orden->cita->doctor_id !== Auth::id()) {
            abort(403);
        }

        if (!$orden->resultado_path || !Storage::exists($orden->resultado_path)) {
            return back()->withErrors(['error' => 'No hay resultados disponibles para descargar.']);
        }

        $name = 'resultado_laboratorio_'.$orden->id.'.pdf';
        return Storage::download($orden->resultado_path, $name);
    }
}
