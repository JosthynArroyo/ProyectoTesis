<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\NotaSoap;
use Illuminate\Support\Facades\Auth;

class HistorialController extends Controller
{
    public function index()
    {
        $pacienteId = Auth::id();

        $notas = NotaSoap::query()
            ->select('notas_soap.*')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->where('citas_medicas.paciente_id', $pacienteId)
            ->orderBy('citas_medicas.fecha', 'desc')
            ->orderBy('citas_medicas.hora', 'desc')
            ->with(['cita.doctor', 'cita.especialidad'])
            ->paginate(10);

        return view('paciente.historial', [
            'notas' => $notas,
        ]);
    }

    public function show(NotaSoap $nota)
    {
        $pacienteId = Auth::id();

        $nota->load(['cita.paciente', 'cita.doctor', 'cita.especialidad', 'diagnosticos', 'enmiendas.autor']);

        if ($nota->estado !== NotaSoap::ESTADO_FIRMADA || $nota->cita->paciente_id !== $pacienteId) {
            abort(403);
        }

        return view('paciente.historial-show', [
            'nota' => $nota,
        ]);
    }
}
