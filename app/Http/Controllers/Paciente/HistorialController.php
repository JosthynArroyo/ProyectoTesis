<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\CertificadoMedico;
use App\Models\NotaSoap;
use Illuminate\Support\Facades\Auth;

class HistorialController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $pacienteId = $user->id;
        $pacienteFilter = request()->get('paciente', 'all');

        $dependientes = $user->dependientes()->get();

        $notasQuery = NotaSoap::query()
            ->select('notas_soap.*')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->where('citas_medicas.paciente_id', $pacienteId);

        $certificadosQuery = CertificadoMedico::query()
            ->where('paciente_id', $pacienteId);

        if ($pacienteFilter === 'principal') {
            $notasQuery->whereNull('citas_medicas.dependiente_id');
            $certificadosQuery->whereNull('dependiente_id');
        } elseif (is_numeric($pacienteFilter)) {
            $depId = (int) $pacienteFilter;
            if ($dependientes->contains('id', $depId)) {
                $notasQuery->where('citas_medicas.dependiente_id', $depId);
                $certificadosQuery->where('dependiente_id', $depId);
            } else {
                $notasQuery->whereRaw('1=0');
                $certificadosQuery->whereRaw('1=0');
            }
        }

        $notas = $notasQuery
            ->orderBy('citas_medicas.fecha', 'desc')
            ->orderBy('citas_medicas.hora', 'desc')
            ->with(['cita.doctor', 'cita.especialidad'])
            ->paginate(10)
            ->withQueryString();

        $certificados = $certificadosQuery
            ->with(['doctor', 'cita.doctor', 'cita.especialidad'])
            ->orderByDesc('fecha_emision')
            ->get();

        return view('paciente.historial', [
            'notas' => $notas,
            'certificados' => $certificados,
            'dependientes' => $dependientes,
            'pacienteFilter' => $pacienteFilter,
        ]);
    }

    public function show(NotaSoap $nota)
    {
        $pacienteId = Auth::id();

        $nota->load(['cita.paciente', 'cita.dependiente', 'cita.doctor', 'cita.especialidad', 'diagnosticos', 'enmiendas.autor']);

        if ($nota->estado !== NotaSoap::ESTADO_FIRMADA || $nota->cita->paciente_id !== $pacienteId) {
            abort(403);
        }

        if ($nota->cita->dependiente_id && (!$nota->cita->dependiente || (int)$nota->cita->dependiente->user_id !== (int)$pacienteId)) {
            abort(403);
        }

        return view('paciente.historial-show', [
            'nota' => $nota,
        ]);
    }
}
