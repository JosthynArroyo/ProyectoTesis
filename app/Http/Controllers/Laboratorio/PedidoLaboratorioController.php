<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\PedidoLaboratorio;
use App\Services\LaboratoryResultStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class PedidoLaboratorioController extends Controller
{
    /**
     * Lista los pedidos de laboratorio.
     */
    public function index(Request $request)
    {
        $estado = strtolower(trim((string) $request->query('estado', 'all')));

        $pedidos = PedidoLaboratorio::with([
                'paciente',
                'doctor',
                'resultados' => function ($query) {
                    $query->orderByDesc('version');
                },
                'resultados.laboratorio',
            ])
            ->when($estado !== 'all', function ($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('laboratorio.pedidos.index', compact('pedidos', 'estado'));
    }

    /**
     * Marca la muestra como tomada.
     */
    public function marcarMuestra(Request $request, PedidoLaboratorio $pedido)
    {
        $data = $request->validate([
            'sample_collected_at' => ['nullable', 'date'],
        ], [
            'sample_collected_at.date' => 'La fecha de toma de muestra no tiene un formato válido.',
        ]);

        $transitioned = DB::transaction(function () use ($pedido, $data) {
            $locked = PedidoLaboratorio::where('id', $pedido->id)->lockForUpdate()->first();
            if (! $locked || $locked->estado === PedidoLaboratorio::ESTADO_RESULTADO_LISTO) {
                return false;
            }

            $sampleDate = ! empty($data['sample_collected_at'])
                ? Carbon::parse($data['sample_collected_at'])
                : now('America/Guayaquil');

            $locked->update([
                'estado' => PedidoLaboratorio::ESTADO_MUESTRA_TOMADA,
                'sample_collected_at' => $sampleDate,
                'sample_collected_by' => \Illuminate\Support\Facades\Auth::id(),
            ]);

            return true;
        });

        if (! $transitioned) {
            return back()->withErrors(['error' => 'Los resultados para este pedido ya fueron publicados.']);
        }

        return back()->with('success', 'Muestra del pedido registrada como tomada con éxito.');
    }

    /**
     * Sube el resultado del pedido y notifica al paciente.
     */
    public function subirResultado(Request $request, PedidoLaboratorio $pedido, LaboratoryResultStorageService $resultStorage)
    {
        if ($pedido->estado === PedidoLaboratorio::ESTADO_RESULTADO_LISTO) {
            return back()->withErrors(['error' => 'Los resultados para este pedido ya fueron publicados.']);
        }

        $validated = $request->validate([
            'resultado_pdf' => 'required|file|mimes:pdf|max:5120',
            'resultado_resumen' => 'required|string|max:2000',
        ], [
            'resultado_pdf.required' => 'Debe adjuntar el PDF con los resultados.',
            'resultado_pdf.mimes' => 'El archivo de resultados debe ser un PDF válido.',
            'resultado_resumen.required' => 'Debe ingresar un resumen clínico de los resultados.',
        ]);

        $path = $resultStorage->storeUploadedPdf($request->file('resultado_pdf'), 'legacy-medical-orders', $pedido->id);

        $published = false;
        try {
            $published = DB::transaction(function () use ($pedido, $path, $validated) {
                $locked = PedidoLaboratorio::where('id', $pedido->id)->lockForUpdate()->first();
                if (! $locked || $locked->estado === PedidoLaboratorio::ESTADO_RESULTADO_LISTO) {
                    return false;
                }

                $locked->update([
                    'resultado_path' => $path,
                    'resultado_resumen' => $validated['resultado_resumen'],
                    'resultado_publicado_at' => now('America/Guayaquil'),
                    'estado' => PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
                ]);

                return true;
            });
        } catch (\Throwable $exception) {
            $resultStorage->deleteNew($path);
            throw $exception;
        }

        if (! $published) {
            $resultStorage->deleteNew($path);
            return back()->withErrors(['error' => 'Los resultados para este pedido ya fueron publicados.']);
        }

        // Desacoplar el envío de correo de la solicitud web mediante el job oficial del sistema
        $paciente = $pedido->paciente;
        if ($paciente && $paciente->email) {
            try {
                \App\Jobs\EnviarResultadoPedidoLaboratorioJob::dispatch(null, false, $pedido->id);
            } catch (\Throwable $e) {
                $pedido->forceFill([
                    'envio_estado' => 'failed',
                    'envio_error' => 'No se pudo encolar el trabajo de notificación: ' . mb_substr($e->getMessage(), 0, 1000),
                ])->saveQuietly();

                Log::error('Error despachando EnviarResultadoPedidoLaboratorioJob a la cola: ' . $e->getMessage(), [
                    'pedido_id' => $pedido->id,
                ]);
            }
        }

        return back()->with('success', 'Resultados de laboratorio publicados y notificados al paciente por correo electrónico.');
    }

    /**
     * Descarga la orden médica firmada por el doctor (vista inline).
     */
    public function downloadOrden(PedidoLaboratorio $pedido)
    {
        $docService = app(\App\Services\LaboratoryOrderDocumentService::class);
        $docService->ensureUserCanView($pedido);

        return $docService->streamInline($pedido, 'orden_medica_' . $pedido->id . '.pdf');
    }

    /**
     * Descarga la orden médica firmada por el doctor (attachment/descarga).
     */
    public function downloadOrdenAttachment(PedidoLaboratorio $pedido)
    {
        $docService = app(\App\Services\LaboratoryOrderDocumentService::class);
        $docService->ensureUserCanView($pedido);

        return $docService->streamDownload($pedido, 'orden_medica_' . $pedido->id . '.pdf');
    }

    /**
     * Descarga el PDF de resultados subidos por el laboratorio.
     */
    public function downloadResultado(PedidoLaboratorio $pedido, LaboratoryResultStorageService $resultStorage)
    {
        if (! $pedido->resultado_path || ! $resultStorage->resolve($pedido->resultado_path)) {
            return back()->withErrors(['error' => 'El documento de resultados no está disponible.']);
        }

        $name = 'resultado_pedido_' . $pedido->id . '.pdf';
        return $resultStorage->download($pedido->resultado_path, $name);
    }
}
