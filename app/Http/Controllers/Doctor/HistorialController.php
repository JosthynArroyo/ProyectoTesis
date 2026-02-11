<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\NotaSoap;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class HistorialController extends Controller
{
    public function show(User $paciente)
    {
        $doctorId = Auth::id();

        $haAtendido = Cita::where('doctor_id', $doctorId)
            ->where('paciente_id', $paciente->id)
            ->exists();

        if (! $haAtendido) {
            abort(403);
        }

        $notas = NotaSoap::query()
            ->select('notas_soap.*')
            ->join('citas_medicas', 'notas_soap.cita_id', '=', 'citas_medicas.id')
            ->where('notas_soap.estado', NotaSoap::ESTADO_FIRMADA)
            ->where('citas_medicas.paciente_id', $paciente->id)
            ->where('citas_medicas.doctor_id', $doctorId)
            ->orderBy('citas_medicas.fecha', 'desc')
            ->orderBy('citas_medicas.hora', 'desc')
            ->with(['cita.especialidad', 'cita.doctor', 'diagnosticos'])
            ->paginate(10);

        return view('doctor.paciente-historial', [
            'paciente' => $paciente,
            'notas' => $notas,
        ]);
    }
}
