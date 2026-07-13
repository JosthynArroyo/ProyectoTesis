<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Http\Requests\Paciente\SubmitPagoRequest;
use App\Models\Pago;
use App\Services\ImageOptimizer;
use App\Services\PagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PagoController extends Controller
{
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
        PagoService $pagoService,
        ImageOptimizer $imageOptimizer
    ) {
        if ((int) $pago->paciente_id !== (int) Auth::id()) {
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

        if ($metodo === Pago::METODO_TRANSFERENCIA) {
            if ($request->hasFile('comprobante')) {
                $this->deleteComprobante($comprobantePath, $imageOptimizer);

                $file = $request->file('comprobante');
                $extension = strtolower((string) $file->getClientOriginalExtension());
                $mime = strtolower((string) $file->getMimeType());

                if ($this->isImageUpload($extension, $mime)) {
                    $comprobantePath = $imageOptimizer->optimizeAndStore(
                        $file,
                        'payment-proofs/patients/'.$pago->paciente_id
                    );
                } else {
                    $comprobantePath = $file->store(
                        'pagos/comprobantes/pacientes/'.$pago->paciente_id,
                        'local'
                    );
                }
            }
        } else {
            $this->deleteComprobante($comprobantePath, $imageOptimizer);
            $comprobantePath = null;
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
        if ((int) $pago->paciente_id !== (int) Auth::id()) {
            abort(403);
        }

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

    private function deleteComprobante(?string $path, ImageOptimizer $imageOptimizer): void
    {
        $stored = $this->resolveComprobanteStorage($path);
        if (! $stored) {
            return;
        }

        if ($stored['disk'] === 'local') {
            Storage::disk('local')->delete($stored['path']);

            return;
        }

        $imageOptimizer->deleteByStoredPath($stored['path']);
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

    private function isImageUpload(string $extension, string $mime): bool
    {
        return in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)
            || in_array($mime, ['image/jpeg', 'image/pjpeg', 'image/png', 'image/webp'], true);
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

    public function reciboPdf(Pago $pago)
    {
        if ((int) $pago->paciente_id !== (int) Auth::id()) {
            abort(403);
        }

        $recibo = $pago->receipt;
        if (! $recibo || ! $recibo->pdf_path || ! Storage::disk('local')->exists($recibo->pdf_path)) {
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
}
