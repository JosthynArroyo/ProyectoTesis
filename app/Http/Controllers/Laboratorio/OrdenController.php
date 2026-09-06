<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Mail\ResultadoLaboratorioMail;
use App\Models\LabOrder;
use App\Models\LaboratorioOrden;
use App\Models\PedidoLaboratorio;
use App\Services\LaboratoryResultStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

class OrdenController extends Controller
{
    public function index(Request $request)
    {
        $estado = strtolower(trim((string) $request->query('estado', 'all')));
        $allowed = [
            'all',
            LaboratorioOrden::ESTADO_ORDEN_CREADA,
            LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
            LaboratorioOrden::ESTADO_MUESTRA_TOMADA,
            LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE,
        ];
        if (! in_array($estado, $allowed, true)) {
            $estado = 'all';
        }

        $pedidoCitaIds = DB::table('pedidos_laboratorio')
            ->whereNotNull('cita_id')
            ->pluck('cita_id');

        $legacyRows = LaboratorioOrden::query()
            ->join('citas_medicas', 'citas_medicas.id', '=', 'laboratorio_ordenes.cita_id')
            ->when($pedidoCitaIds->isNotEmpty(), function ($query) use ($pedidoCitaIds) {
                $query->whereNotIn('laboratorio_ordenes.cita_id', $pedidoCitaIds);
            })
            ->when($estado !== 'all', function ($query) use ($estado) {
                $query->where('laboratorio_ordenes.estado', $estado);
            })
            ->selectRaw("'legacy' as source")
            ->selectRaw('laboratorio_ordenes.id as source_id')
            ->selectRaw('COALESCE(laboratorio_ordenes.resultado_publicado_at, citas_medicas.fecha, laboratorio_ordenes.updated_at, laboratorio_ordenes.created_at) as sort_at');

        $selfServiceRows = LabOrder::query()
            ->where(function ($query) {
                $query->whereNull('laboratorio_id')
                    ->orWhere('laboratorio_id', Auth::id());
            })
            ->when($estado !== 'all', function ($query) use ($estado) {
                match ($estado) {
                    LaboratorioOrden::ESTADO_CITA_PROGRAMADA => $query->where('status', LabOrder::STATUS_PENDIENTE_TOMA),
                    LaboratorioOrden::ESTADO_MUESTRA_TOMADA => $query->whereIn('status', [
                        LabOrder::STATUS_MUESTRA_TOMADA,
                        LabOrder::STATUS_EN_ANALISIS,
                    ]),
                    LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => $query->where('status', LabOrder::STATUS_RESULTADO_LISTO),
                    LaboratorioOrden::ESTADO_ORDEN_CREADA => $query->whereRaw('1 = 0'),
                    default => null,
                };
            }, function ($query) {
                $query->whereIn('status', [
                    LabOrder::STATUS_PENDIENTE_TOMA,
                    LabOrder::STATUS_MUESTRA_TOMADA,
                    LabOrder::STATUS_EN_ANALISIS,
                    LabOrder::STATUS_RESULTADO_LISTO,
                ]);
            })
            ->selectRaw("'lab_order' as source")
            ->selectRaw('lab_orders.id as source_id')
            ->selectRaw('COALESCE(lab_orders.resultado_publicado_at, lab_orders.scheduled_at, lab_orders.created_at) as sort_at');

        $pedidoRows = PedidoLaboratorio::query()
            ->when($estado !== 'all', function ($query) use ($estado) {
                match ($estado) {
                    LaboratorioOrden::ESTADO_CITA_PROGRAMADA => $query->where('estado', PedidoLaboratorio::ESTADO_PENDIENTE_TOMA),
                    LaboratorioOrden::ESTADO_MUESTRA_TOMADA => $query->where('estado', PedidoLaboratorio::ESTADO_MUESTRA_TOMADA),
                    LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => $query->where('estado', PedidoLaboratorio::ESTADO_RESULTADO_LISTO),
                    LaboratorioOrden::ESTADO_ORDEN_CREADA => $query->whereRaw('1 = 0'),
                    default => null,
                };
            }, function ($query) {
                $query->whereIn('estado', [
                    PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
                    PedidoLaboratorio::ESTADO_MUESTRA_TOMADA,
                    PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
                ]);
            })
            ->selectRaw("'pedido_laboratorio' as source")
            ->selectRaw('pedidos_laboratorio.id as source_id')
            ->selectRaw('COALESCE(pedidos_laboratorio.resultado_publicado_at, pedidos_laboratorio.sample_collected_at, pedidos_laboratorio.updated_at, pedidos_laboratorio.created_at) as sort_at');

        $worklistQuery = $legacyRows->unionAll($selfServiceRows)->unionAll($pedidoRows);

        $ordenes = DB::query()
            ->fromSub($worklistQuery, 'lab_worklist')
            ->orderByDesc('sort_at')
            ->orderByDesc('source_id')
            ->paginate(12)
            ->withQueryString();

        $ordenes = $this->hydrateWorklistRows($ordenes);

        return view('laboratorio.ordenes.index', compact('ordenes', 'estado'));
    }

