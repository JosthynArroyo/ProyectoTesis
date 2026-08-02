<?php

namespace App\Http\Controllers;

use App\Services\ClinicIdentityService;
use App\Services\DocumentoCsvService;
use Illuminate\Http\Request;

class DocumentoVerificacionController extends Controller
{
    public function create()
    {
        return view('documentos.verificar');
    }

    public function search(Request $request)
    {
        $data = $request->validate([
            'csv' => ['required', 'string', 'max:32'],
        ]);

        return redirect()->route('documentos.verificar.show', [
            'csv' => strtoupper(trim($data['csv'])),
        ]);
    }

    public function show(string $csv, DocumentoCsvService $documents, ClinicIdentityService $clinic)
    {
        $documento = $documents->findDocumento($csv);

        if (! $documento) {
            abort(404);
        }

        $viewData = $this->buildVerificationData($documento, $csv, $clinic);

        if ($viewData === null) {
            abort(404);
        }

        return view('documentos.verificacion-show', $viewData);
    }

    // ─── Helpers ────────────────────────────────────────────────────────────────

    private function buildVerificationData(array $documento, string $csv, ClinicIdentityService $clinic): ?array
    {
        $tipo = $documento['tipo'] ?? null;

        $base = [
            'csv'      => $csv,
            'tipo'     => $tipo,
            'titulo'   => $documento['titulo'] ?? 'Documento médico',
            'clinica'  => $clinic->institutionalName(),
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
                $cert->loadMissing(['doctor', 'paciente']);
                return array_merge($base, [
                    'doctor'     => optional($cert->doctor)->name,
                    'paciente'   => $this->protectedName(optional($cert->paciente)->name),
                    'emitido_en' => $cert->fecha_emision,
                    'estado'     => 'Verificado',
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
