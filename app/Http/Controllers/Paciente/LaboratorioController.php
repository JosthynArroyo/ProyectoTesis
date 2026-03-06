<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class LaboratorioController extends Controller
{
    public function index()
    {
        $ordenesTradicionales = LaboratorioOrden::with(['cita.doctor', 'cita.especialidad'])
            ->whereHas('cita', function ($query) {
                $query->where('paciente_id', Auth::id());
            })
            ->orderByDesc('id')
            ->get();

        $autoOrdenes = LabOrder::with(['doctor', 'items.test'])
            ->where('patient_id', Auth::id())
            ->orderByDesc('created_at')
            ->get();

        $items = $this->buildTimelineItems($ordenesTradicionales, $autoOrdenes);
        $ordenes = $this->paginateItems($items, 12);

        return view('paciente.laboratorio', compact('ordenes'));
    }

    public function download(LaboratorioOrden $orden)
    {
        $orden->load('cita');
        if ($orden->cita->paciente_id !== Auth::id()) {
            abort(403);
        }

        if (! $orden->resultado_path || ! Storage::exists($orden->resultado_path)) {
            return back()->withErrors(['error' => 'No hay resultados disponibles para descargar.']);
        }

        $name = 'resultado_laboratorio_'.$orden->id.'.pdf';

        return Storage::download($orden->resultado_path, $name);
    }

    private function buildTimelineItems(Collection $ordenesTradicionales, Collection $autoOrdenes): Collection
    {
        $legacy = $ordenesTradicionales->map(function (LaboratorioOrden $orden) {
            $sortAt = $orden->resultado_publicado_at
                ?? optional($orden->cita)->fecha
                ?? $orden->updated_at
                ?? $orden->created_at;

            $statusMap = [
                LaboratorioOrden::ESTADO_ORDEN_CREADA => ['Orden creada', 'warning'],
                LaboratorioOrden::ESTADO_CITA_PROGRAMADA => ['Cita programada', 'info'],
                LaboratorioOrden::ESTADO_MUESTRA_TOMADA => ['Muestra tomada', 'info'],
                LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => ['Resultado disponible', 'success'],
            ];

            $status = $statusMap[$orden->estado] ?? ['En proceso', 'info'];

            return (object) [
                'uid' => 'legacy-'.$orden->id,
                'title' => $orden->tipo_examen ?: 'Examen de laboratorio',
                'subtitle' => optional(optional($orden->cita)->doctor)->name ?? 'Laboratorio',
                'date' => $sortAt ? Carbon::parse($sortAt) : null,
                'date_label' => $sortAt ? Carbon::parse($sortAt)->format('Y/m/d H:i') : 'Sin fecha',
                'status_label' => $status[0],
                'status_tone' => $status[1],
                'summary' => $orden->resultado_resumen,
                'preparation' => $orden->preparacion,
                'notes' => $orden->indicaciones,
                'primary_action_url' => $orden->resultado_path ? route('paciente.laboratorio.download', $orden->id) : null,
                'primary_action_label' => $orden->resultado_path ? 'Descargar' : null,
                'secondary_actions' => [
                    [
                        'label' => 'Agendar cita medica',
                        'url' => route('paciente.crear-cita'),
                    ],
                    [
                        'label' => 'Solicitar examen',
                        'url' => route('paciente.laboratorio.solicitar'),
                    ],
                ],
            ];
        });

        $auto = $autoOrdenes->map(function (LabOrder $order) {
            $item = $order->items->first();
            $statusMap = [
                LabOrder::STATUS_PENDIENTE_TOMA => ['Pendiente de toma', 'warning'],
                LabOrder::STATUS_MUESTRA_TOMADA => ['Muestra tomada', 'info'],
                LabOrder::STATUS_EN_ANALISIS => ['En analisis', 'info'],
                LabOrder::STATUS_RESULTADO_LISTO => ['Resultado listo', 'success'],
                LabOrder::STATUS_CANCELADO => ['Cancelado', 'danger'],
                LabOrder::STATUS_NO_SE_PRESENTO => ['No se presento', 'danger'],
            ];
            $status = $statusMap[$order->status] ?? ['En proceso', 'info'];
            $priority = trim((string) $order->priority);
            $priorityLabel = $priority !== '' ? ucfirst(str_replace('_', ' ', $priority)) : null;
            $sourceLabel = $order->source === LabOrder::SOURCE_MEDICAL_ORDER ? 'Con orden medica' : 'Rutina';
            $details = array_values(array_filter([$sourceLabel, $priorityLabel], fn ($value) => filled($value)));

            return (object) [
                'uid' => 'lab-order-'.$order->id,
                'title' => $item?->test?->nombre ?? 'Examen de laboratorio',
                'subtitle' => implode(' - ', $details),
                'date' => $order->scheduled_at ?? $order->created_at,
                'date_label' => ($order->scheduled_at ?? $order->created_at)?->format('Y/m/d H:i') ?? 'Sin fecha',
                'status_label' => $status[0],
                'status_tone' => $status[1],
                'summary' => $order->doctor_notes,
                'preparation' => $item?->preparacion_snapshot,
                'notes' => $item?->indicaciones_snapshot,
                'primary_action_url' => null,
                'primary_action_label' => null,
                'secondary_actions' => [
                    [
                        'label' => 'Agendar cita medica',
                        'url' => route('paciente.crear-cita'),
                    ],
                    [
                        'label' => 'Solicitar otro examen',
                        'url' => route('paciente.laboratorio.solicitar'),
                    ],
                ],
            ];
        });

        return $legacy
            ->concat($auto)
            ->sortByDesc(fn ($item) => optional($item->date)?->timestamp ?? 0)
            ->values();
    }

    private function paginateItems(Collection $items, int $perPage): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage();
        $slice = $items->slice(($page - 1) * $perPage, $perPage)->values();

        return new LengthAwarePaginator(
            $slice,
            $items->count(),
            $perPage,
            $page,
            [
                'path' => request()->url(),
                'query' => request()->query(),
            ]
        );
    }
}
