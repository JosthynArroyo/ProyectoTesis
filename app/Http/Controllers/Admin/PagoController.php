<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnularPagoRequest;
use App\Http\Requests\Admin\ApprovePagoRequest;
use App\Http\Requests\Admin\RejectPagoRequest;
use App\Http\Requests\Admin\UpdatePagoMetodoRequest;
use App\Http\Requests\Admin\UpdatePagoMontoRequest;
use App\Models\Pago;
use App\Services\PagoService;
use App\Services\PaymentOrderDocumentService;
use App\Services\PaymentProofStorageService;
use App\Services\PaymentReceiptDocumentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PagoController extends Controller
{
    public function __construct(
        private readonly PaymentProofStorageService $paymentProofStorageService
    ) {}
    public function index(Request $request)
    {
        $estado = (string) $request->query('estado', '');
        $desde = $this->normalizeDateFilter($request->query('desde', ''));
        $hasta = $this->normalizeDateFilter($request->query('hasta', ''));
        $buscar = $this->normalizeSearchTerm($request->query('q', ''));

        if ($estado === 'all') {
            $estado = '';
        }

        $pagos = Pago::query()
            ->with([
                'paciente:id,name,email,dni',
                'cita:id,doctor_id,fecha,hora,estado',
                'cita.doctor:id,name',
                'receipt:id,pago_id,folio_recibo,emitido_en,pdf_path',
            ])
            ->when(in_array($estado, Pago::ESTADOS, true), function ($query) use ($estado) {
                $query->where('estado', $estado);
            })
            ->when($desde !== '', function ($query) use ($desde) {
                $query->whereDate('created_at', '>=', $desde);
            })
            ->when($hasta !== '', function ($query) use ($hasta) {
                $query->whereDate('created_at', '<=', $hasta);
            })
            ->when($buscar !== '', function ($query) use ($buscar) {
                $like = '%'.$buscar.'%';
                $query->where(function ($nested) use ($like) {
                    $nested->where('folio_unico', 'like', $like)
                        ->orWhere('token_publico', 'like', $like)
                        ->orWhereHas('paciente', function ($patientQuery) use ($like) {
                            $patientQuery->where('name', 'like', $like)
                                ->orWhere('dni', 'like', $like)
                                ->orWhere('email', 'like', $like);
                        });
                });
            })
            // Static CASE ordering only; never build this fragment from request values.
            ->orderByRaw("
                CASE estado
                    WHEN 'pendiente' THEN 1
                    WHEN 'en_verificacion' THEN 2
                    WHEN 'rechazado' THEN 3
                    WHEN 'pagado' THEN 4
                    WHEN 'anulado' THEN 5
                    ELSE 6
                END
            ")
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        $totales = Pago::query()
            ->select('estado', DB::raw('count(*) as total'))
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return view('admin.pagos.index', compact('pagos', 'estado', 'desde', 'hasta', 'buscar', 'totales'));
    }

    private function normalizeSearchTerm(mixed $value, int $maxLength = 100): string
    {
        return trim(mb_substr((string) $value, 0, $maxLength));
    }

    private function normalizeDateFilter(mixed $value): string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }

        $date = \DateTime::createFromFormat('Y-m-d', $value);

        return $date && $date->format('Y-m-d') === $value ? $value : '';
    }

    public function show(Pago $pago)
    {
        $pago->load([
            'paciente:id,name,email,dni',
            'cita:id,doctor_id,especialidad_id,fecha,hora,estado',
            'cita.doctor:id,name',
            'cita.especialidad:id,nombre',
            'statusLogs.actor:id,name',
            'aprobador:id,name',
            'receipt.logs.actor:id,name',
            'receipt.emisor:id,name',
        ]);

        return view('admin.pagos.show', compact('pago'));
    }

    public function actualizarMonto(UpdatePagoMontoRequest $request, Pago $pago, PagoService $pagoService)
    {
        $data = $request->validated();

        if (! $pago->esEditableFinancieramente()) {
            return back()->withErrors(['error' => 'Este pago ya fue aprobado o cuenta con recibo emitido; su monto es inmutable.']);
        }

        try {
            $pagoService->actualizarMontoAdministrativo(
                pago: $pago,
                nuevoMonto: (float) $data['monto'],
                nuevaMoneda: $data['moneda'] ?? null,
                actor: $request->user()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Monto de pago actualizado.');
    }

    public function actualizarMetodo(UpdatePagoMetodoRequest $request, Pago $pago, PagoService $pagoService)
    {
        $data = $request->validated();
        $nuevoMetodo = $data['metodo_pago'];
        $observacion = $data['observacion_admin'] ?? null;

        if (! $pago->esEditableFinancieramente()) {
            return back()->withErrors(['error' => 'Este pago ya fue aprobado o cuenta con recibo emitido; su método de pago es inmutable.']);
        }

        try {
            $pagoService->actualizarMetodoAdministrativo(
                pago: $pago,
                nuevoMetodo: $nuevoMetodo,
                observacion: $observacion,
                actor: $request->user()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['error' => $e->getMessage()]);
        }

        return back()->with('success', 'Metodo de pago actualizado.');
    }

    public function aprobar(ApprovePagoRequest $request, Pago $pago, PagoService $pagoService)
    {
        if (! $pago->canPerformAdministrativeAction(Pago::ADMIN_ACTION_APROBAR)) {
            return $this->rejectAdministrativeTransition($pago);
        }

        if (empty($pago->metodo_pago)) {
            return redirect()
                ->route('admin.pagos.show', $pago)
                ->withErrors(['error' => 'Debe asignar un método de pago antes de aprobar.']);
        }
        if ($pago->metodo_pago === Pago::METODO_TRANSFERENCIA && ! $pago->comprobante_path) {
            return redirect()
                ->route('admin.pagos.show', $pago)
                ->withErrors(['error' => 'No se puede aprobar una transferencia sin comprobante adjunto.']);
        }

        try {
            $pagoService->cambiarEstado(
                pago: $pago,
                nuevoEstado: $pago->administrativeTargetState(Pago::ADMIN_ACTION_APROBAR) ?? Pago::ESTADO_PAGADO,
                actor: $request->user(),
                motivo: $request->validated('observacion_admin')
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.pagos.show', $pago)
                ->withErrors(['error' => $e->getMessage()]);
        } catch (\RuntimeException $e) {
            return redirect()
                ->route('admin.pagos.show', $pago)
                ->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.pagos.show', $pago)
            ->with('success', 'Pago aprobado correctamente.');
    }

    public function rechazar(RejectPagoRequest $request, Pago $pago, PagoService $pagoService)
    {
        if (! $pago->canPerformAdministrativeAction(Pago::ADMIN_ACTION_RECHAZAR)) {
            return $this->rejectAdministrativeTransition($pago);
        }

        try {
            $pagoService->cambiarEstado(
                pago: $pago,
                nuevoEstado: $pago->administrativeTargetState(Pago::ADMIN_ACTION_RECHAZAR) ?? Pago::ESTADO_RECHAZADO,
                actor: $request->user(),
                motivo: $request->validated('observacion_admin')
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.pagos.show', $pago)
                ->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.pagos.show', $pago)
            ->with('success', 'Pago rechazado y devuelto al paciente para correccion.');
    }

    public function anular(AnularPagoRequest $request, Pago $pago, PagoService $pagoService)
    {
        if (! $pago->canPerformAdministrativeAction(Pago::ADMIN_ACTION_ANULAR)) {
            return $this->rejectAdministrativeTransition($pago);
        }

        try {
            $pagoService->cambiarEstado(
                pago: $pago,
                nuevoEstado: $pago->administrativeTargetState(Pago::ADMIN_ACTION_ANULAR) ?? Pago::ESTADO_ANULADO,
                actor: $request->user(),
                motivo: $request->validated('observacion_admin')
            );
        } catch (\InvalidArgumentException $e) {
            return redirect()
                ->route('admin.pagos.show', $pago)
                ->withErrors(['error' => $e->getMessage()]);
        }

        return redirect()
            ->route('admin.pagos.show', $pago)
            ->with('success', 'Pago anulado correctamente.');
    }

    private function rejectAdministrativeTransition(Pago $pago)
    {
        return redirect()
            ->route('admin.pagos.show', $pago)
            ->withErrors(['error' => $pago->administrativeStateMessage()]);
    }

    public function ordenPdf(Request $request, Pago $pago, PaymentOrderDocumentService $orderDocumentService)
    {
        return $orderDocumentService->streamOrderResponse($pago, $request->user(), 'admin');
    }

    public function reciboPdf(Request $request, Pago $pago, PagoService $pagoService, PaymentReceiptDocumentService $receiptDocumentService)
    {
        if ($pago->estado !== Pago::ESTADO_PAGADO) {
            abort(404);
        }

        $recibo = $pagoService->emitirReciboParaPago($pago, $request->user(), 'Reimpresion de recibo solicitada por administracion.');

        return $receiptDocumentService->streamReceiptResponse($recibo, $request->user(), 'admin');
    }

    public function comprobante(Pago $pago)
    {
        return $this->paymentProofStorageService->streamProofResponse($pago, auth()->user(), 'admin');
    }
}
