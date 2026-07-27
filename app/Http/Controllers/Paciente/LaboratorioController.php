<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\PedidoLaboratorio;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class LaboratorioController extends Controller
{
    public function index()
    {
        $ordenes = $this->paginateTimeline((int) Auth::id(), 12);
        $pedidosLaboratorio = PedidoLaboratorio::with([
                'doctor',
                'cita.dependiente.responsable',
                'resultados' => function ($query) {
                    $query->orderByDesc('version');
                },
                'resultados.laboratorio',
            ])
            ->where('paciente_id', Auth::id())
            ->latest()
            ->get();

        return view('paciente.laboratorio', compact('ordenes', 'pedidosLaboratorio'));
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

    public function downloadAutoOrder(LabOrder $order)
    {
        if ((int) $order->patient_id !== (int) Auth::id()) {
            abort(403);
        }

        if (! $order->hasResultadoDisponible() || ! Storage::exists($order->resultado_path)) {
            return back()->withErrors(['error' => 'No hay resultados disponibles para descargar.']);
        }

        $name = 'resultado_laboratorio_solicitud_'.$order->id.'.pdf';

        return Storage::download($order->resultado_path, $name);
    }

    public function downloadPedido(PedidoLaboratorio $pedido)
    {
        if ((int) $pedido->paciente_id !== (int) Auth::id()) {
            abort(403);
        }

        $resultado = $pedido->resultados()
            ->where('estado', 'publicado')
            ->orderByDesc('version')
            ->first();

        if (! $resultado || ! $resultado->pdf_path || ! Storage::disk('local')->exists($resultado->pdf_path)) {
            return back()->withErrors(['error' => 'No hay resultados publicados para descargar.']);
        }

        return Storage::disk('local')->download(
            $resultado->pdf_path,
            'resultado_laboratorio_'.$pedido->id.'_v'.$resultado->version.'.pdf'
        );
    }

    private function paginateTimeline(int $patientId, int $perPage): LengthAwarePaginator
    {
        $legacyRows = LaboratorioOrden::query()
            ->join('citas_medicas', 'citas_medicas.id', '=', 'laboratorio_ordenes.cita_id')
            ->where('citas_medicas.paciente_id', $patientId)
            ->selectRaw("'legacy' as source")
            ->selectRaw('laboratorio_ordenes.id as source_id')
            ->selectRaw('COALESCE(laboratorio_ordenes.resultado_publicado_at, citas_medicas.fecha, laboratorio_ordenes.updated_at, laboratorio_ordenes.created_at) as sort_at');

        $selfServiceRows = LabOrder::query()
            ->where('patient_id', $patientId)
            ->selectRaw("'lab_order' as source")
            ->selectRaw('lab_orders.id as source_id')
            ->selectRaw('COALESCE(lab_orders.resultado_publicado_at, lab_orders.scheduled_at, lab_orders.created_at) as sort_at');

        $rows = DB::query()
            ->fromSub($legacyRows->unionAll($selfServiceRows), 'lab_timeline')
            ->orderByDesc('sort_at')
            ->orderByDesc('source_id')
            ->paginate($perPage)
            ->withQueryString();

        return $this->hydrateTimelineRows($rows);
    }

    private function hydrateTimelineRows(LengthAwarePaginator $rows): LengthAwarePaginator
    {
        $pageRows = $rows->getCollection();
        $legacyIds = $pageRows
            ->where('source', 'legacy')
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->all();
        $selfServiceIds = $pageRows
            ->where('source', 'lab_order')
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $legacyOrders = LaboratorioOrden::with(['cita.doctor', 'cita.especialidad'])
            ->whereIn('id', $legacyIds)
            ->get()
            ->keyBy('id');

        $selfServiceOrders = LabOrder::with(['doctor', 'laboratorio', 'items.test'])
            ->whereIn('id', $selfServiceIds)
            ->get()
            ->keyBy('id');

        $rows->setCollection($pageRows->map(function ($row) use ($legacyOrders, $selfServiceOrders) {
            if ($row->source === 'legacy') {
                $orden = $legacyOrders->get((int) $row->source_id);

                return $orden ? $this->mapLegacyOrder($orden) : null;
            }

            $order = $selfServiceOrders->get((int) $row->source_id);

            return $order ? $this->mapSelfServiceOrder($order) : null;
        })->filter()->values());

        return $rows;
    }

    private function mapLegacyOrder(LaboratorioOrden $orden): object
    {
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
                    'label' => 'Ver mis citas',
                    'url' => route('paciente.citas'),
                ],
            ],
        ];
    }

    private function mapSelfServiceOrder(LabOrder $order): object
    {
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
        $labLabel = $order->laboratorio?->name ? 'Procesa: '.$order->laboratorio->name : null;
        $details = array_values(array_filter([$sourceLabel, $priorityLabel, $labLabel], fn ($value) => filled($value)));
        $resultReady = $order->hasResultadoDisponible();

        return (object) [
            'uid' => 'lab-order-'.$order->id,
            'title' => $order->tipo_examen ?? 'Examen de laboratorio',
            'subtitle' => implode(' - ', $details),
            'date' => $order->resultado_publicado_at ?? $order->scheduled_at ?? $order->created_at,
            'date_label' => ($order->resultado_publicado_at ?? $order->scheduled_at ?? $order->created_at)?->format('Y/m/d H:i') ?? 'Sin fecha',
            'status_label' => $status[0],
            'status_tone' => $status[1],
            'summary' => $order->resultado_resumen ?: $order->doctor_notes,
            'preparation' => $order->preparacion,
            'notes' => $order->indicaciones,
            'primary_action_url' => $resultReady ? route('paciente.lab-orders.download', $order->id) : null,
            'primary_action_label' => $resultReady ? 'Descargar' : null,
            'secondary_actions' => [
                [
                    'label' => 'Agendar cita medica',
                    'url' => route('paciente.crear-cita'),
                ],
                [
                    'label' => 'Ver mis citas',
                    'url' => route('paciente.citas'),
                ],
            ],
        ];
    }
}
