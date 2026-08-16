<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Paciente\SubmitPagoRequest;
use App\Models\Pago;
use App\Services\PagoService;
use App\Services\PaymentOrderDocumentService;
use App\Services\PaymentProofStorageService;
use App\Services\PaymentReceiptDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class PagoController extends Controller
{
    public function __construct(
        private readonly PaymentProofStorageService $paymentProofStorageService
    ) {}

    public function index(Request $request)
    {
        $user = Auth::user();
        $estado = (string) $request->get('estado', '');
        if ($estado === 'all') {
            $estado = '';
        }

        $pagos = Pago::query()
            ->with([
                'cita:id,doctor_id,especialidad_id,fecha,hora,estado',
                'cita.doctor:id,name',
                'cita.especialidad:id,nombre',
                'receipt:id,pago_id,folio_recibo,emitido_en,pdf_path',
            ])
            ->where('paciente_id', $user->id)
            ->conOrdenCobroReal()
            ->when(in_array($estado, Pago::ESTADOS, true), function ($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->orderByDesc('created_at')
            ->paginate(10)
            ->withQueryString();

        $bloqueoActivo = app(PagoService::class)->pacienteTieneBloqueo($user->id);

        return view('paciente.pagos.index', compact('pagos', 'estado', 'bloqueoActivo'));
    }

    public function submit(
        SubmitPagoRequest $request,
        Pago $pago,
        PagoService $pagoService
    ) {
        if (! $this->paymentProofStorageService->userCanViewProof($request->user(), $pago, 'paciente')) {
            abort(403);
        }

        if (! $pago->esEditablePorPaciente()) {
            $message = $pago->estado === Pago::ESTADO_EN_VERIFICACION
                ? 'El pago está en verificación. Debe esperar revisión administrativa antes de cambiar el método.'
                : 'Este pago no permite modificaciones en su estado actual.';

            return back()->withErrors([
                'error' => $message,
            ]);
        }
        if (! $pago->tieneOrdenCobro()) {
            return back()->withErrors([
                'error' => 'La orden de cobro aún no está habilitada para esta cita.',
            ]);
        }

        $data = $request->validated();
        $metodo = $data['metodo_pago'];

        if ($pago->metodo_pago === Pago::METODO_EFECTIVO && $metodo === Pago::METODO_EFECTIVO) {
            return redirect()
                ->route('paciente.pagos.index')
                ->with('success', 'Pago en clínica: este pago será confirmado por recepción al momento de su atención.');
        }

        $oldPath = $pago->comprobante_path;
        $oldDisk = $pago->comprobante_disk;
        $newUploadedPath = null;
        $newUploadedDisk = null;

        $comprobantePath = $oldPath;
        $comprobanteDisk = $oldDisk;

        if ($metodo === Pago::METODO_TRANSFERENCIA) {
            if ($request->hasFile('comprobante')) {
                try {
                    $stored = $this->paymentProofStorageService->storeUploadedProofFile(
                        $request->file('comprobante'),
                        $pago->id
                    );
                    $newUploadedPath = $stored['path'];
                    $newUploadedDisk = $stored['disk'];
                    $comprobantePath = $newUploadedPath;
                    $comprobanteDisk = $newUploadedDisk;
                } catch (\InvalidArgumentException $e) {
                    return back()->withErrors([
                        'comprobante' => $e->getMessage(),
                    ])->withInput();
                } catch (\Throwable $e) {
                    return back()->withErrors([
                        'comprobante' => 'Error al procesar el comprobante: ' . $e->getMessage(),
                    ])->withInput();
                }
            }
        } else {
            // Cambio a efectivo: desvincular comprobante
            $comprobantePath = null;
            $comprobanteDisk = null;
        }

        $nuevoEstado = $metodo === Pago::METODO_TRANSFERENCIA
            ? Pago::ESTADO_EN_VERIFICACION
            : Pago::ESTADO_PENDIENTE;

        if ($nuevoEstado === Pago::ESTADO_EN_VERIFICACION && ! $comprobantePath) {
            if ($newUploadedPath) {
                try {
                    Storage::disk($newUploadedDisk)->delete($newUploadedPath);
                } catch (\Throwable) {
                }
            }
            return back()->withErrors([
                'comprobante' => 'Debe adjuntar un comprobante para enviar transferencia a verificación.',
            ])->withInput();
        }

        try {
            $pagoService->cambiarEstado(
                pago: $pago,
                nuevoEstado: $nuevoEstado,
                actor: $request->user(),
                motivo: null,
                extra: [
                    'metodo_pago' => $metodo,
                    'referencia_transaccion' => $data['referencia_transaccion'] ?? null,
                    'comprobante_path' => $comprobantePath,
                    'comprobante_disk' => $comprobanteDisk,
                    'observacion_admin' => null,
                ]
            );
        } catch (\Throwable $e) {
            // Compensación: Si la actualización de BD falla, eliminar el archivo recién subido
            if ($newUploadedPath) {
                try {
                    Storage::disk($newUploadedDisk)->delete($newUploadedPath);
                } catch (\Throwable $cleanupErr) {
                    \Illuminate\Support\Facades\Log::error('Error cleaning up new proof on DB failure: ' . $cleanupErr->getMessage());
                }
            }

            if ($e instanceof \InvalidArgumentException) {
                return back()->withErrors([
                    'error' => $e->getMessage(),
                ]);
            }

            throw $e;
        }

        // Eliminar el comprobante anterior ÚNICAMENTE después de confirmar la mutación en BD
        if ($oldPath && $oldPath !== $comprobantePath) {
            $this->paymentProofStorageService->deleteOldProofIfSafe($oldPath, $oldDisk);
        }

        $message = $nuevoEstado === Pago::ESTADO_EN_VERIFICACION
            ? 'Comprobante enviado. El pago quedó en verificación.'
            : 'Pago en clínica: este pago será confirmado por recepción al momento de su atención.';

        return redirect()
            ->route('paciente.pagos.index')
            ->with('success', $message);
    }

    public function comprobante(Pago $pago)
    {
        return $this->paymentProofStorageService->streamProofResponse($pago, Auth::user(), 'paciente');
    }

    public function ordenPdf(Request $request, Pago $pago, PaymentOrderDocumentService $orderDocumentService)
    {
        return $orderDocumentService->streamOrderResponse($pago, Auth::user(), 'paciente');
    }

    public function reciboPdf(Pago $pago, PaymentReceiptDocumentService $receiptDocumentService)
    {
        $recibo = $pago->receipt;
        if (! $recibo) {
            abort(404);
        }

        return $receiptDocumentService->streamReceiptResponse($recibo, Auth::user(), 'paciente');
    }
}
