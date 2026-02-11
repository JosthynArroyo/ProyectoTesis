<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\NotaSoap;
use App\Models\User;
use Illuminate\Http\Request;

class HistorialController extends Controller
{
    public function index(Request $request)
    {
        $q = trim((string) $request->get('q', ''));
        $pacienteId = $request->integer('paciente_id') ?: null;

        $query = NotaSoap::query()
            ->select('notas_soap.*')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->join('users as pacientes', 'citas_medicas.paciente_id', '=', 'pacientes.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->when($pacienteId, fn ($qq) => $qq->where('citas_medicas.paciente_id', $pacienteId))
            ->when($q !== '', function ($qq) use ($q) {
                $like = '%'.$q.'%';
                $qq->where(function ($w) use ($like) {
                    $w->where('pacientes.name', 'like', $like)
                      ->orWhere('pacientes.dni', 'like', $like)
                      ->orWhere('pacientes.email', 'like', $like);
                });
            })
            ->orderBy('citas_medicas.fecha', 'desc')
            ->orderBy('citas_medicas.hora', 'desc')
            ->with(['cita.paciente', 'cita.doctor', 'cita.especialidad']);

        $notas = $query->paginate(15)->withQueryString();

        return view('admin.historial.index', [
            'notas' => $notas,
            'q' => $q,
            'pacienteId' => $pacienteId,
        ]);
    }

    public function paciente(User $paciente)
    {
        $notas = NotaSoap::query()
            ->select('notas_soap.*')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->where('citas_medicas.paciente_id', $paciente->id)
            ->orderBy('citas_medicas.fecha', 'desc')
            ->orderBy('citas_medicas.hora', 'desc')
            ->with(['cita.doctor', 'cita.especialidad'])
            ->paginate(15);

        return view('admin.historial.paciente', [
            'paciente' => $paciente,
            'notas' => $notas,
        ]);
    }

    public function show(NotaSoap $nota)
    {
        $nota->load(['cita.paciente', 'cita.doctor', 'cita.especialidad', 'diagnosticos', 'enmiendas.autor']);

        if ($nota->estado !== NotaSoap::ESTADO_FIRMADA) {
            abort(404);
        }

        return view('admin.historial.show', [
            'nota' => $nota,
        ]);
    }
}
