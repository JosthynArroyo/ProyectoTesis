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
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PagoController extends Controller
{
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

    public function actualizarMonto(UpdatePagoMontoRequest $request, Pago $pago)
    {
        $data = $request->validated();

        $pago->monto = $data['monto'];
        if (! empty($data['moneda'])) {
            $pago->moneda = strtoupper((string) $data['moneda']);
        }
        $pago->save();

        return back()->with('success', 'Monto de pago actualizado.');
    }

    public function actualizarMetodo(UpdatePagoMetodoRequest $request, Pago $pago, PagoService $pagoService)
    {
        $data = $request->validated();
        $nuevoMetodo = $data['metodo_pago'];
        $metodoAnterior = $pago->metodo_pago;
        $observacion = trim((string) ($data['observacion_admin'] ?? ''));

        if ($metodoAnterior === $nuevoMetodo) {
            return back()->with('success', 'Metodo de pago actualizado.');
        }

        $motivoBase = $metodoAnterior
            ? 'Cambio de metodo de pago: '.strtoupper($metodoAnterior).' -> '.strtoupper($nuevoMetodo).'.'
            : 'Asignacion de metodo de pago: '.strtoupper($nuevoMetodo).'.';
        $motivoLog = $observacion !== '' ? $motivoBase.' '.$observacion : $motivoBase;

        DB::transaction(function () use ($pago, $nuevoMetodo, $observacion, $pagoService, $request, $motivoLog): void {
            $pago->metodo_pago = $nuevoMetodo;
            if ($observacion !== '') {
                $pago->observacion_admin = $observacion;
            }
            $pago->save();

            $pagoService->registrarLog(
                pago: $pago,
                estadoAnterior: $pago->estado,
                estadoNuevo: $pago->estado,
                actor: $request->user(),
                motivo: $motivoLog
            );
        });

        return back()->with('success', 'Metodo de pago actualizado.');
    }

    public function aprobar(ApprovePagoRequest $request, Pago $pago, PagoService $pagoService)
    {
        if ($pago->estado === Pago::ESTADO_ANULADO) {
            return back()->withErrors(['error' => 'No se puede aprobar un pago anulado.']);
        }
        if ($pago->estado === Pago::ESTADO_PAGADO) {
            return back()->withErrors(['error' => 'El pago ya se encuentra aprobado.']);
        }
        if (empty($pago->metodo_pago)) {
            return back()->withErrors(['error' => 'Debe asignar metodo de pago antes de aprobar.']);
        }
        if ($pago->metodo_pago === Pago::METODO_TRANSFERENCIA && ! $pago->comprobante_path) {
            return back()->withErrors(['error' => 'No se puede aprobar transferencia sin comprobante adjunto.']);
        }

        $pagoService->cambiarEstado(
            pago: $pago,
            nuevoEstado: Pago::ESTADO_PAGADO,
            actor: $request->user(),
            motivo: $request->validated('observacion_admin')
        );

        return back()->with('success', 'Pago aprobado correctamente.');
    }

    public function rechazar(RejectPagoRequest $request, Pago $pago, PagoService $pagoService)
    {
        if ($pago->estado === Pago::ESTADO_ANULADO) {
            return back()->withErrors(['error' => 'No se puede rechazar un pago anulado.']);
        }
        if ($pago->estado === Pago::ESTADO_PAGADO) {
            return back()->withErrors(['error' => 'No se puede rechazar un pago ya aprobado.']);
        }

        $pagoService->cambiarEstado(
            pago: $pago,
            nuevoEstado: Pago::ESTADO_RECHAZADO,
            actor: $request->user(),
            motivo: $request->validated('observacion_admin')
        );

        return back()->with('success', 'Pago rechazado y devuelto al paciente para corrección.');
    }

    public function anular(AnularPagoRequest $request, Pago $pago, PagoService $pagoService)
    {
        if ($pago->estado === Pago::ESTADO_ANULADO) {
            return back()->withErrors(['error' => 'El pago ya está anulado.']);
        }

        $pagoService->cambiarEstado(
            pago: $pago,
            nuevoEstado: Pago::ESTADO_ANULADO,
            actor: $request->user(),
            motivo: $request->validated('observacion_admin')
        );

        return back()->with('success', 'Pago anulado correctamente.');
    }

    public function ordenPdf(Request $request, Pago $pago, PagoService $pagoService)
    {
        if (! $pago->tieneOrdenCobro()) {
            abort(404);
        }

        $path = $pagoService->obtenerOGenerarOrdenPdf($pago, $request->user());
        if (! Storage::disk('local')->exists($path)) {
            abort(404);
        }

        $fileName = 'orden_cobro_'.($pago->folio_unico ?: $pago->id).'.pdf';

        return Storage::disk('local')->response(
            $path,
            $fileName,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            ]
        );
    }

    public function reciboPdf(Request $request, Pago $pago, PagoService $pagoService)
    {
        if ($pago->estado !== Pago::ESTADO_PAGADO) {
            abort(404);
        }

        $recibo = $pagoService->emitirReciboParaPago($pago, $request->user(), 'Reimpresion de recibo solicitada por administracion.');
        if (! $recibo->pdf_path || ! Storage::disk('local')->exists($recibo->pdf_path)) {
            abort(404);
        }

        $fileName = 'recibo_pago_'.($recibo->folio_recibo ?: $recibo->id).'.pdf';

        return Storage::disk('local')->response(
            $recibo->pdf_path,
            $fileName,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            ]
        );
    }

    public function comprobante(Pago $pago)
    {
        $stored = $this->resolveComprobanteStorage($pago->comprobante_path);
        if (! $stored) {
            abort(404);
        }

        $disk = Storage::disk($stored['disk']);
        $mime = $disk->mimeType($stored['path']) ?: 'application/octet-stream';
        $fileName = 'comprobante_pago_'.$pago->id.'.'.pathinfo($stored['path'], PATHINFO_EXTENSION);

        return $disk->response(
            $stored['path'],
            $fileName,
            [
                'Content-Type' => $mime,
                'Content-Disposition' => 'inline; filename="'.$fileName.'"',
            ]
        );
    }

    /**
     * @return array{disk:string,path:string}|null
     */
    private function resolveComprobanteStorage(?string $path): ?array
    {
        $normalized = trim((string) $path);
        if ($normalized === '') {
            return null;
        }

        $normalized = ltrim(str_replace('\\', '/', $normalized), '/');

        if (Storage::disk('local')->exists($normalized)) {
            return ['disk' => 'local', 'path' => $normalized];
        }

        $publicCandidates = [$normalized];
        if (Str::startsWith($normalized, 'storage/')) {
            $publicCandidates[] = ltrim(substr($normalized, 8), '/');
        }

        foreach ($publicCandidates as $candidate) {
            if ($candidate !== '' && Storage::disk('public')->exists($candidate)) {
                return ['disk' => 'public', 'path' => $candidate];
            }
        }

        return null;
    }
}
