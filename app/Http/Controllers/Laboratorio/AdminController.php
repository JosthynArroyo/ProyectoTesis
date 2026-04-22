<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use Carbon\Carbon;
use Illuminate\Support\Collection;
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
        $legacyPendientes = LaboratorioOrden::whereHas('cita', function ($query) use ($user) {
            $query->where('doctor_id', $user->id);
        })->where('estado', LaboratorioOrden::ESTADO_CITA_PROGRAMADA)->count();
        $selfServicePendientes = LabOrder::query()
            ->where(function ($query) use ($user) {
                $query->whereNull('laboratorio_id')
                    ->orWhere('laboratorio_id', $user->id);
            })
            ->where('status', LabOrder::STATUS_PENDIENTE_TOMA)
            ->count();
        $ordenesPendientes = $legacyPendientes + $selfServicePendientes;

        $legacyResultadosHoy = LaboratorioOrden::whereHas('cita', function ($query) use ($user) {
            $query->where('doctor_id', $user->id);
        })->whereDate('resultado_publicado_at', $hoy)->count();
        $selfServiceResultadosHoy = LabOrder::query()
            ->where('laboratorio_id', $user->id)
            ->whereDate('resultado_publicado_at', $hoy)
            ->count();
        $resultadosHoy = $legacyResultadosHoy + $selfServiceResultadosHoy;

        $legacyRecientes = LaboratorioOrden::with(['cita.paciente'])
            ->whereHas('cita', function ($query) use ($user) {
                $query->where('doctor_id', $user->id);
            })
            ->orderByDesc('id')
            ->limit(6)
            ->get();
        $selfServiceRecientes = LabOrder::with(['patient', 'items.test'])
            ->where(function ($query) use ($user) {
                $query->whereNull('laboratorio_id')
                    ->orWhere('laboratorio_id', $user->id);
            })
            ->orderByDesc('created_at')
            ->limit(6)
            ->get();

        $ordenesRecientes = $this->mapRecentOrders($legacyRecientes, $selfServiceRecientes)
            ->sortByDesc(fn ($item) => optional($item->sort_at)?->timestamp ?? 0)
            ->take(8)
            ->values();

        return view('laboratorio.dashboard', compact(
            'user',
            'citasHoy',
            'ordenesPendientes',
            'resultadosHoy',
            'ordenesRecientes'
        ));
    }

    private function mapRecentOrders(Collection $legacyOrders, Collection $selfServiceOrders): Collection
    {
        $legacy = $legacyOrders->map(function (LaboratorioOrden $orden) {
            $date = $orden->resultado_publicado_at ?? optional($orden->cita)->fecha ?? $orden->created_at;

            return (object) [
                'patient_name' => optional($orden->cita->paciente)->name ?? 'Paciente',
                'exam_name' => $orden->tipo_examen,
                'status_label' => str_replace('_', ' ', $orden->estado),
                'badge_tone' => match ($orden->estado) {
                    LaboratorioOrden::ESTADO_ORDEN_CREADA => 'warning',
                    LaboratorioOrden::ESTADO_CITA_PROGRAMADA => 'info',
                    LaboratorioOrden::ESTADO_MUESTRA_TOMADA => 'neutral',
                    LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => 'success',
                    default => 'neutral',
                },
                'date_label' => $date?->format('d/m/Y') ?? 'Sin fecha',
                'action_label' => $orden->resultado_path ? 'Ver resultado' : 'Gestionar',
                'action_url' => route('laboratorio.ordenes.index').'#legacy-'.$orden->id,
                'sort_at' => $date,
            ];
        });

        $selfService = $selfServiceOrders->map(function (LabOrder $order) {
            $date = $order->resultado_publicado_at ?? $order->scheduled_at ?? $order->created_at;

            return (object) [
                'patient_name' => $order->patient?->name ?? 'Paciente',
                'exam_name' => $order->tipo_examen ?? 'Examen de laboratorio',
                'status_label' => str_replace('_', ' ', $order->status),
                'badge_tone' => match ($order->status) {
                    LabOrder::STATUS_PENDIENTE_TOMA => 'warning',
                    LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS => 'info',
                    LabOrder::STATUS_RESULTADO_LISTO => 'success',
                    default => 'neutral',
                },
                'date_label' => $date?->format('d/m/Y') ?? 'Sin fecha',
                'action_label' => $order->hasResultadoDisponible() ? 'Ver resultado' : 'Gestionar',
                'action_url' => route('laboratorio.ordenes.index').'#lab-order-'.$order->id,
                'sort_at' => $date,
            ];
        });

        return $legacy->concat($selfService);
    }
}
