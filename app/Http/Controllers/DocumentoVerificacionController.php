<?php

namespace App\Http\Controllers;

use App\Services\ClinicIdentityService;
use App\Services\DocumentoCsvService;
use Illuminate\Http\Request;

class DocumentoVerificacionController extends Controller
{
    public const ERROR_DOCUMENTO_NO_ENCONTRADO = 'No se encontró un documento asociado al código ingresado. Verifique el código e intente nuevamente.';

    public function create()
    {
        return view('documentos.verificar');
    }

    public function search(Request $request, DocumentoCsvService $documents)
    {
        $data = $request->validate([
            'csv' => ['required', 'string', 'max:32'],
        ]);

        $csv = strtoupper(trim($data['csv']));
        $documento = $documents->findDocumento($csv);

        if (! $documento) {
            return back()
                ->withErrors(['csv' => self::ERROR_DOCUMENTO_NO_ENCONTRADO])
                ->withInput();
        }

        return redirect()->route('documentos.verificar.show', [
            'csv' => $csv,
        ]);
    }

    public function show(string $csv, DocumentoCsvService $documents, ClinicIdentityService $clinic)
    {
        $documento = $documents->findDocumento($csv);

        if (! $documento) {
            return redirect()
                ->route('documentos.verificar.form')
                ->withErrors(['csv' => self::ERROR_DOCUMENTO_NO_ENCONTRADO])
                ->withInput(['csv' => $csv]);
        }

        $viewData = $this->buildVerificationData($documento, $csv, $clinic);

        if ($viewData === null) {
            return redirect()
                ->route('documentos.verificar.form')
                ->withErrors(['csv' => self::ERROR_DOCUMENTO_NO_ENCONTRADO])
                ->withInput(['csv' => $csv]);
        }

        return view('documentos.verificacion-show', $viewData);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function buildVerificationData(array $documento, string $csv, ClinicIdentityService $clinic): ?array
    {
        $tipo = $documento['tipo'] ?? null;

        $base = [
            'csv'         => $csv,
            'tipo'        => $tipo,
            'titulo'      => $documento['titulo'] ?? 'Documento médico',
            'clinica'     => $clinic->institutionalName(),
            'doctor'      => null,
            'paciente'    => null,
            'version'     => null,
            'folio'       => null,
            'monto'       => null,
            'metodo_pago' => null,
        ];

        switch ($tipo) {
            case 'receta':
                $receta = $documento['receta'];
                $receta->loadMissing(['cita.doctor', 'cita.paciente', 'cita.especialidad']);
                return array_merge($base, [
                    'doctor'     => optional($receta->cita?->doctor)->name,
                    'paciente'   => $this->protectedName(optional($receta->cita?->paciente)->name),
                    'emitido_en' => $receta->created_at,
                    'estado'     => 'Verificado',
                ]);

            case 'certificado_medico':
                $cert = $documento['certificado'];
                $cert->loadMissing(['doctor', 'paciente', 'dependiente', 'cita.dependiente']);
                $estadoClean = $cert->isReemplazado() ? 'Reemplazado' : 'Verificado';
                return array_merge($base, [
                    'doctor'     => optional($cert->doctor)->name,
                    'paciente'   => $this->protectedName($cert->nombrePacienteReal()),
                    'version'    => 'V'.($cert->version ?: 1),
                    'emitido_en' => $cert->fecha_emision,
                    'estado'     => $estadoClean,
                ]);

            case 'pedido_laboratorio':
                $pedido = $documento['pedido'];
                $pedido->loadMissing(['doctor', 'paciente']);
                return array_merge($base, [
                    'doctor'     => optional($pedido->doctor)->name,
                    'paciente'   => $this->protectedName(optional($pedido->paciente)->name),
                    'emitido_en' => $pedido->created_at,
                    'estado'     => 'Verificado',
                ]);

            case 'resultado_laboratorio':
                $resultado = $documento['resultado'] ?? null;
                $pacienteName = $resultado?->pedido?->paciente?->name;
                $doctorName = $resultado?->pedido?->doctor?->name ?: ($resultado?->pedido?->cita?->doctor?->name ?? null);

                return array_merge($base, [
                    'titulo'     => 'Informe de laboratorio',
                    'doctor'     => $doctorName,
                    'paciente'   => $this->protectedName($pacienteName),
                    'version'    => $resultado ? 'V'.$resultado->version : null,
                    'emitido_en' => $resultado?->publicado_at,
                    'estado'     => 'Verificado',
                ]);

            case 'orden_cobro':
                $pago = $documento['pago'];
                $pago->loadMissing('paciente');
                $estadoClean = match($pago->estado) {
                    'pagado' => 'Pagado',
                    'en_verificacion' => 'En verificación',
                    'rechazado' => 'Rechazado',
                    'anulado' => 'Anulado',
                    default => 'Pendiente',
                };
                return array_merge($base, [
                    'titulo' => 'Orden de cobro',
                    'folio' => $pago->folio_unico ?: 'Sin Folio',
                    'paciente' => $this->protectedName($pago->paciente?->name),
                    'emitido_en' => $pago->created_at,
                    'monto' => '$' . number_format((float) $pago->monto, 2) . ' ' . ($pago->moneda ?: 'USD'),
                    'estado' => $estadoClean,
                ]);

            case 'recibo_pago':
                $receipt = $documento['receipt'];
                $receipt->loadMissing(['pago.paciente']);
                $estadoClean = ($receipt->pago?->estado === 'anulado') ? 'Anulado' : 'Pagado';
                return array_merge($base, [
                    'titulo' => 'Recibo de pago',
                    'folio' => $receipt->folio_recibo,
                    'paciente' => $this->protectedName($receipt->pago?->paciente?->name),
                    'emitido_en' => $receipt->emitido_en ?: $receipt->created_at,
                    'monto' => '$' . number_format((float) $receipt->monto, 2) . ' ' . ($receipt->pago?->moneda ?: 'USD'),
                    'metodo_pago' => strtoupper((string) $receipt->metodo_pago),
                    'estado' => $estadoClean,
                ]);

            case 'comprobante_cita':
                $cita = $documento['cita'];
                $cita->loadMissing(['paciente', 'doctor', 'especialidad', 'dependiente']);
                $estadoClean = match($cita->estado) {
                    'pendiente' => 'Pendiente',
                    'confirmada' => 'Confirmada',
                    'cancelada' => 'Cancelada',
                    'realizada' => 'Realizada',
                    'no_se_presento' => 'No se presentó',
                    default => ucfirst((string) $cita->estado),
                };
                $fechaStr = $cita->fecha instanceof \Carbon\Carbon
                    ? $cita->fecha->format('Y-m-d')
                    : (string) $cita->fecha;
                $citaDateTime = \Carbon\Carbon::parse($fechaStr . ' ' . $cita->hora);
                $pacienteName = $cita->dependiente?->nombre ?? $cita->paciente?->name;
                return array_merge($base, [
                    'titulo' => 'Comprobante de cita',
                    'folio' => $cita->folio_cita ?: 'Sin Folio',
                    'paciente' => $this->protectedName($pacienteName),
                    'emitido_en' => $citaDateTime,
                    'doctor' => $cita->doctor?->name,
                    'especialidad' => $cita->especialidad?->nombre,
                    'estado' => $estadoClean,
                ]);

            default:
                return null;
        }
    }

    /**
     * Returns first name + last initial for privacy protection.
     * E.g. "Josthyn Arroyo Mendez" → "Josthyn A."
     */
    private function protectedName(?string $name): ?string
    {
        if (! $name) {
            return null;
        }

        $parts = preg_split('/\s+/', trim($name), -1, PREG_SPLIT_NO_EMPTY);

        if (empty($parts)) {
            return null;
        }

        $firstName = $parts[0];
        $lastInitial = isset($parts[1]) ? strtoupper(mb_substr($parts[1], 0, 1)).'.' : null;

        return $lastInitial ? "{$firstName} {$lastInitial}" : $firstName;
    }
}
