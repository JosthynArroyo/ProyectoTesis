<?php

namespace Database\Seeders;

use App\Models\CertificadoMedico;
use App\Models\Cita;
use App\Models\ClinicalRecord;
use App\Models\Dependiente;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\NotaSoap;
use App\Models\Pago;
use App\Models\PatientFlag;
use App\Models\PaymentReceipt;
use App\Models\PedidoLaboratorio;
use App\Models\Receta;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DemoUsersSeeder extends Seeder
{
    public const CANONICAL_DEMO_EMAILS = [
        'superadmin@demo-clinigest.test',
        'admin@demo-clinigest.test',
        'laboratorio@demo-clinigest.test',
        'doctor.medicina@demo-clinigest.test',
        'doctor.pediatria@demo-clinigest.test',
        'doctor.ginecologia@demo-clinigest.test',
        'paciente@demo-clinigest.test',
        'paciente01@demo-clinigest.test',
        'paciente02@demo-clinigest.test',
    ];

    public function run(): void
    {
        $password = Hash::make('Demo1234!');

        $superadminRole = Role::firstOrCreate(['name' => 'superadmin'], ['label' => 'Superadministrador']);
        $adminRole = Role::firstOrCreate(['name' => 'administrador'], ['label' => 'Administrador']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor'], ['label' => 'Doctor']);
        $pacienteRole = Role::firstOrCreate(['name' => 'paciente'], ['label' => 'Paciente']);
        $labRole = Role::firstOrCreate(['name' => 'laboratorio'], ['label' => 'Laboratorio']);

        // 0. Depurar usuarios demo obsoletos fuera de los 9 autorizados
        self::pruneObsoleteDemoUsers();

        // 1. Superadmin
        $superadmin = User::updateOrCreate(
            ['email' => 'superadmin@demo-clinigest.test'],
            [
                'name' => 'Ing. Mateo Villacís',
                'password' => $password,
                'dni' => '0912345601',
                'telefono' => '0991111111',
                'direccion' => 'Av. Francisco de Orellana 100',
                'fecha_nacimiento' => '1980-03-15',
                'sexo' => 'Masculino',
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
            ]
        );
        $superadmin->roles()->syncWithoutDetaching([$superadminRole->id]);

        // 2. Administrador
        $admin = User::updateOrCreate(
            ['email' => 'admin@demo-clinigest.test'],
            [
                'name' => 'Dra. Valeria Mendoza',
                'password' => $password,
                'dni' => '0912345602',
                'telefono' => '0992222222',
                'direccion' => 'Av. Plaza Dañín y Constitución',
                'fecha_nacimiento' => '1985-07-22',
                'sexo' => 'Femenino',
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
            ]
        );
        $admin->roles()->syncWithoutDetaching([$adminRole->id]);

        // 3. Laboratorio
        $labUser = User::updateOrCreate(
            ['email' => 'laboratorio@demo-clinigest.test'],
            [
                'name' => 'Lic. Carlos Morales',
                'password' => $password,
                'dni' => '0912345603',
                'telefono' => '0993333333',
                'direccion' => 'Área de Laboratorio Clínico Central',
                'fecha_nacimiento' => '1988-11-05',
                'sexo' => 'Masculino',
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
            ]
        );
        $labUser->roles()->syncWithoutDetaching([$labRole->id]);

        // 4. 3 Doctores con Especialidades Diferenciadas
        $especialidades = Especialidad::pluck('id', 'nombre');

        $doctoresData = [
            [
                'name' => 'Dr. Fernando Alvarado',
                'email' => 'doctor.medicina@demo-clinigest.test',
                'dni' => '0912345610',
                'telefono' => '0994444410',
                'sexo' => 'Masculino',
                'especialidad' => 'Medicina General',
                'precio' => 30.00,
            ],
            [
                'name' => 'Dra. Carmen Delgado',
                'email' => 'doctor.pediatria@demo-clinigest.test',
                'dni' => '0912345611',
                'telefono' => '0994444411',
                'sexo' => 'Femenino',
                'especialidad' => 'Pediatría',
                'precio' => 40.00,
            ],
            [
                'name' => 'Dra. Elena Paredes',
                'email' => 'doctor.ginecologia@demo-clinigest.test',
                'dni' => '0912345613',
                'telefono' => '0994444413',
                'sexo' => 'Femenino',
                'especialidad' => 'Ginecología',
                'precio' => 45.00,
            ],
        ];

        foreach ($doctoresData as $docInfo) {
            $doctor = User::updateOrCreate(
                ['email' => $docInfo['email']],
                [
                    'name' => $docInfo['name'],
                    'password' => $password,
                    'dni' => $docInfo['dni'],
                    'telefono' => $docInfo['telefono'],
                    'direccion' => 'Av. de las Américas 1240 y Plaza Médica',
                    'fecha_nacimiento' => '1982-04-10',
                    'sexo' => $docInfo['sexo'],
                    'status' => User::STATUS_ACTIVE,
                    'must_change_password' => false,
                ]
            );
            $doctor->roles()->syncWithoutDetaching([$doctorRole->id]);

            if (isset($especialidades[$docInfo['especialidad']])) {
                $espId = $especialidades[$docInfo['especialidad']];
                $doctor->especialidades()->syncWithoutDetaching([$espId]);
            }
        }

        // Generar disponibilidad médica con ventana rodante sostenible de 180 días para los 3 doctores
        self::seedDoctorSchedules();

        // 5. Paciente principal demo (Javier Espinoza)
        $pacienteDemo = User::updateOrCreate(
            ['email' => 'paciente@demo-clinigest.test'],
            [
                'name' => 'Javier Espinoza',
                'password' => $password,
                'dni' => '0912345700',
                'telefono' => '0995555700',
                'direccion' => 'Cdla. Kennedy Norte Mz 104 Sl 12',
                'fecha_nacimiento' => '1986-09-14',
                'sexo' => 'Masculino',
                'status' => User::STATUS_ACTIVE,
                'must_change_password' => false,
            ]
        );
        $pacienteDemo->roles()->syncWithoutDetaching([$pacienteRole->id]);

        PatientFlag::updateOrCreate(
            ['user_id' => $pacienteDemo->id],
            [
                'adulto_mayor' => false,
                'embarazo' => false,
                'discapacidad' => false,
                'cronico' => true,
            ]
        );

        // Dependientes del paciente demo (Lucas/Mateo Espinoza [hijo] y Lucía Barreno [madre])
        Dependiente::firstOrCreate(
            ['user_id' => $pacienteDemo->id, 'dni' => '0912345799'],
            [
                'nombre' => 'Mateo Espinoza',
                'fecha_nacimiento' => '2016-04-12',
                'sexo' => 'Masculino',
                'parentesco' => 'hijo',
                'activo' => true,
            ]
        );

        Dependiente::firstOrCreate(
            ['user_id' => $pacienteDemo->id, 'dni' => '0912345798'],
            [
                'nombre' => 'Lucía Barreno',
                'fecha_nacimiento' => '1958-11-03',
                'sexo' => 'Femenino',
                'parentesco' => 'madre',
                'activo' => true,
            ]
        );

        // 6. Dos pacientes complementarios representativos
        $pacientesComplementarios = [
            [
                'name' => 'María José Andrade',
                'email' => 'paciente01@demo-clinigest.test',
                'dni' => '0920000001',
                'telefono' => '0980000001',
                'fecha' => '1992-05-18',
                'sexo' => 'Femenino',
                'cronico' => false,
                'embarazo' => true,
                'adulto_mayor' => false,
            ],
            [
                'name' => 'Roberto Sánchez Romero',
                'email' => 'paciente02@demo-clinigest.test',
                'dni' => '0920000002',
                'telefono' => '0980000002',
                'fecha' => '1955-08-20',
                'sexo' => 'Masculino',
                'cronico' => true,
                'embarazo' => false,
                'adulto_mayor' => true,
            ],
        ];

        foreach ($pacientesComplementarios as $pData) {
            $pUser = User::updateOrCreate(
                ['email' => $pData['email']],
                [
                    'name' => $pData['name'],
                    'password' => $password,
                    'dni' => $pData['dni'],
                    'telefono' => $pData['telefono'],
                    'direccion' => 'Guayaquil - Sector Kennedy',
                    'fecha_nacimiento' => $pData['fecha'],
                    'sexo' => $pData['sexo'],
                    'status' => User::STATUS_ACTIVE,
                    'must_change_password' => false,
                ]
            );
            $pUser->roles()->syncWithoutDetaching([$pacienteRole->id]);

            PatientFlag::updateOrCreate(
                ['user_id' => $pUser->id],
                [
                    'adulto_mayor' => $pData['adulto_mayor'],
                    'embarazo' => $pData['embarazo'],
                    'discapacidad' => false,
                    'cronico' => $pData['cronico'],
                ]
            );
        }
    }

    /**
     * Purga usuarios demo que ya no forman parte de las 9 cuentas autorizadas y limpia sus datos en cascada.
     */
    public static function pruneObsoleteDemoUsers(): void
    {
        $obsoleteUsers = User::where('email', 'like', '%@demo-clinigest.test')
            ->whereNotIn('email', self::CANONICAL_DEMO_EMAILS)
            ->get();

        if ($obsoleteUsers->isEmpty()) {
            return;
        }

        $userIds = $obsoleteUsers->pluck('id')->all();

        // 1. Eliminar pagos y recibos asociados a citas o pacientes obsoletos
        $citasIds = Cita::whereIn('paciente_id', $userIds)
            ->orWhereIn('doctor_id', $userIds)
            ->pluck('id')
            ->all();

        $pagosIds = Pago::whereIn('paciente_id', $userIds)
            ->orWhereIn('cita_id', $citasIds)
            ->pluck('id')
            ->all();

        PaymentReceipt::whereIn('pago_id', $pagosIds)->delete();
        Pago::whereIn('id', $pagosIds)->delete();

        // 2. Eliminar recetas, certificados y pedidos de laboratorio asociados
        Receta::whereIn('cita_id', $citasIds)->delete();
        CertificadoMedico::whereIn('cita_id', $citasIds)
            ->orWhereIn('paciente_id', $userIds)
            ->orWhereIn('doctor_id', $userIds)
            ->delete();

        PedidoLaboratorio::whereIn('cita_id', $citasIds)
            ->orWhereIn('paciente_id', $userIds)
            ->orWhereIn('doctor_id', $userIds)
            ->delete();

        // 3. Eliminar notas SOAP y diagnósticos
        $soaps = NotaSoap::whereIn('cita_id', $citasIds)->get();
        foreach ($soaps as $soap) {
            $soap->diagnosticos()->delete();
            $soap->enmiendas()->delete();
            $soap->delete();
        }

        // 4. Eliminar citas
        Cita::whereIn('id', $citasIds)->delete();

        // 5. Eliminar horarios de médicos obsoletos
        Horario::whereIn('doctor_id', $userIds)->delete();

        // 6. Eliminar historias clínicas, flags y dependientes de pacientes obsoletos
        ClinicalRecord::whereIn('patient_id', $userIds)->delete();
        PatientFlag::whereIn('user_id', $userIds)->delete();
        Dependiente::whereIn('user_id', $userIds)->delete();

        // 7. Desvincular roles y especialidades, y eliminar usuarios
        foreach ($obsoleteUsers as $user) {
            $user->roles()->detach();
            $user->especialidades()->detach();
            $user->delete();
        }
    }

    /**
     * Genera disponibilidad médica sostenible para los 3 doctores demo en una ventana rodante de 180 días.
     */
    public static function seedDoctorSchedules(): void
    {
        $doctorsConfig = [
            'doctor.medicina@demo-clinigest.test' => [
                ['inicio' => '08:00:00', 'fin' => '12:30:00', 'intervalo' => 30],
                ['inicio' => '13:30:00', 'fin' => '17:00:00', 'intervalo' => 30],
            ],
            'doctor.pediatria@demo-clinigest.test' => [
                ['inicio' => '08:30:00', 'fin' => '12:30:00', 'intervalo' => 30],
                ['inicio' => '14:00:00', 'fin' => '16:30:00', 'intervalo' => 30],
            ],
            'doctor.ginecologia@demo-clinigest.test' => [
                ['inicio' => '08:00:00', 'fin' => '12:00:00', 'intervalo' => 30],
                ['inicio' => '13:30:00', 'fin' => '16:30:00', 'intervalo' => 30],
            ],
        ];

        $doctors = User::whereIn('email', array_keys($doctorsConfig))->get()->keyBy('email');
        if ($doctors->isEmpty()) {
            return;
        }

        $windowStart = now()->subDays(14)->startOfDay();
        $windowEnd = now()->addDays(180)->endOfDay();
        $doctorIds = $doctors->pluck('id')->all();

        // 1. Depurar bloques demo fuera de los doctores seleccionados o fuera de la ventana
        Horario::whereNotIn('doctor_id', array_merge($doctorIds, User::whereHas('roles', fn ($q) => $q->where('name', 'laboratorio'))->pluck('id')->all()))
            ->delete();

        Horario::whereIn('doctor_id', $doctorIds)
            ->where(function ($q) use ($windowStart, $windowEnd) {
                $q->whereDate('fecha', '<', $windowStart->toDateString())
                    ->orWhereDate('fecha', '>', $windowEnd->toDateString());
            })
            ->delete();

        // 2. Generar franjas horarias realistas Lunes a Viernes en ventana de 180 días
        for ($d = -14; $d <= 180; $d++) {
            $targetDate = now()->addDays($d);
            if (! $targetDate->isWeekday()) {
                continue;
            }

            $fechaStr = $targetDate->format('Y-m-d');

            foreach ($doctorsConfig as $email => $shifts) {
                if (! isset($doctors[$email])) {
                    continue;
                }

                $docId = $doctors[$email]->id;
                foreach ($shifts as $shift) {
                    Horario::firstOrCreate(
                        [
                            'doctor_id' => $docId,
                            'fecha' => $fechaStr,
                            'hora_inicio' => $shift['inicio'],
                            'hora_fin' => $shift['fin'],
                        ],
                        [
                            'intervalo_minutos' => $shift['intervalo'],
                        ]
                    );
                }
            }
        }
    }
}
