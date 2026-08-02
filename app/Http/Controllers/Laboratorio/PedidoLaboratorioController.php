<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Mail\ResultadoPedidoLaboratorioMail;
use App\Models\PedidoLaboratorio;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

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
    public function marcarMuestra(PedidoLaboratorio $pedido)
    {
        if ($pedido->estado === 'resultado_listo') {
            return back()->withErrors(['error' => 'Los resultados para este pedido ya fueron publicados.']);
        }

        $pedido->update(['estado' => 'muestra_tomada']);

        return back()->with('success', 'Muestra del pedido registrada como tomada con éxito.');
    }

    /**
     * Sube el resultado del pedido y notifica al paciente.
     */
    public function subirResultado(Request $request, PedidoLaboratorio $pedido)
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

        // Guardar archivo
        $path = $request->file('resultado_pdf')->store('laboratorio_resultados', 'local');

        $pedido->update([
            'resultado_path' => $path,
            'resultado_resumen' => $request->input('resultado_resumen'),
            'resultado_publicado_at' => now('America/Guayaquil'),
            'estado' => 'resultado_listo',
        ]);

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
    public function downloadResultado(PedidoLaboratorio $pedido)
    {
        if (!$pedido->resultado_path || !Storage::disk('local')->exists($pedido->resultado_path)) {
            return back()->withErrors(['error' => 'El documento de resultados no está disponible.']);
        }

        $name = 'resultado_pedido_' . $pedido->id . '.pdf';
        return Storage::disk('local')->download($pedido->resultado_path, $name);
    }
}
