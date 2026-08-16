<?php

namespace App\Http\Controllers\Laboratorio;

use App\Enums\LabResultClassification;
use App\Enums\LabResultType;
use App\Http\Controllers\Controller;
use App\Jobs\EnviarResultadoPedidoLaboratorioJob;
use App\Models\LaboratoryComponent;
use App\Models\LaboratoryResultValue;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\PedidoLaboratorioResultadoAudit;
use App\Services\DocumentoCsvService;
use App\Services\LabTestCatalogConfigService;
use App\Services\LabTestCatalogService;
use App\Services\PedidoLaboratorioPdfService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PedidoLaboratorioResultadoController extends Controller
{
    public function create(PedidoLaboratorio $pedido, LabTestCatalogConfigService $catalogConfig)
    {
        $pedido = $this->loadPedido($pedido);
        $resultado = $this->editableResultado($pedido);
        $examStructure = $catalogConfig->resolvePedidoItems($pedido, $resultado);

        return view('laboratorio.pedidos.resultados', [
            'pedido' => $pedido,
            'resultado' => $resultado,
            'examStructure' => $examStructure,
        ]);
    }

    public function draft(Request $request, PedidoLaboratorio $pedido, LabTestCatalogConfigService $catalogConfig)
    {
        $validated = $this->validateDynamicPayload($request, $pedido, $catalogConfig, false);

        DB::transaction(function () use ($pedido, $validated, $catalogConfig, $request): void {
            $pedido = $this->loadPedido(
                PedidoLaboratorio::query()->with('resultados')->whereKey($pedido->id)->lockForUpdate()->firstOrFail()
            );
            $resultado = $this->editableResultado($pedido);
            $this->fillDynamicResultado($resultado, $pedido, $validated, $catalogConfig, PedidoLaboratorioResultado::ESTADO_BORRADOR, $request->user());
            $resultado->save();
            $this->audit($pedido, $resultado, 'borrador_guardado', $request->user(), [
                'items' => count((array) $validated['items']),
            ]);
        });

        return redirect()
            ->route('laboratorio.pedidos.resultados.form', $pedido)
            ->with('success', 'Borrador guardado correctamente.');
    }

    public function preview(Request $request, PedidoLaboratorio $pedido, PedidoLaboratorioPdfService $pdfs, LabTestCatalogConfigService $catalogConfig)
    {
        $validated = $this->validateDynamicPayload($request, $pedido, $catalogConfig, false);

        $resultado = DB::transaction(function () use ($pedido, $validated, $catalogConfig, $request) {
            $pedido = $this->loadPedido(
                PedidoLaboratorio::query()->with('resultados')->whereKey($pedido->id)->lockForUpdate()->firstOrFail()
            );
            $resultado = $this->editableResultado($pedido);
            $this->fillDynamicResultado($resultado, $pedido, $validated, $catalogConfig, PedidoLaboratorioResultado::ESTADO_BORRADOR, $request->user());
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

    public function publish(Request $request, PedidoLaboratorio $pedido, PedidoLaboratorioPdfService $pdfs, LabTestCatalogConfigService $catalogConfig)
    {
        $validated = $this->validateDynamicPayload($request, $pedido, $catalogConfig, true);

        $pedido->loadMissing('resultados');
        $latestPublished = $pedido->resultados->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)->sortByDesc('version')->first();
        $hasDraft = $pedido->resultados->contains(fn (PedidoLaboratorioResultado $resultado) => $resultado->estado === PedidoLaboratorioResultado::ESTADO_BORRADOR);

        if ($latestPublished && $pedido->resultado_publicado_at && ! $hasDraft) {
            return redirect()
                ->route('laboratorio.pedidos.index')
                ->with('info', 'Este resultado ya fue publicado. Usa la corrección versionada si necesitas cambiarlo.');
        }

        $uuid = (string) Str::uuid();
        $pdfPath = null;
        $disk = Storage::disk('r2_private');

        try {
            [$resultado, $isNewPublication] = DB::transaction(function () use ($pedido, $validated, $pdfs, $catalogConfig, $request, $uuid, $disk, &$pdfPath) {
                $pedido = $this->loadPedido(
                    PedidoLaboratorio::query()->with(['resultados', 'cita.paciente', 'cita.dependiente.responsable', 'cita.doctor', 'doctor', 'paciente'])->whereKey($pedido->id)->lockForUpdate()->firstOrFail()
                );

                $latest = $pedido->resultados()->orderByDesc('version')->first();
                if ($latest && $latest->estado === PedidoLaboratorioResultado::ESTADO_PUBLICADO && $pedido->resultado_publicado_at && ! $pedido->resultados()->where('estado', PedidoLaboratorioResultado::ESTADO_BORRADOR)->exists()) {
                    return [$latest->fresh(['pedido']), false];
                }

                $resultado = $this->editableResultado($pedido);
                $previousPublished = $pedido->resultados()
                    ->where('estado', PedidoLaboratorioResultado::ESTADO_PUBLICADO)
                    ->orderByDesc('version')
                    ->lockForUpdate()
                    ->first();

                $this->fillDynamicResultado($resultado, $pedido, $validated, $catalogConfig, PedidoLaboratorioResultado::ESTADO_PUBLICADO, $request->user());

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
                    'processed_at' => $pedido->processed_at ?? now(),
                    'estado' => PedidoLaboratorio::ESTADO_RESULTADO_LISTO,
                ])->saveQuietly();

                $this->audit($pedido, $resultado, $previousPublished ? 'corregido' : 'publicado', $request->user(), [
                    'version' => $resultado->version,
                    'path' => $key,
                ]);

                return [$resultado->fresh(['pedido']), true];
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

        if (! $isNewPublication) {
            return redirect()
                ->route('laboratorio.pedidos.index')
                ->with('info', 'Este resultado ya fue publicado. Usa la corrección versionada si necesitas cambiarlo.');
        }

        try {
            EnviarResultadoPedidoLaboratorioJob::dispatch($resultado->id);
        } catch (\Throwable $dispatchException) {
            \Illuminate\Support\Facades\Log::error('Error despachando job de envío de resultado de laboratorio: ' . $dispatchException->getMessage());
        }

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

    private function validateDynamicPayload(Request $request, PedidoLaboratorio $pedido, LabTestCatalogConfigService $catalogConfig, bool $isPublishing = false): array
    {
        $examStructure = $catalogConfig->resolvePedidoItems($pedido);
        $rawItems = $request->input('items', []);

        if (empty($rawItems)) {
            throw ValidationException::withMessages(['items' => 'Debes completar al menos un resultado.']);
        }

        $errors = [];

        foreach ($examStructure as $exam) {
            $examCode = $exam['exam_code'];

            if (isset($rawItems[$examCode]['resultado'])) {
                continue;
            }

            foreach ($exam['components'] as $comp) {
                $code = $comp['code'];
                $compData = $rawItems[$code] ?? $rawItems[$examCode] ?? [];
                $type = $comp['result_type'];

                if ($isPublishing) {
                    if (isset($compData['resultado']) && trim((string)$compData['resultado']) !== '') {
                        continue;
                    }

                    if ($type === LabResultType::NUMERIC->value) {
                        $val = trim((string) ($compData['value_numeric'] ?? $compData['resultado'] ?? ''));
                        if ($val === '') {
                            $errors["items.{$code}.value_numeric"] = "El componente '{$comp['name']}' requiere un valor numérico.";
                        } elseif (!is_numeric($val)) {
                            $errors["items.{$code}.value_numeric"] = "El componente '{$comp['name']}' debe ser un número válido.";
                        }
                        if (isset($compData['comparator']) && $compData['comparator'] !== '') {
                            $compNorm = LaboratoryResultValue::normalizeComparator($compData['comparator']);
                            if (!in_array($compNorm, LaboratoryResultValue::allowedComparators(), true)) {
                                $errors["items.{$code}.comparator"] = "El operador seleccionado para '{$comp['name']}' no es válido.";
                            }
                        }
                    } elseif ($type === LabResultType::CODED->value) {
                        $codeVal = trim((string) ($compData['value_code'] ?? $compData['resultado'] ?? ''));
                        if ($codeVal === '') {
                            $errors["items.{$code}.value_code"] = "Selecciona una opción para '{$comp['name']}'.";
                        }
                    } elseif ($type === LabResultType::TITER->value) {
                        $titerVal = trim((string) ($compData['value_text'] ?? $compData['resultado'] ?? ''));
                        if ($titerVal === '') {
                            $errors["items.{$code}.value_text"] = "Ingresa la dilución o título para '{$comp['name']}'.";
                        }
                    } elseif ($type === LabResultType::BLOOD_GROUP->value) {
                        $abo = trim((string) ($compData['extra_data']['abo'] ?? ''));
                        $rh = trim((string) ($compData['extra_data']['rh'] ?? ''));
                        if ($abo === '' || $rh === '') {
                            $errors["items.{$code}.extra_data"] = "Selecciona el grupo ABO y el factor Rh para '{$comp['name']}'.";
                        }
                    } elseif ($type === LabResultType::CULTURE->value) {
                        $state = trim((string) ($compData['value_code'] ?? $compData['resultado'] ?? ''));
                        if ($state === '') {
                            $errors["items.{$code}.value_code"] = "Selecciona el estado del cultivo para '{$comp['name']}'.";
                        }
                    }

                    // Clasificación obligatoriamente manual
                    $classVal = trim((string) ($compData['classification'] ?? $compData['clasificacion'] ?? ''));
                    if ($classVal === '') {
                        $errors["items.{$code}.classification"] = "Selecciona la clasificación manual para '{$comp['name']}'.";
                    }

                    // Confirmación explícita de referencias provisionales
                    if (($comp['validation_status'] ?? 'provisional') === 'provisional') {
                        $isConfirmed = !empty($compData['confirm_reference']) || !empty($compData['reference']) || !empty($compData['resultado']) || !empty($compData['value_numeric']) || !empty($compData['value_code']) || !empty($compData['value_text']);
                        if (!$isConfirmed) {
                            $errors["items.{$code}.confirm_reference"] = "Debes confirmar o ingresar la referencia para '{$comp['name']}' antes de publicar.";
                        }
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw ValidationException::withMessages($errors);
        }

        return [
            'observaciones_generales' => $request->input('observaciones_generales'),
            'items' => $rawItems,
        ];
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

    private function fillDynamicResultado(
        PedidoLaboratorioResultado $resultado,
        PedidoLaboratorio $pedido,
        array $validated,
        LabTestCatalogConfigService $catalogConfig,
        string $estado,
        ?\App\Models\User $user
    ): void {
        $examStructure = $catalogConfig->resolvePedidoItems($pedido);
        $inputItems = (array) ($validated['items'] ?? []);

        // Sujeto de Atención Real (Dependiente o Titular)
        $pedido->loadMissing(['cita.dependiente', 'cita.paciente']);
        $isDependiente = (bool) ($pedido->cita?->dependiente_id && $pedido->cita?->dependiente);
        $pacienteReal = $isDependiente ? $pedido->cita->dependiente : ($pedido->cita?->paciente ?? $pedido->paciente);
        $subjectType = $isDependiente ? 'dependiente' : 'titular';
        $subjectId = $pacienteReal?->id;

        $sexReal = strtolower((string) ($pacienteReal?->sexo ?? 'both'));
        $birthDateReal = $pacienteReal?->fecha_nacimiento;

        if ($pedido->sample_collected_at) {
            $sampleDate = Carbon::parse($pedido->sample_collected_at);
            $ageSource = 'sample_collection';
        } elseif ($pedido->processed_at) {
            $sampleDate = Carbon::parse($pedido->processed_at);
            $ageSource = 'processing';
        } else {
            $sampleDate = $pedido->created_at ? Carbon::parse($pedido->created_at) : now();
            $ageSource = 'order_created_fallback';
        }

        $ageDaysReal = $birthDateReal ? (int) Carbon::parse($birthDateReal)->diffInDays($sampleDate) : 10950;

        LaboratoryResultValue::where('result_id', $resultado->id)->delete();

        $numericValuesMap = [];
        foreach ($examStructure as $exam) {
            foreach ($exam['components'] as $comp) {
                $code = $comp['code'];
                $compInput = (array) ($inputItems[$code] ?? $inputItems[$exam['exam_code']] ?? []);
                $valNum = trim((string) ($compInput['value_numeric'] ?? $compInput['resultado'] ?? ''));
                if (is_numeric($valNum)) {
                    $numericValuesMap[$code] = (float) $valNum;
                }
            }
        }

        $snapshotItems = [];

        foreach ($examStructure as $exam) {
            $examCode = $exam['exam_code'];
            $examName = $exam['exam_name'];

            $firstCompText = null;
            $firstCompUnit = null;
            $firstCompRef = null;
            $firstCompClass = 'normal';
            $firstCompMethod = null;
            $firstCompObs = null;

            foreach ($exam['components'] as $compIdx => $comp) {
                $code = $comp['code'];
                $compInput = (array) ($inputItems[$code] ?? $inputItems[$examCode] ?? []);
                $type = $comp['result_type'];

                $compModel = LaboratoryComponent::where('code', $code)->first();
                $compId = $compModel?->id;

                $valNumeric = null;
                $valCode = null;
                $valText = null;
                $titerDenom = null;
                $isCalculated = ($type === LabResultType::CALCULATED->value);
                $extraData = $compInput['extra_data'] ?? null;

                if ($type === LabResultType::NUMERIC->value) {
                    $rawNum = trim((string) ($compInput['value_numeric'] ?? $compInput['resultado'] ?? ''));
                    $rawComp = $compInput['comparator'] ?? null;
                    if (preg_match('/^(=|<|>|<=|>=|≤|≥)\s*(.+)$/u', $rawNum, $matches)) {
                        if ($rawComp === null || $rawComp === '') {
                            $rawComp = $matches[1];
                        }
                        $rawNum = trim($matches[2]);
                    }
                    $valNumeric = is_numeric($rawNum) ? (float) $rawNum : null;
                    $comparatorNorm = LaboratoryResultValue::normalizeComparator($rawComp);
                    $valText = $valNumeric !== null
                        ? LaboratoryResultValue::formatNumericResult($valNumeric, $comparatorNorm)
                        : trim((string)($compInput['resultado'] ?? ''));
                } elseif ($type === LabResultType::CODED->value) {
                    $valCode = trim((string) ($compInput['value_code'] ?? $compInput['resultado'] ?? ''));
                    $valText = Str::headline($valCode);
                } elseif ($type === LabResultType::TITER->value) {
                    $valText = trim((string) ($compInput['value_text'] ?? $compInput['resultado'] ?? ''));
                    if (preg_match('/1:(\d+)/', $valText, $m)) {
                        $titerDenom = $m[1];
                    }
                } elseif ($type === LabResultType::BLOOD_GROUP->value) {
                    $abo = $extraData['abo'] ?? '';
                    $rh = $extraData['rh'] ?? '';
                    $valText = trim("{$abo} {$rh}") ?: trim((string)($compInput['resultado'] ?? ''));
                } elseif ($type === LabResultType::CALCULATED->value) {
                    if ($code === 'bilirrubina_indirecta' && isset($numericValuesMap['bilirrubina_total'], $numericValuesMap['bilirrubina_directa'])) {
                        $valNumeric = max(0, $numericValuesMap['bilirrubina_total'] - $numericValuesMap['bilirrubina_directa']);
                    } elseif ($code === 'psa_ratio' && isset($numericValuesMap['psa_total'], $numericValuesMap['psa_libre']) && $numericValuesMap['psa_total'] > 0) {
                        $valNumeric = round(($numericValuesMap['psa_libre'] / $numericValuesMap['psa_total']) * 100, 2);
                    }
                    $valText = $valNumeric !== null ? (string) $valNumeric : '';
                } else {
                    $valText = trim((string) ($compInput['value_text'] ?? $compInput['resultado'] ?? ''));
                    $valCode = trim((string) ($compInput['value_code'] ?? ''));
                }

                // Clasificación Seleccionada Manualmente
                $rawClass = trim((string) ($compInput['classification'] ?? $compInput['clasificacion'] ?? 'normal'));
                $classificationEnum = LabResultClassification::tryFrom($rawClass) ?? LabResultClassification::NORMAL;

                // Modificaciones particulares (Overrides)
                $enteredUnit = trim((string) ($compInput['unit'] ?? $compInput['unidad'] ?? $comp['default_unit']));
                $enteredMethod = trim((string) ($compInput['method'] ?? $compInput['metodo'] ?? $comp['default_method']));
                $enteredRef = trim((string) ($compInput['reference'] ?? $compInput['referencia'] ?? $comp['reference_text']));

                $unitOverridden = ($enteredUnit !== ($comp['default_unit'] ?? ''));
                $methodOverridden = ($enteredMethod !== ($comp['default_method'] ?? ''));
                $refOverridden = ($enteredRef !== ($comp['reference_text'] ?? ''));
                $overrideReason = trim((string) ($compInput['override_reason'] ?? ''));

                if ($compId) {
                    LaboratoryResultValue::create([
                        'result_id' => $resultado->id,
                        'component_id' => $compId,
                        'value_numeric' => $valNumeric,
                        'comparator' => $type === LabResultType::NUMERIC->value ? ($comparatorNorm ?? '=') : null,
                        'value_code' => $valCode,
                        'value_text' => $valText,
                        'titer_denominator' => $titerDenom,
                        'unit_snapshot' => $enteredUnit,
                        'method_snapshot' => $enteredMethod,
                        'reference_snapshot' => $enteredRef,
                        'reference_type_snapshot' => $comp['reference_type'] ?? 'interval',
                        'classification' => $classificationEnum,
                        'observation' => trim((string) ($compInput['observation'] ?? $compInput['observaciones'] ?? '')),
                        'is_calculated' => $isCalculated,
                        'extra_data' => $extraData,
                        'subject_type' => $subjectType,
                        'subject_id' => $subjectId,
                        'patient_sex_snapshot' => $sexReal,
                        'patient_birth_date_snapshot' => $birthDateReal,
                        'patient_age_days_snapshot' => $ageDaysReal,
                        'age_calculation_date_snapshot' => $sampleDate,
                        'age_calculation_source' => $ageSource,
                        'reference_rule_id' => $comp['reference_rule_id'] ?? null,
                        'reference_was_overridden' => $refOverridden,
                        'method_was_overridden' => $methodOverridden,
                        'unit_was_overridden' => $unitOverridden,
                        'override_reason' => $overrideReason ?: null,
                        'reference_confirmed_for_result' => true,
                        'reference_confirmed_by' => $user?->id,
                        'reference_confirmed_at' => now(),
                        'entered_by' => $user?->id,
                        'validated_by' => $estado === PedidoLaboratorioResultado::ESTADO_PUBLICADO ? $user?->id : null,
                        'validated_at' => $estado === PedidoLaboratorioResultado::ESTADO_PUBLICADO ? now() : null,
                    ]);
                }

                if ($compIdx === 0) {
                    $firstCompText = $valText ?: ($compInput['resultado'] ?? '-');
                    $firstCompUnit = $enteredUnit;
                    $firstCompRef = $enteredRef;
                    $firstCompClass = $classificationEnum->value;
                    $firstCompMethod = $enteredMethod;
                    $firstCompObs = trim((string) ($compInput['observation'] ?? $compInput['observaciones'] ?? ''));
                }
            }

            $snapshotItems[] = [
                'key' => $examCode,
                'nombre' => $examName,
                'resultado' => $firstCompText ?: '-',
                'unidad' => $firstCompUnit ?: 'No aplica',
                'referencia' => $firstCompRef ?: 'No aplica',
                'clasificacion' => $firstCompClass,
                'metodo' => $firstCompMethod ?: 'Método institucional',
                'observaciones' => $firstCompObs ?: '',
            ];
        }

        $resultado->forceFill([
            'estado' => $estado,
            'resultado_items' => $snapshotItems,
            'observaciones_generales' => trim((string) ($validated['observaciones_generales'] ?? '')) ?: null,
            'laboratorio_id' => $user?->id,
        ]);
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
