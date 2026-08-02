<?php

namespace App\Http\Controllers\Laboratorio;

use App\Http\Controllers\Controller;
use App\Jobs\EnviarResultadoPedidoLaboratorioJob;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\PedidoLaboratorioResultadoAudit;
use App\Services\DocumentoCsvService;
use App\Services\LabTestCatalogService;
use App\Services\PedidoLaboratorioPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class PedidoLaboratorioResultadoController extends Controller
{
    public function create(PedidoLaboratorio $pedido, LabTestCatalogService $catalog)
    {
        $pedido = $this->loadPedido($pedido);
        $resultado = $this->editableResultado($pedido);

        return view('laboratorio.pedidos.resultados', [
            'pedido' => $pedido,
            'resultado' => $resultado,
            'items' => $this->buildFormItems($pedido, $resultado, $catalog),
        ]);
    }

    public function draft(Request $request, PedidoLaboratorio $pedido, LabTestCatalogService $catalog)
    {
        $validated = $this->validatePayload($request, $pedido);

        DB::transaction(function () use ($pedido, $validated, $catalog, $request): void {
            $pedido = $this->loadPedido(
                PedidoLaboratorio::query()->with('resultados')->whereKey($pedido->id)->lockForUpdate()->firstOrFail()
            );
            $resultado = $this->editableResultado($pedido);
            $this->fillResultado($resultado, $pedido, $validated, $catalog, PedidoLaboratorioResultado::ESTADO_BORRADOR, $request->user());
            $resultado->save();
            $this->audit($pedido, $resultado, 'borrador_guardado', $request->user(), [
                'items' => count((array) $validated['items']),
            ]);
        });

        return redirect()
            ->route('laboratorio.pedidos.resultados.form', $pedido)
            ->with('success', 'Borrador guardado correctamente.');
    }

    public function preview(Request $request, PedidoLaboratorio $pedido, PedidoLaboratorioPdfService $pdfs, LabTestCatalogService $catalog)
    {
        $validated = $this->validatePayload($request, $pedido);

        $resultado = DB::transaction(function () use ($pedido, $validated, $catalog, $request) {
            $pedido = $this->loadPedido(
                PedidoLaboratorio::query()->with('resultados')->whereKey($pedido->id)->lockForUpdate()->firstOrFail()
            );
            $resultado = $this->editableResultado($pedido);
            $this->fillResultado($resultado, $pedido, $validated, $catalog, PedidoLaboratorioResultado::ESTADO_BORRADOR, $request->user());
            $resultado->save();
            $this->audit($pedido, $resultado, 'vista_previa', $request->user(), [
                'items' => count((array) $validated['items']),
            ]);

            return $resultado->fresh(['pedido']);
        });

        $html = $pdfs->previewHtml($resultado->pedido, $resultado);
        $pdfOutput = $pdfs->renderPdfOutput($html);

        return response($pdfOutput, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="preview_resultado_laboratorio_'.$pedido->id.'.pdf"',
        ]);
    }

    public function publish(Request $request, PedidoLaboratorio $pedido, PedidoLaboratorioPdfService $pdfs, LabTestCatalogService $catalog)
    {
        $validated = $this->validatePayload($request, $pedido);

        $pedido->loadMissing('resultados');
        $latestPublished = $pedido->resultados->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)->sortByDesc('version')->first();
        $hasDraft = $pedido->resultados->contains(fn (PedidoLaboratorioResultado $resultado) => $resultado->estado === PedidoLaboratorioResultado::ESTADO_BORRADOR);

        if ($latestPublished && $pedido->resultado_publicado_at && ! $hasDraft) {
            return back()->with('info', 'Este resultado ya fue publicado. Usa la corrección versionada si necesitas cambiarlo.');
        }

        $uuid = (string) \Illuminate\Support\Str::uuid();
        $pdfPath = null;
        $disk = Storage::disk('r2_private');

        try {
            $resultado = DB::transaction(function () use ($pedido, $validated, $pdfs, $catalog, $request, $uuid, $disk, &$pdfPath) {
                $pedido = $this->loadPedido(
                    PedidoLaboratorio::query()->with(['resultados', 'cita.paciente', 'cita.dependiente.responsable', 'cita.doctor', 'doctor', 'paciente'])->whereKey($pedido->id)->lockForUpdate()->firstOrFail()
                );

                $latest = $pedido->resultados()->orderByDesc('version')->first();
                if ($latest && $latest->estado === PedidoLaboratorioResultado::ESTADO_PUBLICADO && $pedido->resultado_publicado_at && ! $pedido->resultados()->where('estado', PedidoLaboratorioResultado::ESTADO_BORRADOR)->exists()) {
                    return $latest->fresh(['pedido']);
                }

                $resultado = $this->editableResultado($pedido);
                $previousPublished = $pedido->resultados()
                    ->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();

                $this->fillResultado($resultado, $pedido, $validated, $catalog, PedidoLaboratorioResultado::ESTADO_PUBLICADO, $request->user());

                if (! $resultado->csv) {
                    $resultado->csv = app(DocumentoCsvService::class)->generateCsv();
                }

                $resultado->publicado_at = now('America/Guayaquil');
                $resultado->envio_estado = 'pending';
                $resultado->envio_error = null;
                $resultado->envio_intentos = 0;
                $resultado->pdf_disk = 'r2_private';
                $resultado->save();

                // 1. Generar en memoria
                $html = $pdfs->previewHtml($pedido, $resultado);
                $pdfBytes = $pdfs->renderPdfOutput($html);

                // 2. Subir primero a R2
                $key = "documents/laboratory-results/{$resultado->id}/{$uuid}.pdf";
                $pdfPath = $key;

                $uploaded = $disk->put($key, $pdfBytes);
                if (!$uploaded) {
                    throw new \RuntimeException("Fallo al subir el archivo PDF a r2_private.");
                }

                // 3. Verificar existencia, tamaño y cabecera
                if (!$disk->exists($key)) {
                    throw new \RuntimeException("El archivo subido no existe en r2_private.");
                }
                $size = $disk->size($key);
                if ($size <= 0) {
                    throw new \RuntimeException("El archivo subido en r2_private tiene tamaño cero.");
                }
                $stream = $disk->read($key);
                $content = is_resource($stream) ? stream_get_contents($stream) : $stream;
                if (substr((string)$content, 0, 4) !== '%PDF') {
                    throw new \RuntimeException("El archivo subido no tiene una cabecera PDF válida.");
                }

                $resultado->forceFill([
                    'pdf_path' => $key,
                ])->saveQuietly();

                if ($previousPublished && $previousPublished->id !== $resultado->id) {
                    $previousPublished->forceFill([
                        'estado' => PedidoLaboratorioResultado::ESTADO_REEMPLAZADO,
                        'reemplaza_id' => $resultado->id,
                    ])->saveQuietly();
                }

                $pedido->forceFill([
                    'resultado_path' => $key,
                    'resultado_resumen' => $resultado->observaciones_generales,
                    'resultado_publicado_at' => $resultado->publicado_at,
                    'resultado_enviado_at' => null,
                    'estado' => PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
                ])->saveQuietly();

                $this->audit($pedido, $resultado, $previousPublished ? 'corregido' : 'publicado', $request->user(), [
                    'version' => $resultado->version,
                    'path' => $key,
                ]);

                return $resultado->fresh(['pedido']);
            });
        } catch (\Throwable $e) {
            if ($pdfPath && $disk->exists($pdfPath)) {
                try {
                    $disk->delete($pdfPath);
                } catch (\Throwable $err) {
                    \Illuminate\Support\Facades\Log::error("Error al limpiar objeto R2 tras fallar la transacción: " . $err->getMessage());
                }
            }
            throw $e;
        }

        EnviarResultadoPedidoLaboratorioJob::dispatch($resultado->id);

        return redirect()
            ->route('laboratorio.pedidos.index')
            ->with('success', 'Resultados publicados correctamente. El correo se enviará solo al paciente.');
    }

    public function resend(PedidoLaboratorio $pedido)
    {
        abort_unless(Auth::user()?->hasRole('laboratorio'), 403);

        $resultado = $this->currentPublished($pedido);
        if (! $resultado) {
            return back()->withErrors(['error' => 'No existe un resultado publicado para reenviar.']);
        }

        $this->audit($pedido, $resultado, 'reenviado_solicitado', Auth::user(), [
            'version' => $resultado->version,
        ]);

        EnviarResultadoPedidoLaboratorioJob::dispatch($resultado->id, true);

        return back()->with('success', 'Se reintentará el envío del correo solo al paciente.');
    }

    public function download(Request $request, PedidoLaboratorio $pedido, PedidoLaboratorioPdfService $pdfs)
    {
        $resultado = $this->currentPublished($pedido);
        if (! $resultado) {
            return back()->withErrors(['error' => 'No hay resultados publicados para descargar.']);
        }

        abort_unless($pdfs->usuarioAutorizadoParaResultado($pedido, $resultado, Auth::user()), 403);

        $this->audit($pedido, $resultado, 'descargado', Auth::user(), [
            'version' => $resultado->version,
        ]);

        $disposition = $request->query('disposition', 'attachment');
        return $pdfs->streamResultadoFile($resultado, $disposition);
    }

    private function loadPedido(PedidoLaboratorio $pedido): PedidoLaboratorio
    {
        return $pedido->loadMissing([
            'cita.paciente',
            'cita.dependiente.responsable',
            'cita.doctor',
            'doctor',
            'paciente',
            'resultados.laboratorio',
        ]);
    }

    private function validatePayload(Request $request, PedidoLaboratorio $pedido): array
    {
        $pedido->loadMissing('resultados');

        $rules = [
            'observaciones_generales' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1'],
        ];

        foreach ((array) $pedido->examenes as $key) {
            $rules['items.'.$key.'.resultado'] = ['required', 'string', 'max:255'];
            $rules['items.'.$key.'.unidad'] = ['nullable', 'string', 'max:80'];
            $rules['items.'.$key.'.referencia'] = ['nullable', 'string', 'max:255'];
            $rules['items.'.$key.'.clasificacion'] = ['required', Rule::in(['normal', 'alto', 'bajo', 'critico'])];
            $rules['items.'.$key.'.metodo'] = ['nullable', 'string', 'max:255'];
            $rules['items.'.$key.'.observaciones'] = ['nullable', 'string', 'max:1000'];
        }

        return $request->validate($rules, [
            'items.required' => 'Debes completar al menos un resultado.',
            'items.*.resultado.required' => 'Cada examen requiere un resultado.',
            'items.*.clasificacion.required' => 'Cada examen requiere una clasificación.',
        ]);
    }

    private function editableResultado(PedidoLaboratorio $pedido): PedidoLaboratorioResultado
    {
        $latest = $pedido->resultados()->orderByDesc('version')->first();

        if ($latest && $latest->estado === PedidoLaboratorioResultado::ESTADO_BORRADOR) {
            return $latest;
        }

        $nextVersion = ($latest?->version ?? 0) + 1;
        $seed = $latest?->resultado_items ?? [];

        return $pedido->resultados()->create([
            'version' => $nextVersion,
            'estado' => PedidoLaboratorioResultado::ESTADO_BORRADOR,
            'resultado_items' => $seed,
            'observaciones_generales' => $latest?->observaciones_generales,
            'laboratorio_id' => Auth::id(),
        ]);
    }

    private function currentPublished(PedidoLaboratorio $pedido): ?PedidoLaboratorioResultado
    {
        return $pedido->resultados()
            ->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)
            ->orderByDesc('version')
            ->first();
    }

    private function fillResultado(
        PedidoLaboratorioResultado $resultado,
        PedidoLaboratorio $pedido,
        array $validated,
        LabTestCatalogService $catalog,
        string $estado,
        ?\App\Models\User $user
    ): void {
        $items = [];

        foreach ((array) $pedido->examenes as $key) {
            $row = (array) data_get($validated, 'items.'.$key, []);
            $items[] = [
                'key' => $key,
                'nombre' => $catalog->label($key),
                'resultado' => trim((string) ($row['resultado'] ?? '')),
                'unidad' => trim((string) ($row['unidad'] ?? '')),
                'referencia' => trim((string) ($row['referencia'] ?? '')),
                'clasificacion' => trim((string) ($row['clasificacion'] ?? 'normal')),
                'metodo' => trim((string) ($row['metodo'] ?? '')),
                'observaciones' => trim((string) ($row['observaciones'] ?? '')),
            ];
        }

        $resultado->forceFill([
            'estado' => $estado,
            'resultado_items' => $items,
            'observaciones_generales' => trim((string) ($validated['observaciones_generales'] ?? '')) ?: null,
            'laboratorio_id' => $user?->id,
        ]);
    }

    private function buildFormItems(PedidoLaboratorio $pedido, PedidoLaboratorioResultado $resultado, LabTestCatalogService $catalog): array
    {
        $saved = collect($resultado->resultado_items ?? [])->keyBy('key');
        $items = [];

        foreach ((array) $pedido->examenes as $key) {
            $current = $saved->get($key, []);
            $items[] = [
                'key' => $key,
                'nombre' => $catalog->label($key),
                'resultado' => $current['resultado'] ?? '',
                'unidad' => $current['unidad'] ?? '',
                'referencia' => $current['referencia'] ?? '',
                'clasificacion' => $current['clasificacion'] ?? 'normal',
                'metodo' => $current['metodo'] ?? '',
                'observaciones' => $current['observaciones'] ?? '',
            ];
        }

        return $items;
    }

    private function audit(PedidoLaboratorio $pedido, ?PedidoLaboratorioResultado $resultado, string $accion, ?\App\Models\User $user, array $meta = []): void
    {
        PedidoLaboratorioResultadoAudit::query()->create([
            'pedido_laboratorio_id' => $pedido->id,
            'pedido_laboratorio_resultado_id' => $resultado?->id,
            'user_id' => $user?->id,
            'accion' => $accion,
            'meta' => $meta ?: null,
        ]);
    }
}
