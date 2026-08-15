<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarPedidoLaboratorioJob;
use App\Models\Cita;
use App\Models\PedidoLaboratorio;
use App\Services\DocumentoCsvService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class PedidoLaboratorioController extends Controller
{
    public function create(Cita $cita)
    {
        $this->authorizeCita($cita);

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede generar pedidos de laboratorio para citas realizadas.');
        }

        $cita->loadMissing(['paciente', 'doctor', 'pedidoLaboratorio']);

        if ($cita->pedidoLaboratorio) {
            return redirect()->route('doctor.pedidos-laboratorio.edit', $cita);
        }

        return view('doctor.pedidos-laboratorio.crear', compact('cita'));
    }

    public function edit(Cita $cita)
    {
        $this->authorizeCita($cita);

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede editar pedidos de laboratorio para citas realizadas.');
        }

        $pedido = $cita->pedidoLaboratorio;
        if (! $pedido) {
            return redirect()->route('doctor.pedidos-laboratorio.create', $cita);
        }

        abort_unless($pedido->doctor_id === Auth::id(), 403);
        abort_unless($pedido->cita_id === $cita->id, 403);

        $cita->loadMissing(['paciente', 'doctor']);

        return view('doctor.pedidos-laboratorio.crear', compact('cita', 'pedido'));
    }

    public function update(Request $request, Cita $cita)
    {
        $this->authorizeCita($cita);

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede modificar pedidos de laboratorio para citas realizadas.');
        }

        $pedido = $cita->pedidoLaboratorio;
        if (! $pedido) {
            return redirect()->route('doctor.pedidos-laboratorio.create', $cita);
        }

        abort_unless($pedido->doctor_id === Auth::id(), 403);
        abort_unless($pedido->cita_id === $cita->id, 403);

        $validated = $request->validate([
            'examenes' => ['required', 'array', 'min:1'],
        ], [
            'examenes.required' => 'Debe seleccionar al menos un examen de laboratorio.',
            'examenes.min' => 'Debe seleccionar al menos un examen de laboratorio.',
        ]);

        $docService = app(\App\Services\LaboratoryOrderDocumentService::class);
        $oldPath = $pedido->getRawOriginal('pdf_path');
        $oldDisk = $pedido->getRawOriginal('pdf_disk');
        $st = null;

        DB::beginTransaction();
        try {
            $pedido->update([
                'examenes' => $validated['examenes'],
            ]);

            [$pdfBinary] = $docService->generatePdfOutput($pedido);
            $st = $docService->storeOrderPdf($pedido, $pdfBinary);
            $pedido->update([
                'pdf_path' => $st['pdf_path'],
                'pdf_disk' => $st['pdf_disk'],
            ]);

            DB::commit();

            $docService->cleanupOldPdf($oldPath, $oldDisk);
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($st && isset($st['pdf_path'])) {
                $docService->deleteQuietly($st['pdf_path'], $st['pdf_disk']);
            }

            return back()->withErrors(['error' => 'Error al modificar el pedido de laboratorio: ' . $e->getMessage()])->withInput();
        }

        $pedido->forceFill([
            'envio_estado' => 'queued',
            'envio_error' => null,
        ])->saveQuietly();

        EnviarPedidoLaboratorioJob::dispatch($pedido->id);

        return redirect()->route('doctor.citas')
            ->with('success', 'Pedido de laboratorio modificado y enviado al correo del paciente.');
    }

    public function index(Request $request)
    {
        $doctor = $request->user();
        if (! $doctor) {
            abort(403);
        }

        $pedidos = PedidoLaboratorio::with(['paciente', 'cita'])
            ->with([
                'resultados' => function ($query) {
                    $query->orderByDesc('version');
                },
                'resultados.laboratorio',
            ])
            ->where('doctor_id', $doctor->id)
            ->latest()
            ->paginate(10);

        return view('doctor.pedidos-laboratorio.index', compact('pedidos'));
    }

    public function store(Request $request, Cita $cita)
    {
        $this->authorizeCita($cita);

        if ($cita->estado !== Cita::ESTADO_REALIZADA) {
            return back()->with('error', 'Solo se puede generar pedidos de laboratorio para citas realizadas.');
        }

        $validated = $request->validate([
            'examenes' => ['required', 'array', 'min:1'],
        ], [
            'examenes.required' => 'Debe seleccionar al menos un examen de laboratorio.',
            'examenes.min' => 'Debe seleccionar al menos un examen de laboratorio.',
        ]);

        $doctor = $request->user();
        if (! $doctor) {
            abort(403);
        }

        $csvService = app(DocumentoCsvService::class);
        $docService = app(\App\Services\LaboratoryOrderDocumentService::class);
        $csv = $csvService->generateCsv();
        $st = null;

        DB::beginTransaction();
        try {
            $cita = Cita::query()
                ->with(['pedidoLaboratorio', 'paciente', 'doctor', 'especialidad'])
                ->whereKey($cita->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($cita->pedidoLaboratorio) {
                DB::rollBack();

                return redirect()
                    ->route('doctor.pedidos-laboratorio.download', $cita->pedidoLaboratorio)
                    ->with('info', 'Ya existe un pedido de laboratorio para esta cita.');
            }

            $pedido = PedidoLaboratorio::create([
                'cita_id' => $cita->id,
                'paciente_id' => $cita->paciente_id,
                'doctor_id' => $doctor->id,
                'csv' => $csv,
                'examenes' => $validated['examenes'],
                'estado' => 'pendiente_toma',
                'envio_estado' => 'queued',
                'envio_intentos' => 0,
            ]);

            [$pdfBinary] = $docService->generatePdfOutput($pedido);
            $st = $docService->storeOrderPdf($pedido, $pdfBinary);
            $pedido->update([
                'pdf_path' => $st['pdf_path'],
                'pdf_disk' => $st['pdf_disk'],
            ]);

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();

            if ($st && isset($st['pdf_path'])) {
                $docService->deleteQuietly($st['pdf_path'], $st['pdf_disk']);
            }

            return back()->withErrors(['error' => 'Error al generar el pedido de laboratorio: ' . $e->getMessage()])->withInput();
        }

        EnviarPedidoLaboratorioJob::dispatch($pedido->id);

        return redirect()->route('doctor.citas')
            ->with('success', 'Pedido de laboratorio generado y enviado al correo del paciente.');
    }

    public function download(PedidoLaboratorio $pedido)
    {
        $docService = app(\App\Services\LaboratoryOrderDocumentService::class);
        $docService->ensureUserCanView($pedido);

        return $docService->streamInline($pedido, "pedido_laboratorio_{$pedido->id}.pdf");
    }

    public function downloadResultado(\Illuminate\Http\Request $request, PedidoLaboratorio $pedido, \App\Services\PedidoLaboratorioPdfService $pdfs)
    {
        $resultado = $pedido->resultados()
            ->where('estado', 'publicado')
            ->orderByDesc('version')
            ->first();

        if (! $resultado) {
            return back()->withErrors(['error' => 'El informe de resultados aún no está disponible.']);
        }

        abort_unless($pdfs->usuarioAutorizadoParaResultado($pedido, $resultado, Auth::user()), 403);

        $disposition = $request->query('disposition', 'attachment');
        return $pdfs->streamResultadoFile($resultado, $disposition);
    }

    public function resend(PedidoLaboratorio $pedido)
    {
        $docService = app(\App\Services\LaboratoryOrderDocumentService::class);
        $docService->ensureUserCanView($pedido);

        $diskName = $docService->resolveDisk($pedido->pdf_disk);
        if (! $pedido->pdf_path || ! Storage::disk($diskName)->exists($pedido->pdf_path)) {
            [$pdfBinary] = $docService->generatePdfOutput($pedido);
            $st = $docService->storeOrderPdf($pedido, $pdfBinary);
            $pedido->update([
                'pdf_path' => $st['pdf_path'],
                'pdf_disk' => $st['pdf_disk'],
            ]);
        }

        $pedido->forceFill([
            'envio_estado' => 'queued',
            'envio_error' => null,
        ])->saveQuietly();

        EnviarPedidoLaboratorioJob::dispatch($pedido->id, forceResend: true);

        return back()->with('success', 'Se reintentara el envio del pedido de laboratorio.');
    }

    private function authorizeCita(Cita $cita): void
    {
        abort_unless($cita->doctor_id === Auth::id(), 403);
    }

}
