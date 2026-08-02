<?php

namespace App\Http\Controllers;

use App\Services\DocumentoCsvService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function show(string $csv, DocumentoCsvService $documents)
    {
        $documento = $documents->findDocumento($csv);

        if (! $documento || empty($documento['pdf_path'])) {
            abort(404);
        }

        if (($documento['tipo'] ?? null) === 'receta' && isset($documento['receta'])) {
            return app(\App\Services\RecipeDocumentService::class)->streamInline($documento['receta'], $documento['nombre_descarga']);
        }

        if (($documento['tipo'] ?? null) === 'certificado_medico' && isset($documento['certificado'])) {
            return app(\App\Services\MedicalCertificateDocumentService::class)->streamInline($documento['certificado'], $documento['nombre_descarga']);
        }

        if (! Storage::disk('local')->exists($documento['pdf_path'])) {
            abort(404);
        }

        return response()->file(
            Storage::disk('local')->path($documento['pdf_path']),
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="'.$documento['nombre_descarga'].'"',
            ]
        );
    }

}
