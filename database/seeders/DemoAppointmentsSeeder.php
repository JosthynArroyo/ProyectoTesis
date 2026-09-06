<?php

namespace Database\Seeders;

use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoAppointmentsSeeder extends Seeder
{
    public function run(): void
    {
        $doctors = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'doctor'))->with('especialidades')->get();
        $patients = User::whereHas('roles', fn ($q) => $q->where('roles.name', 'paciente'))->with('dependientes')->get();

        if ($doctors->isEmpty() || $patients->isEmpty()) {
            return;
        }

        $especialidades = Especialidad::all()->keyBy('id');

        $pacienteJavier = $patients->firstWhere('email', 'paciente@demo-clinigest.test') ?? $patients->first();
        $pacienteMaria = $patients->firstWhere('email', 'paciente01@demo-clinigest.test') ?? $patients->get(1, $pacienteJavier);
        $pacienteRoberto = $patients->firstWhere('email', 'paciente02@demo-clinigest.test') ?? $patients->get(2, $pacienteJavier);

        $docMedicina = $doctors->firstWhere('email', 'doctor.medicina@demo-clinigest.test') ?? $doctors->first();
        $docPediatria = $doctors->firstWhere('email', 'doctor.pediatria@demo-clinigest.test') ?? $doctors->get(1, $docMedicina);
        $docGinecologia = $doctors->firstWhere('email', 'doctor.ginecologia@demo-clinigest.test') ?? $doctors->get(2, $docMedicina);

        $depHijo = $pacienteJavier->dependientes->firstWhere('parentesco', 'hijo');
        $depMadre = $pacienteJavier->dependientes->firstWhere('parentesco', 'madre');

        // Definir blueprint de citas ricas y representativas
        $citasBlueprint = [
            // ─── 1. Javier Espinoza (Canónico) ───
            // Citas pasadas realizadas
            [
                'paciente' => $pacienteJavier,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => -45,
                'hora' => '08:30:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_ALTA,
                'motivo' => 'Control de hipertensión arterial y ajuste de medicación habitual.',
            ],
            [
                'paciente' => $pacienteJavier,
                'dependiente' => $depHijo,
                'doctor' => $docPediatria,
                'offset_days' => -30,
                'hora' => '09:00:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_MEDIA,
                'motivo' => 'Control pediátrico de rutina y revisión de esquema de vacunación infantil.',
            ],
            [
                'paciente' => $pacienteJavier,
                'dependiente' => $depMadre,
                'doctor' => $docMedicina,
                'offset_days' => -18,
                'hora' => '10:00:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_ALTA,
                'motivo' => 'Evaluación médica integral geriátrica por dolores articulares difusos.',
            ],
            [
                'paciente' => $pacienteJavier,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => -7,
                'hora' => '11:00:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_MEDIA,
                'motivo' => 'Seguimiento y lectura de exámenes de laboratorio ambulatorios.',
            ],
            // Citas pasadas no exitosas
            [
                'paciente' => $pacienteJavier,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => -12,
                'hora' => '15:30:00',
                'estado' => Cita::ESTADO_CANCELADA,
                'prioridad' => Cita::PRIORIDAD_BAJA,
                'motivo' => 'Cita cancelada con anticipación por motivos de viaje laboral.',
            ],
            // Citas futuras
            [
                'paciente' => $pacienteJavier,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => 5,
                'hora' => '09:00:00',
                'estado' => Cita::ESTADO_CONFIRMADA,
                'prioridad' => Cita::PRIORIDAD_ALTA,
                'motivo' => 'Control semestral preventivo y chequeo clínico general.',
            ],
            [
                'paciente' => $pacienteJavier,
                'dependiente' => $depHijo,
                'doctor' => $docPediatria,
                'offset_days' => 8,
                'hora' => '10:30:00',
                'estado' => Cita::ESTADO_PENDIENTE,
                'prioridad' => Cita::PRIORIDAD_MEDIA,
                'motivo' => 'Evaluación pediátrica de crecimiento y desarrollo.',
            ],

            // ─── 2. María José Andrade (Embarazo / Ginecología / Medicina General) ───
            [
                'paciente' => $pacienteMaria,
                'dependiente' => null,
                'doctor' => $docGinecologia,
                'offset_days' => -40,
                'hora' => '08:30:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_ALTA,
                'motivo' => 'Chequeo ginecológico y evaluación prenatal de primer trimestre.',
            ],
            [
                'paciente' => $pacienteMaria,
                'dependiente' => null,
                'doctor' => $docGinecologia,
                'offset_days' => -20,
                'hora' => '09:30:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_ALTA,
                'motivo' => 'Control ecográfico obstétrico de seguimiento y suplementación.',
            ],
            [
                'paciente' => $pacienteMaria,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => -10,
                'hora' => '11:30:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_MEDIA,
                'motivo' => 'Evaluación de cuadro respiratorio leve e hidratación oral.',
            ],
            [
                'paciente' => $pacienteMaria,
                'dependiente' => null,
                'doctor' => $docGinecologia,
                'offset_days' => -5,
                'hora' => '14:00:00',
                'estado' => Cita::ESTADO_NO_SE_PRESENTO,
                'prioridad' => Cita::PRIORIDAD_BAJA,
                'motivo' => 'Paciente no acudió por inconveniente de transporte.',
            ],
            [
                'paciente' => $pacienteMaria,
                'dependiente' => null,
                'doctor' => $docGinecologia,
                'offset_days' => 6,
                'hora' => '09:00:00',
                'estado' => Cita::ESTADO_CONFIRMADA,
                'prioridad' => Cita::PRIORIDAD_ALTA,
                'motivo' => 'Control prenatal mensual y revisión de curva de peso fetal.',
            ],

            // ─── 3. Roberto Sánchez Romero (Adulto Mayor / Crónico) ───
            [
                'paciente' => $pacienteRoberto,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => -50,
                'hora' => '08:00:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_ALTA,
                'motivo' => 'Evaluación integral por hipertensión arterial y fatiga matutina.',
            ],
            [
                'paciente' => $pacienteRoberto,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => -25,
                'hora' => '09:00:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_MEDIA,
                'motivo' => 'Control de valores de glicemia en ayunas y régimen dietético.',
            ],
            [
                'paciente' => $pacienteRoberto,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => -8,
                'hora' => '10:00:00',
                'estado' => Cita::ESTADO_REALIZADA,
                'prioridad' => Cita::PRIORIDAD_MEDIA,
                'motivo' => 'Evaluación de dolor lumbar mecánico y prescripción analgésica.',
            ],
            [
                'paciente' => $pacienteRoberto,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => -15,
                'hora' => '16:00:00',
                'estado' => Cita::ESTADO_CANCELADA,
                'prioridad' => Cita::PRIORIDAD_BAJA,
                'motivo' => 'Cancelación solicitada por paciente para reprogramar con familiares.',
            ],
            [
                'paciente' => $pacienteRoberto,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => 7,
                'hora' => '08:30:00',
                'estado' => Cita::ESTADO_CONFIRMADA,
                'prioridad' => Cita::PRIORIDAD_MEDIA,
                'motivo' => 'Control mensual de presión arterial y seguimiento farmacológico.',
            ],
            [
                'paciente' => $pacienteRoberto,
                'dependiente' => null,
                'doctor' => $docMedicina,
                'offset_days' => 12,
                'hora' => '11:00:00',
                'estado' => Cita::ESTADO_PENDIENTE,
                'prioridad' => Cita::PRIORIDAD_BAJA,
                'motivo' => 'Revisión preventiva anual de laboratorio.',
            ],
        ];

        // Rastrear slots asignados para garantizar 100% de unicidad (doctor_id, fecha, hora)
        $usedSlots = [];

        foreach ($citasBlueprint as $idx => $blueprint) {
            $doctor = $blueprint['doctor'];
            $paciente = $blueprint['paciente'];
            $dependiente = $blueprint['dependiente'];
            $espId = $doctor->especialidades->first()?->id ?? $especialidades->first()?->id;

            $fecha = now()->addDays($blueprint['offset_days'])->format('Y-m-d');
            $hora = $blueprint['hora'];

            $slotKey = "{$doctor->id}_{$fecha}_{$hora}";
            if (isset($usedSlots[$slotKey])) {
                $hora = sprintf('%02d:%02d:00', rand(8, 16), rand(0, 59));
            }
            $usedSlots[$slotKey] = true;

            $folio = sprintf('CIT-2026-%05d', $idx + 101);

            Cita::updateOrCreate(
                ['folio_cita' => $folio],
                [
                    'doctor_id' => $doctor->id,
                    'paciente_id' => $paciente->id,
                    'dependiente_id' => $dependiente?->id,
                    'especialidad_id' => $espId,
                    'fecha' => $fecha,
                    'hora' => $hora,
                    'motivo_consulta' => $blueprint['motivo'],
                    'estado' => $blueprint['estado'],
                    'activo' => true,
                    'prioridad_nivel' => $blueprint['prioridad'],
                    'prioridad_fuente' => 'auto',
                    'token_validacion' => Str::random(32),
                    'created_at' => now()->addDays($blueprint['offset_days'])->subHours(24),
                    'updated_at' => now()->addDays($blueprint['offset_days']),
                ]
            );
        }
    }
}
