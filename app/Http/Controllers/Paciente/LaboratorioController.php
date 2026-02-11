<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\LaboratorioOrden;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LaboratorioController extends Controller
{
    public function index()
    {
        $ordenes = LaboratorioOrden::with(['cita.doctor', 'cita.especialidad'])
            ->whereHas('cita', function ($q) {
                $q->where('paciente_id', Auth::id());
            })
            ->orderByDesc('id')
            ->paginate(12);

        return view('paciente.laboratorio', compact('ordenes'));
    }

    public function download(LaboratorioOrden $orden)
    {
        $orden->load('cita');
        if ($orden->cita->paciente_id !== Auth::id()) {
            abort(403);
        }

        if (!$orden->resultado_path || !Storage::exists($orden->resultado_path)) {
            return back()->withErrors(['error' => 'No hay resultados disponibles para descargar.']);
        }

        $name = 'resultado_laboratorio_'.$orden->id.'.pdf';
        return Storage::download($orden->resultado_path, $name);
    }
}
