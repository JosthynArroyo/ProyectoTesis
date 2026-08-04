<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Paciente\SubmitPagoRequest;
use App\Models\Pago;
use App\Services\PagoService;
use App\Services\PaymentProofStorageService;
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

        $comprobantePath = $pago->comprobante_path;
        $comprobanteDisk = $pago->comprobante_disk;

        if ($metodo === Pago::METODO_TRANSFERENCIA) {
            if ($request->hasFile('comprobante')) {
                try {
                    $stored = $this->paymentProofStorageService->uploadAndStoreProof(
                        $request->file('comprobante'),
                        $pago
                    );
                    $comprobantePath = $stored['path'];
                    $comprobanteDisk = $stored['disk'];
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
            if ($comprobantePath) {
                $oldPath = $comprobantePath;
                $oldDisk = $comprobanteDisk;
                $comprobantePath = null;
                $comprobanteDisk = null;
                $this->paymentProofStorageService->deleteOldProofIfSafe($oldPath, $oldDisk);
            }
        }

        $nuevoEstado = $metodo === Pago::METODO_TRANSFERENCIA
            ? Pago::ESTADO_EN_VERIFICACION
            : Pago::ESTADO_PENDIENTE;

        if ($nuevoEstado === Pago::ESTADO_EN_VERIFICACION && ! $comprobantePath) {
            return back()->withErrors([
                'comprobante' => 'Debe adjuntar un comprobante para enviar transferencia a verificación.',
            ])->withInput();
        }

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

    public function ordenPdf(Request $request, Pago $pago, PagoService $pagoService)
    {
        if ((int) $pago->paciente_id !== (int) Auth::id()) {
            abort(403);
        }
        if (! $pago->tieneOrdenCobro()) {
            abort(404);
        }

        $path = $pagoService->obtenerOGenerarOrdenPdf($pago, $request->user());
        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $fileName = 'orden_cobro_' . ($pago->folio_unico ?: $pago->id) . '.pdf';

        return Storage::disk('local')->response(
            $path,
            $fileName,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]
        );
    }

    public function reciboPdf(Pago $pago)
    {
        if ((int) $pago->paciente_id !== (int) Auth::id()) {
            abort(403);
        }

        $recibo = $pago->receipt;
        if (! $recibo || ! $recibo->pdf_path || ! Storage::disk('local')->exists($recibo->pdf_path)) {
            abort(404);
        }

        $fileName = 'recibo_pago_' . ($recibo->folio_recibo ?: $recibo->id) . '.pdf';

        return Storage::disk('local')->response(
            $recibo->pdf_path,
            $fileName,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $fileName . '"',
            ]
        );
    }
}
