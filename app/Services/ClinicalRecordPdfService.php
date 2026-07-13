<?php

namespace App\Services;

use App\Models\ClinicalRecord;
use Carbon\Carbon;
use Dompdf\Dompdf;
use Dompdf\Options;

class ClinicalRecordPdfService
{
    public function generate(ClinicalRecord $record, array $viewData): string
    {
        $subject = app(ClinicalRecordService::class)->subjectData($record);
        $age = $subject->fecha_nacimiento ? Carbon::parse($subject->fecha_nacimiento)->age : null;

        $html = view('pdf.expediente-clinico', [
            'record' => $record,
            'patient' => $subject,
            'age' => $age,
            'representativeName' => $subject->representante ?? null,
        ] + $viewData)->render();

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $options->set('isFontSubsettingEnabled', true);
        $options->set('tempDir', storage_path('app'));

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
