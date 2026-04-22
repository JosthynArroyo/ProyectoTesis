<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CitaRecordatorio;
use App\Services\CitaNoShowService;
use App\Services\CitaRecordatorioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RecordatorioController extends Controller
{
    public function index(CitaRecordatorioService $recordatorios): View
    {
        app(CitaNoShowService::class)->marcarVencidas();

        $stats = $recordatorios->pendingDueStats();
        $pendientes = $recordatorios->pendingListQuery()
            ->paginate(12, ['*'], 'pendientes_page')
            ->through(function (CitaRecordatorio $recordatorio) use ($recordatorios) {
                $cita = $recordatorio->cita;
                $puedeGestionar = $recordatorios->canBeManaged($recordatorio);

                $recordatorio->setAttribute('telefono_normalizado', $recordatorios->normalizedPhoneForCita($cita));
                $recordatorio->setAttribute('mensaje_sugerido', $recordatorios->messageForCita($cita));
                $recordatorio->setAttribute('whatsapp_url', $recordatorios->whatsappUrlForCita($cita));
                $recordatorio->setAttribute('puede_gestionar', $puedeGestionar);

                return $recordatorio;
            });

        return view('admin.recordatorios.index', [
            'recordatorios' => $pendientes,
            'stats' => $stats,
            'reglaActiva' => $recordatorios->targetDescription(),
        ]);
    }

    public function enviados(CitaRecordatorioService $recordatorios): View
    {
        app(CitaNoShowService::class)->marcarVencidas();

        $enviados = $recordatorios->sentListQuery()
            ->paginate(10)
            ->through(function (CitaRecordatorio $recordatorio) use ($recordatorios) {
                $cita = $recordatorio->cita;

                $recordatorio->setAttribute('telefono_normalizado', $recordatorios->normalizedPhoneForCita($cita));

                return $recordatorio;
            });

        return view('admin.recordatorios.enviados', [
            'recordatoriosEnviados' => $enviados,
        ]);
    }

    public function marcarEnviado(Request $request, CitaRecordatorio $recordatorio, CitaRecordatorioService $recordatorios): RedirectResponse
    {
        if (! $recordatorios->markAsSent($recordatorio, $request->user())) {
            return back()->withErrors([
                'error' => 'El recordatorio aun no esta habilitado para gestionarse o la cita cambio antes de ser gestionada.',
            ]);
        }

        return back()->with('success', 'Recordatorio marcado como enviado.');
    }

    public function marcarOmitido(Request $request, CitaRecordatorio $recordatorio, CitaRecordatorioService $recordatorios): RedirectResponse
    {
        if (! $recordatorios->markAsSkipped($recordatorio, $request->user())) {
            return back()->withErrors([
                'error' => 'El recordatorio aun no esta habilitado para gestionarse o la cita cambio antes de ser gestionada.',
            ]);
        }

        return back()->with('success', 'Recordatorio marcado como omitido.');
    }
}
