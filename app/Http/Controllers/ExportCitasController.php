<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Cita;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportCitasController extends Controller
{
    public function exportarCitas(): StreamedResponse
    {

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Encabezados
        $sheet->setCellValue('A1', 'Paciente');
        $sheet->setCellValue('B1', 'Doctor');
        $sheet->setCellValue('C1', 'Fecha');
        $sheet->setCellValue('D1', 'Hora');

        // Datos
        $fila = 2;
        Cita::with(['paciente', 'doctor'])
            ->chunkById(500, function ($citas) use ($sheet, &$fila) {
                foreach ($citas as $cita) {
                    $sheet->setCellValue("A{$fila}", $cita->paciente->name ?? 'Sin paciente');
                    $sheet->setCellValue("B{$fila}", $cita->doctor->name ?? 'Sin asignar');
                    $sheet->setCellValue("C{$fila}", $cita->fecha);
                    $sheet->setCellValue("D{$fila}", $cita->hora);
                    $fila++;
                }

                // Liberar memoria del lote actual
                unset($citas);
            });

        // Nombre
        $fileName = 'citas_' . now()->format('Ymd_His') . '.xlsx';

        // Stream directo al navegador
        return response()->streamDownload(function () use ($spreadsheet) {
            // Limpia cualquier salida previa (evita corrupción del ZIP XLSX)
            if (ob_get_length()) {
                ob_end_clean();
            }
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $fileName, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            'Pragma' => 'no-cache',
        ]);
    }
}
