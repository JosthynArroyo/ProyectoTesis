<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\PedidoLaboratorio;
use App\Services\LaboratoryResultStorageService;
use Carbon\Carbon;
use Illuminate\Http\Request;
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
        if ($pedido->estado === 'resultado_listo') {
            return back()->withErrors(['error' => 'Los resultados para este pedido ya fueron publicados.']);
        }

        $sampleDate = $request->input('sample_collected_at')
            ? Carbon::parse($request->input('sample_collected_at'))
            : now('America/Guayaquil');

        $pedido->update([
            'estado' => 'muestra_tomada',
            'sample_collected_at' => $sampleDate,
            'sample_collected_by' => \Illuminate\Support\Facades\Auth::id(),
        ]);

        return back()->with('success', 'Muestra del pedido registrada como tomada con éxito.');
    }

    /**
     * Sube el resultado del pedido y notifica al paciente.
     */
    public function subirResultado(Request $request, PedidoLaboratorio $pedido, LaboratoryResultStorageService $resultStorage)
    {
        if ($pedido->estado === 'resultado_listo') {
            return back()->withErrors(['error' => 'Los resultados para este pedido ya fueron publicados.']);
        }

        $request->validate([
            'resultado_pdf' => 'required|file|mimes:pdf|max:5120',
            'resultado_resumen' => 'required|string|max:2000',
        ], [
            'resultado_pdf.required' => 'Debe adjuntar el PDF con los resultados.',
            'resultado_pdf.mimes' => 'El archivo de resultados debe ser un PDF válido.',
            'resultado_resumen.required' => 'Debe ingresar un resumen clínico de los resultados.',
        ]);

        $path = $resultStorage->storeUploadedPdf($request->file('resultado_pdf'), 'legacy-medical-orders', $pedido->id);

        try {
            $pedido->update([
                'resultado_path' => $path,
                'resultado_resumen' => $request->input('resultado_resumen'),
                'resultado_publicado_at' => now('America/Guayaquil'),
                'estado' => 'resultado_listo',
            ]);
        } catch (\Throwable $exception) {
            $resultStorage->deleteNew($path);
            throw $exception;
        }

        // Enviar notificación al paciente
        if ($pedido->paciente && $pedido->paciente->email) {
            Mail::to($pedido->paciente->email)->send(new ResultadoPedidoLaboratorioMail($pedido));
            $pedido->update(['resultado_enviado_at' => now('America/Guayaquil')]);
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
