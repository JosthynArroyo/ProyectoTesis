<?php

namespace Database\Seeders;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\NotaSoap;
use App\Models\NotaSoapDiagnostico;
use App\Models\Receta;
use Illuminate\Database\Seeder;

class DemoClinicalHistorySeeder extends Seeder
{
    public function run(): void
    {
        $completedCitas = Cita::where('estado', Cita::ESTADO_REALIZADA)
            ->with(['doctor', 'paciente'])
            ->orderBy('fecha', 'asc')
            ->get();

        if ($completedCitas->isEmpty()) {
            return;
        }

        $diagnosticosCatalog = [
            ['cie10' => 'I10', 'desc' => 'Hipertensión esencial (primaria)', 'meds' => "1. Losartán 50 mg vía oral cada 24 horas por 30 días.\n2. Amlodipino 5 mg vía oral cada 24 horas por 30 días.", 'plan' => 'Monitoreo diario de presión arterial. Reducción de sal y grasas saturadas.'],
            ['cie10' => 'K29.7', 'desc' => 'Gastritis, no especificada', 'meds' => "1. Omeprazol 20 mg vía oral 30 minutos antes del desayuno por 14 días.\n2. Hidróxido de aluminio/magnesio 10 ml después de las comidas por 7 días.", 'plan' => 'Evitar café, condimentos, alcohol y comidas copiosas.'],
            ['cie10' => 'J00', 'desc' => 'Rinofaringitis aguda [resfriado común]', 'meds' => "1. Paracetamol 500 mg vía oral cada 8 horas por 3 días si hay malestar.\n2. Loratadina 10 mg vía oral cada 24 horas por 5 días.", 'plan' => 'Abundante hidratación oral, reposo relativo y medidas de higiene respiratoria.'],
            ['cie10' => 'L20.9', 'desc' => 'Dermatitis atópica, no especificada', 'meds' => "1. Cetirizina 10 mg vía oral cada 24 horas en la noche por 10 días.\n2. Crema emoliente con ceramidas aplicar cada 8 horas sobre piel limpia.", 'plan' => 'Evitar jabones perfumados y baños prolongados con agua muy caliente.'],
            ['cie10' => 'E11.9', 'desc' => 'Diabetes mellitus tipo 2, sin mención de complicación', 'meds' => '1. Metformina 850 mg vía oral con el almuerzo por 30 días.', 'plan' => 'Plan nutricional bajo en carbohidratos simples, caminata 30 minutos al día.'],
        ];

        $recetaCounter = 0;
        $citasByDoctor = $completedCitas->groupBy('doctor_id');

        foreach ($completedCitas as $idx => $cita) {
            // 1. Asegurar historia clínica del paciente
            $clinicalRecord = ClinicalRecord::firstOrCreate(
                ['patient_id' => $cita->paciente_id],
                [
                    'allergies_status' => ClinicalRecord::ALLERGIES_NONE,
                    'clinical_summary' => 'Paciente en seguimiento médico ambulatorio. Controles periódicos de salud.',
                    'last_reviewed_at' => $cita->fecha,
                ]
            );

            $diag = $diagnosticosCatalog[$idx % count($diagnosticosCatalog)];

            // 2. Crear Nota SOAP firmada
            $soap = NotaSoap::updateOrCreate(
                ['cita_id' => $cita->id],
                [
                    'clinical_record_id' => $clinicalRecord->id,
                    'estado' => NotaSoap::ESTADO_FIRMADA,
                    'signed_at' => $cita->fecha->format('Y-m-d').' '.$cita->hora,
                    'signed_by' => $cita->doctor_id,
                    'subjetivo_motivo' => $cita->motivo_consulta,
                    'subjetivo_hpi' => 'Paciente acude refiriendo sintomatología de 5 días de evolución caracterizada por '.strtolower($cita->motivo_consulta),
                    'signos_vitales' => [
                        'pa_sistolica' => rand(110, 130),
                        'pa_diastolica' => rand(70, 85),
                        'fc' => rand(65, 82),
                        'fr' => rand(16, 20),
                        'temperatura' => rand(362, 370) / 10,
                        'spo2' => rand(96, 99),
                        'peso' => rand(62, 85),
                        'talla' => 1.68,
                        'imc' => 24.5,
                    ],
                    'examen_fisico' => 'Paciente orientado en tiempo y espacio, afebril, hemodinámicamente estable. Mucosas húmedas, campos pulmonares ventilados, ruidos cardíacos rítmicos.',
                    'assessment' => 'Cuadro clínico compatible con '.$diag['desc'].' de evolución favorable.',
                    'plan_general' => $diag['plan'],
                    'plan_seguimiento' => 'Control médico en 30 días o antes en caso de signos de alarma.',
                ]
            );

            // 3. Crear Diagnóstico CIE-10
            NotaSoapDiagnostico::updateOrCreate(
                ['nota_soap_id' => $soap->id, 'cie10' => $diag['cie10']],
                [
                    'texto' => $diag['desc'],
                    'tipo' => 'principal',
                ]
            );

            // 4. Crear Certificado Médico (para algunas citas)
            if ($idx === 1 || $idx === 4 || $idx === 7) {
                $codigoCert = sprintf('CERT-2026-%04d', $idx + 1);
                CertificadoMedico::updateOrCreate(
                    ['codigo' => $codigoCert],
                    [
                        'cita_id' => $cita->id,
                        'paciente_id' => $cita->paciente_id,
                        'doctor_id' => $cita->doctor_id,
                        'clinical_record_id' => $clinicalRecord->id,
                        'estado_version' => CertificadoMedico::ESTADO_VIGENTE,
                        'version' => 1,
                        'fecha_emision' => $cita->fecha->format('Y-m-d').' 12:00:00',
                        'texto_constancia' => 'Por medio del presente se certifica que el/la paciente fue atendido(a) en consulta médica ambulatoria presentando cuadro de '.$diag['desc'].', por lo cual se recomienda reposo médico.',
                        'dias_reposo' => 2,
                        'reposo_desde' => $cita->fecha->format('Y-m-d'),
                        'reposo_hasta' => $cita->fecha->addDays(2)->format('Y-m-d'),
                        'observaciones' => 'Se expide para los fines pertinentes que convengan al interesado.',
                        'csv' => $codigoCert,
                    ]
                );
            }
        }

        // 5. Crear Recetas distribuidas estratégicamente por doctor
        // El doctor canónico Dr. Fernando Alvarado (Medicina General) recibe 3 recetas (incluyendo al paciente canónico Javier Espinoza).
        // Los demás doctores reciben 1-2 recetas para asegurar coherencia y variedad en toda la clínica.
        foreach ($citasByDoctor as $doctorId => $doctorCitas) {
            $doctor = $doctorCitas->first()->doctor;
            $isCanonicalDoctor = ($doctor && $doctor->email === 'doctor.medicina@demo-clinigest.test');

            // 3 recetas para el doctor canónico, 1-2 para los demás (priorizando citas más recientes)
            $recetasLimit = $isCanonicalDoctor ? 3 : (($doctorId % 2 === 0) ? 2 : 1);
            $selectedCitas = $doctorCitas->sortByDesc('fecha')->values()->take($recetasLimit);

            foreach ($selectedCitas as $cIdx => $cita) {
                $recetaCounter++;
                $soap = NotaSoap::where('cita_id', $cita->id)->first();
                $diag = $diagnosticosCatalog[($recetaCounter - 1) % count($diagnosticosCatalog)];
                $clinicalRecord = ClinicalRecord::where('patient_id', $cita->paciente_id)->first();

                Receta::updateOrCreate(
                    ['cita_id' => $cita->id],
                    [
                        'nota_soap_id' => $soap?->id,
                        'clinical_record_id' => $clinicalRecord?->id,
                        'diagnostico' => $diag['desc'],
                        'medicamentos' => $diag['meds'],
                        'indicaciones' => $diag['plan'],
                        'csv' => sprintf('REC-2026-%05d', $cita->id + 1000),
                        'pdf_path' => sprintf('documents/recipes/%d/receta_%d.pdf', $cita->id, $cita->id),
                        'pdf_disk' => 'local',
                        'enviado_en' => $cita->fecha->format('Y-m-d').' '.$cita->hora,
                    ]
                );
            }
        }
    }
}