    public function marcarMuestra(LaboratorioOrden $orden)
    {
        $orden = DB::transaction(function () use ($orden) {
            $orden = LaboratorioOrden::query()
                ->with('cita')
                ->whereKey($orden->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeLaboratorioOrden($orden);

            if ($orden->estado === LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE) {
                return null;
            }

            if ($orden->estado !== LaboratorioOrden::ESTADO_MUESTRA_TOMADA) {
                $orden->estado = LaboratorioOrden::ESTADO_MUESTRA_TOMADA;
                $orden->save();
            }

            return $orden;
        });

        if (! $orden) {
            return back()->withErrors(['error' => 'Los resultados ya fueron publicados.']);
        }

        return back()->with('success', 'Muestra registrada.');
    }

    public function marcarMuestraAutoOrder(LabOrder $labOrder)
    {
        $labOrder = DB::transaction(function () use ($labOrder) {
            $labOrder = LabOrder::query()
                ->whereKey($labOrder->id)
                ->lockForUpdate()
                ->firstOrFail();

            $this->authorizeSelfServiceOrder($labOrder);

            if ($labOrder->status === LabOrder::STATUS_RESULTADO_LISTO) {
                return null;
            }

            if ($labOrder->status !== LabOrder::STATUS_MUESTRA_TOMADA) {
                $labOrder->forceFill([
                    'laboratorio_id' => Auth::id(),
                    'scheduled_at' => $labOrder->scheduled_at ?? now(config('app.timezone', 'America/Guayaquil')),
                    'status' => LabOrder::STATUS_MUESTRA_TOMADA,
                ])->save();
            }

            return $labOrder;
        });

        if (! $labOrder) {
            return back()->withErrors(['error' => 'Los resultados ya fueron publicados.']);
        }

        return back()->with('success', 'Muestra registrada para la solicitud del paciente.');
    }

    public function subirResultado(Request $request, LaboratorioOrden $orden, LaboratoryResultStorageService $resultStorage)
    {
        $orden->load(['cita.paciente', 'cita.doctor', 'cita.especialidad']);
        $this->authorizeLaboratorioOrden($orden);

        $data = $request->validate(
            [
                'resultado_pdf' => 'required|file|mimes:pdf|max:5120',
                'resultado_resumen' => 'required|string|max:2000',
            ],
            [
                'resultado_pdf.required' => 'Adjunta el PDF de resultados.',
            ]
        );

        $path = $resultStorage->storeUploadedPdf($request->file('resultado_pdf'), 'legacy-orders', $orden->id);

        try {
            $orden = DB::transaction(function () use ($orden, $path, $data, $resultStorage) {
                $orden = LaboratorioOrden::query()
                    ->with(['cita.paciente', 'cita.doctor', 'cita.especialidad'])
                    ->whereKey($orden->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeLaboratorioOrden($orden);

                if ($orden->estado === LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE) {
                    $resultStorage->deleteNew($path);

                    return null;
                }

                $orden->resultado_path = $path;
                $orden->resultado_resumen = $data['resultado_resumen'] ?? null;
                $orden->resultado_publicado_at = now('America/Guayaquil');
                $orden->estado = LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE;
                $orden->save();

                return $orden->refresh();
            });
        } catch (\Throwable $exception) {
            $resultStorage->deleteNew($path);
            throw $exception;
        }

        if (! $orden) {
            return back()->withErrors(['error' => 'Los resultados ya fueron publicados.']);
        }

        if ($orden->cita && $orden->cita->paciente && $orden->cita->paciente->email && ! $orden->resultado_enviado_at) {
            Mail::to($orden->cita->paciente->email)->send(new ResultadoLaboratorioMail($orden));
            $orden->resultado_enviado_at = now('America/Guayaquil');
            $orden->save();
        }

        return back()->with('success', 'Resultados subidos y notificados al paciente.');
    }

    public function subirResultadoAutoOrder(Request $request, LabOrder $labOrder, LaboratoryResultStorageService $resultStorage)
    {
        $this->authorizeSelfServiceOrder($labOrder);
        $labOrder->loadMissing(['patient', 'doctor', 'items']);

        $data = $request->validate(
            [
                'resultado_pdf' => 'required|file|mimes:pdf|max:5120',
                'resultado_resumen' => 'required|string|max:2000',
            ],
            [
                'resultado_pdf.required' => 'Adjunta el PDF de resultados.',
            ]
        );

        $path = $resultStorage->storeUploadedPdf($request->file('resultado_pdf'), 'self-service-orders', $labOrder->id);

        try {
            $labOrder = DB::transaction(function () use ($labOrder, $path, $data, $resultStorage) {
                $labOrder = LabOrder::query()
                    ->with(['patient', 'doctor', 'items'])
                    ->whereKey($labOrder->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->authorizeSelfServiceOrder($labOrder);

                if ($labOrder->status === LabOrder::STATUS_RESULTADO_LISTO) {
                    $resultStorage->deleteNew($path);

                    return null;
                }

                $labOrder->forceFill([
                    'laboratorio_id' => Auth::id(),
                    'scheduled_at' => $labOrder->scheduled_at ?? now(config('app.timezone', 'America/Guayaquil')),
                    'resultado_path' => $path,
                    'resultado_resumen' => $data['resultado_resumen'],
                    'resultado_publicado_at' => now('America/Guayaquil'),
                    'status' => LabOrder::STATUS_RESULTADO_LISTO,
                ])->save();

                return $labOrder->refresh();
            });
        } catch (\Throwable $exception) {
            $resultStorage->deleteNew($path);
            throw $exception;
        }

        if (! $labOrder) {
            return back()->withErrors(['error' => 'Los resultados ya fueron publicados.']);
        }

        if ($labOrder->patient && $labOrder->patient->email && ! $labOrder->resultado_enviado_at) {
            Mail::to($labOrder->patient->email)->send(new ResultadoLaboratorioMail($labOrder));
            $labOrder->resultado_enviado_at = now('America/Guayaquil');
            $labOrder->save();
        }

        $item = $labOrder->items()->first();
        if ($item) {
            $item->update(['resultado_url' => $path]);
        }

        return back()->with('success', 'Resultados subidos y notificados al paciente.');
    }

    public function download(LaboratorioOrden $orden, LaboratoryResultStorageService $resultStorage)
    {
        $orden->load('cita');
        $this->authorizeLaboratorioOrden($orden);

        if (! $orden->resultado_path || ! $resultStorage->resolve($orden->resultado_path)) {
            return back()->withErrors(['error' => 'No hay resultados disponibles para descargar.']);
        }

        $name = 'resultado_laboratorio_'.$orden->id.'.pdf';

        return $resultStorage->download($orden->resultado_path, $name);
    }

    public function downloadAutoOrder(LabOrder $labOrder, LaboratoryResultStorageService $resultStorage)
    {
        $this->authorizeSelfServiceOrder($labOrder);

        if (! $labOrder->hasResultadoDisponible() || ! $resultStorage->resolve($labOrder->resultado_path)) {
            return back()->withErrors(['error' => 'No hay resultados disponibles para descargar.']);
        }

        $name = 'resultado_laboratorio_solicitud_'.$labOrder->id.'.pdf';

        return $resultStorage->download($labOrder->resultado_path, $name);
    }

    private function authorizeLaboratorioOrden(LaboratorioOrden $orden): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasAnyRole(['laboratorio', 'admin', 'superadmin'])) {
            return;
        }

        if ((int) $user->id === (int) optional($orden->cita)->doctor_id || (int) $user->id === (int) $orden->solicitante_id) {
            return;
        }

        abort(403);
    }

    private function authorizeSelfServiceOrder(LabOrder $labOrder): void
    {
        $user = Auth::user();
        if (! $user) {
            abort(401);
        }

        if ($user->hasAnyRole(['laboratorio', 'admin', 'superadmin'])) {
            if ($labOrder->laboratorio_id !== null && (int) $labOrder->laboratorio_id !== (int) $user->id) {
                abort(403);
            }

            return;
        }

        if ((int) $user->id === (int) $labOrder->doctor_id || (int) $user->id === (int) $labOrder->patient_id) {
            return;
        }

        abort(403);
    }

    private function mapLegacyOrder(LaboratorioOrden $orden): object
    {
        $date = $orden->resultado_publicado_at
            ?? optional($orden->cita)->fecha
            ?? $orden->updated_at
            ?? $orden->created_at;
        $hora = $orden->cita?->hora ? Carbon::parse($orden->cita->hora)->format('H:i') : null;
        $patientName = $orden->cita?->nombrePacienteReal()
            ?? optional($orden->cita->paciente)->name
            ?? 'Paciente';

        return (object) [
            'uid' => 'legacy-'.$orden->id,
            'title' => $orden->tipo_examen,
            'patient_name' => $patientName,
            'status_label' => str_replace('_', ' ', $orden->estado),
            'badge_tone' => match ($orden->estado) {
                LaboratorioOrden::ESTADO_ORDEN_CREADA => 'warning',
                LaboratorioOrden::ESTADO_CITA_PROGRAMADA => 'info',
                LaboratorioOrden::ESTADO_MUESTRA_TOMADA => 'neutral',
                LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE => 'success',
                default => 'neutral',
            },
            'priority_label' => ucfirst((string) $orden->prioridad),
            'date_label' => $date ? trim($date->format('Y/m/d').' '.($hora ?? '')) : 'Sin fecha',
            'source_label' => 'Orden con cita',
            'preparation' => $orden->preparacion,
            'notes' => $orden->indicaciones,
            'result_summary' => $orden->resultado_resumen,
            'download_url' => $orden->resultado_path ? route('laboratorio.ordenes.download', $orden->id) : null,
            'mark_sample_url' => route('laboratorio.ordenes.muestra', $orden->id),
            'upload_result_url' => route('laboratorio.ordenes.resultado', $orden->id),
            'can_mark_sample' => $orden->estado !== LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE,
            'can_upload_result' => $orden->estado !== LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE,
            'sort_at' => $date,
        ];
    }

    private function mapSelfServiceOrder(LabOrder $order): object
    {
        $date = $order->resultado_publicado_at ?? $order->scheduled_at ?? $order->created_at;

        return (object) [
            'uid' => 'lab-order-'.$order->id,
            'title' => $order->tipo_examen ?? 'Examen de laboratorio',
            'patient_name' => $order->patient?->name ?? 'Paciente',
            'status_label' => str_replace('_', ' ', $order->status),
            'badge_tone' => match ($order->status) {
                LabOrder::STATUS_PENDIENTE_TOMA => 'warning',
                LabOrder::STATUS_MUESTRA_TOMADA, LabOrder::STATUS_EN_ANALISIS => 'info',
                LabOrder::STATUS_RESULTADO_LISTO => 'success',
                default => 'neutral',
            },
            'priority_label' => ucfirst((string) $order->priority),
            'date_label' => $date?->format('Y/m/d H:i') ?? 'Sin fecha',
            'source_label' => $order->source === LabOrder::SOURCE_MEDICAL_ORDER
                ? 'Con orden medica'
                : 'Rutina',
            'preparation' => $order->preparacion,
            'notes' => $order->indicaciones,
            'result_summary' => $order->resultado_resumen ?: $order->doctor_notes,
            'download_url' => $order->hasResultadoDisponible() ? route('laboratorio.lab-orders.download', $order->id) : null,
            'mark_sample_url' => route('laboratorio.lab-orders.muestra', $order->id),
            'upload_result_url' => route('laboratorio.lab-orders.resultado', $order->id),
            'can_mark_sample' => $order->status === LabOrder::STATUS_PENDIENTE_TOMA,
            'can_upload_result' => $order->status !== LabOrder::STATUS_RESULTADO_LISTO,
            'sort_at' => $date,
        ];
    }

    private function mapPedidoLaboratorio(PedidoLaboratorio $pedido): object
    {
        $date = $pedido->resultado_publicado_at
            ?? $pedido->sample_collected_at
            ?? optional($pedido->cita)->fecha
            ?? $pedido->updated_at
            ?? $pedido->created_at;
        $hora = $pedido->cita?->hora ? Carbon::parse($pedido->cita->hora)->format('H:i') : null;
        $examList = is_array($pedido->examenes)
            ? implode(', ', array_map(fn ($e) => ucwords(str_replace('_', ' ', $e)), $pedido->examenes))
            : 'Exámenes de laboratorio';
        $patientName = $pedido->cita?->nombrePacienteReal()
            ?? $pedido->paciente?->name
            ?? 'Paciente';

        return (object) [
            'uid' => 'pedido-'.$pedido->id,
            'title' => $examList,
            'patient_name' => $patientName,
            'status_label' => match ($pedido->estado) {
                PedidoLaboratorio::ESTADO_PENDIENTE_TOMA => 'Pendiente de toma',
                PedidoLaboratorio::ESTADO_MUESTRA_TOMADA => 'Muestra tomada / análisis',
                PedidoLaboratorio::ESTADO_RESULTADO_LISTO => 'Resultado listo',
                default => str_replace('_', ' ', (string) $pedido->estado),
            },
            'badge_tone' => match ($pedido->estado) {
                PedidoLaboratorio::ESTADO_PENDIENTE_TOMA => 'warning',
                PedidoLaboratorio::ESTADO_MUESTRA_TOMADA => 'info',
                PedidoLaboratorio::ESTADO_RESULTADO_LISTO => 'success',
                default => 'neutral',
            },
            'priority_label' => 'Normal',
            'date_label' => $date ? trim($date->format('Y/m/d').' '.($hora ?? '')) : 'Sin fecha',
            'source_label' => 'Pedido médico',
            'preparation' => null,
            'notes' => null,
            'result_summary' => $pedido->resultado_resumen,
            'download_url' => ($pedido->resultado_path || ($pedido->relationLoaded('resultados') && $pedido->resultados->where('estado', 'publicado')->isNotEmpty()) || $pedido->estado === PedidoLaboratorio::ESTADO_RESULTADO_LISTO)
                ? route('laboratorio.pedidos.download-resultado', $pedido->id)
                : null,
            'mark_sample_url' => route('laboratorio.pedidos.muestra', $pedido->id),
            'upload_result_url' => route('laboratorio.pedidos.resultado', $pedido->id),
            'can_mark_sample' => $pedido->estado === PedidoLaboratorio::ESTADO_PENDIENTE_TOMA,
            'can_upload_result' => $pedido->estado !== PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
            'sort_at' => $date,
        ];
    }

    private function hydrateWorklistRows(LengthAwarePaginator $rows): LengthAwarePaginator
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
        $pedidoIds = $pageRows
            ->where('source', 'pedido_laboratorio')
            ->pluck('source_id')
            ->map(fn ($id) => (int) $id)
            ->all();

        $legacyOrders = LaboratorioOrden::with(['cita.paciente', 'cita.dependiente', 'cita.doctor', 'cita.especialidad'])
            ->whereIn('id', $legacyIds)
            ->get()
            ->keyBy('id');

        $selfServiceOrders = LabOrder::with(['patient', 'doctor', 'laboratorio', 'items.test'])
            ->whereIn('id', $selfServiceIds)
            ->get()
            ->keyBy('id');

        $pedidoOrders = PedidoLaboratorio::with(['cita.paciente', 'cita.dependiente', 'doctor', 'paciente', 'resultados'])
            ->whereIn('id', $pedidoIds)
            ->get()
            ->keyBy('id');

        $rows->setCollection($pageRows->map(function ($row) use ($legacyOrders, $selfServiceOrders, $pedidoOrders) {
            if ($row->source === 'legacy') {
                $orden = $legacyOrders->get((int) $row->source_id);

                return $orden ? $this->mapLegacyOrder($orden) : null;
            }

            if ($row->source === 'pedido_laboratorio') {
                $pedido = $pedidoOrders->get((int) $row->source_id);

                return $pedido ? $this->mapPedidoLaboratorio($pedido) : null;
            }

            $order = $selfServiceOrders->get((int) $row->source_id);

            return $order ? $this->mapSelfServiceOrder($order) : null;
        })->filter()->values());

        return $rows;
    }
}
