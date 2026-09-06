<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Horario;
use App\Models\LabTest;
use App\Models\PedidoLaboratorio;
use App\Models\PedidoLaboratorioResultado;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DemoLaboratorySeeder extends Seeder
{
    public function run(): void
    {
        $labUser = User::where('email', 'laboratorio@demo-clinigest.test')->first()
            ?? User::whereHas('roles', fn ($q) => $q->where('name', 'laboratorio'))->first();

        if ($labUser) {
            $windowStart = now()->subDays(14)->startOfDay();
            $windowEnd = now()->addDays(180)->endOfDay();

            // 1. Depurar bloques demo obsoletos del usuario de laboratorio fuera de la ventana rodante
            Horario::where('doctor_id', $labUser->id)
                ->where(function ($q) use ($windowStart, $windowEnd) {
                    $q->whereDate('fecha', '<', $windowStart->toDateString())
                        ->orWhereDate('fecha', '>', $windowEnd->toDateString());
                })
                ->delete();

            // 2. Generar disponibilidad ambulatoria realista para el laboratorio (Lunes a Viernes)
            // Franjas calculadas dinámicamente desde now() en una ventana amplia de 180 días (~6 meses)
            for ($d = -14; $d <= 180; $d++) {
                $targetDate = now()->addDays($d);
                if (! $targetDate->isWeekday()) {
                    continue;
                }

                $fechaStr = $targetDate->format('Y-m-d');

                // Bloque matutino: toma y recepción de muestras (08:00 a 12:30)
                Horario::firstOrCreate(
                    [
                        'doctor_id' => $labUser->id,
                        'fecha' => $fechaStr,
                        'hora_inicio' => '08:00:00',
                        'hora_fin' => '12:30:00',
                    ],
                    [
                        'intervalo_minutos' => 30,
                    ]
                );

                // Bloque vespertino: procesamiento y validación (13:30 a 17:00)
                Horario::firstOrCreate(
                    [
                        'doctor_id' => $labUser->id,
                        'fecha' => $fechaStr,
                        'hora_inicio' => '13:30:00',
                        'hora_fin' => '17:00:00',
                    ],
                    [
                        'intervalo_minutos' => 30,
                    ]
                );
            }
        }

        $citas = Cita::where('estado', Cita::ESTADO_REALIZADA)
            ->with(['doctor', 'paciente'])
            ->get();

        if ($citas->isEmpty()) {
            return;
        }

        $labTests = LabTest::pluck('nombre')->toArray();
        if (empty($labTests)) {
            $labTests = ['Hemograma completo', 'Perfil lipídico', 'Glucosa en ayunas', 'Examen general de orina'];
        }

        $otherIndex = 0;

        foreach ($citas as $idx => $cita) {
            $orderCsv = sprintf('ORD-2026-%05d', 1000 + $cita->id);
            $orderPdfPath = "documents/laboratory-orders/{$cita->id}/orden_{$cita->id}.pdf";
            $resultCsv = sprintf('LAB-2026-%05d', 1000 + $cita->id);
            $resultPdfPath = "documents/laboratory-results/{$cita->id}/resultado_{$cita->id}.pdf";

            $isCanonicalDoctor = ($cita->doctor && $cita->doctor->email === 'doctor.medicina@demo-clinigest.test');

            // El doctor canónico Dr. Fernando Alvarado cuenta con todos sus resultados publicados para demostración
            if ($isCanonicalDoctor) {
                $estado = PedidoLaboratorio::ESTADO_RESULTADO_LISTO;
                $sampleCollected = $cita->fecha->addDays(1)->format('Y-m-d 08:30:00');
                $processedAt = $cita->fecha->addDays(1)->format('Y-m-d 14:00:00');
                $publishedAt = $cita->fecha->addDays(1)->format('Y-m-d 16:30:00');
                $resumen = 'Parámetros dentro de los rangos de referencia normales. Sin hallazgos patológicos significativos.';
                $resultadoPath = $resultPdfPath;
            } else {
                // Otros doctores presentan variedad de estados (muestras tomadas y pendientes de toma)
                if ($otherIndex % 2 === 0) {
                    $estado = PedidoLaboratorio::ESTADO_MUESTRA_TOMADA;
                    $sampleCollected = now()->subDays(1)->format('Y-m-d 09:00:00');
                    $processedAt = null;
                    $publishedAt = null;
                    $resumen = null;
                    $resultadoPath = null;
                } else {
                    $estado = PedidoLaboratorio::ESTADO_PENDIENTE_TOMA;
                    $sampleCollected = null;
                    $processedAt = null;
                    $publishedAt = null;
                    $resumen = null;
                    $resultadoPath = null;
                }
                $otherIndex++;
            }

            $selectedExams = [
                $labTests[$idx % count($labTests)],
                $labTests[($idx + 1) % count($labTests)],
            ];

            $pedido = PedidoLaboratorio::updateOrCreate(
                ['cita_id' => $cita->id],
                [
                    'paciente_id' => $cita->paciente_id,
                    'doctor_id' => $cita->doctor_id,
                    'csv' => $orderCsv,
                    'examenes' => array_values(array_unique($selectedExams)),
                    'pdf_path' => $orderPdfPath,
                    'pdf_disk' => 'local',
                    'estado' => $estado,
                    'sample_collected_at' => $sampleCollected,
                    'processed_at' => $processedAt,
                    'resultado_path' => $resultadoPath,
                    'resultado_publicado_at' => $publishedAt,
                    'resultado_resumen' => $resumen,
                ]
            );

            // Si el resultado está listo, crear el resultado detallado publicado
            if ($estado === PedidoLaboratorio::ESTADO_RESULTADO_LISTO) {
                PedidoLaboratorioResultado::updateOrCreate(
                    ['pedido_laboratorio_id' => $pedido->id, 'version' => 1],
                    [
                        'estado' => PedidoLaboratorioResultado::ESTADO_PUBLICADO,
                        'laboratorio_id' => $labUser?->id,
                        'csv' => $resultCsv,
                        'pdf_path' => $resultPdfPath,
                        'pdf_disk' => 'local',
                        'publicado_at' => $publishedAt,
                        'observaciones_generales' => 'Muestra procesada en analizador automatizado. Control de calidad validado.',
                        'resultado_items' => [
                            [
                                'examen' => 'Glucosa en ayunas',
                                'parametro' => 'Glucosa sérica',
                                'resultado' => '92',
                                'unidad' => 'mg/dL',
                                'referencia' => '70 - 100 mg/dL',
                                'estado' => 'normal',
                            ],
                            [
                                'examen' => 'Perfil lipídico',
                                'parametro' => 'Colesterol Total',
                                'resultado' => '182',
                                'unidad' => 'mg/dL',
                                'referencia' => '< 200 mg/dL',
                                'estado' => 'normal',
                            ],
                            [
                                'examen' => 'Perfil lipídico',
                                'parametro' => 'Triglicéridos',
                                'resultado' => '135',
                                'unidad' => 'mg/dL',
                                'referencia' => '< 150 mg/dL',
                                'estado' => 'normal',
                            ],
                        ],
                    ]
                );
            } else {
                PedidoLaboratorioResultado::where('pedido_laboratorio_id', $pedido->id)->delete();
            }
        }
    }
}
