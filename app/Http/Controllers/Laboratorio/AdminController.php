<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\LaboratorioOrden;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    public function dashboard()
    {
        $user = Auth::user();
        $tz = 'America/Guayaquil';
        $hoy = Carbon::now($tz)->toDateString();

        $base = Cita::query()->where('doctor_id', $user->id);

        $citasHoy = (clone $base)->whereDate('fecha', $hoy)->count();
        $ordenesPendientes = LaboratorioOrden::whereHas('cita', function ($q) use ($user) {
            $q->where('doctor_id', $user->id);
        })->where('estado', LaboratorioOrden::ESTADO_CITA_PROGRAMADA)->count();

        $resultadosHoy = LaboratorioOrden::whereHas('cita', function ($q) use ($user) {
            $q->where('doctor_id', $user->id);
        })->whereDate('resultado_publicado_at', $hoy)->count();

        $ordenesRecientes = LaboratorioOrden::with(['cita.paciente', 'cita.doctor'])
            ->whereHas('cita', function ($q) use ($user) {
                $q->where('doctor_id', $user->id);
            })
            ->orderByDesc('id')
            ->limit(8)
            ->get();

        return view('laboratorio.dashboard', compact(
            'user',
            'citasHoy',
            'ordenesPendientes',
            'resultadosHoy',
            'ordenesRecientes'
        ));
    }
}
