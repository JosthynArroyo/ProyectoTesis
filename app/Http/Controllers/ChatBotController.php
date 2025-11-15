<?php

namespace App\Http\Controllers;

use App\Mail\CuentaCreadaDesdeChat;
use App\Models\Cita;
use App\Models\Especialidad;
use App\Models\Horario;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ChatBotController extends Controller
{
    public function index()
    {
        return view('chatbot.index');
    }

    public function especialidades()
    {
        return response()->json(
            Especialidad::orderBy('nombre')->get(['id', 'nombre'])
        );
    }

    public function doctoresPorEspecialidad(Especialidad $especialidad)
    {
        $doctores = User::doctors()
            ->onlyActive()
            ->whereHas('especialidades', fn($q) => $q->where('especialidades.id', $especialidad->id))
            ->orderBy('name')
            ->get(['id', 'name']);

        if ($doctores->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No contamos con médicos activos para esta especialidad de momento.',
            ], 404);
        }

        return response()->json([
            'ok'            => true,
            'especialidad'  => $especialidad->nombre,
            'doctores'      => $doctores->map(fn($d) => [
                'id'     => $d->id,
                'nombre' => $d->name,
            ])->values(),
        ]);
    }

    public function fechasDisponibles(User $doctor)
    {
        abort_unless($doctor->hasRole('doctor'), 404);

        $hoy = Carbon::today();
        $horarios = Horario::where('doctor_id', $doctor->id)
            ->whereDate('fecha', '>=', $hoy)
            ->orderBy('fecha')
            ->limit(30)
            ->get()
            ->groupBy(fn($h) => Carbon::parse($h->fecha)->toDateString());

        $fechas = [];
        foreach ($horarios as $fecha => $bloques) {
            $slotsLibres = $this->calcularSlotsDisponibles($doctor->id, $fecha, $bloques);
            if (empty($slotsLibres)) {
                continue;
            }

            $fechas[] = [
                'value' => $fecha,
                'label' => Carbon::parse($fecha)->locale('es')->isoFormat('dddd D [de] MMMM'),
            ];

            if (count($fechas) >= 7) {
                break;
            }
        }

        if (empty($fechas)) {
            return response()->json([
                'ok'      => false,
                'message' => 'Este médico no tiene horarios libres próximamente.',
            ], 404);
        }

        return response()->json([
            'ok'     => true,
            'doctor' => ['id' => $doctor->id, 'nombre' => $doctor->name],
            'fechas' => $fechas,
        ]);
    }

    public function agendar(Request $request)
    {
        $data = $request->validate([
            'nombre'          => ['required','string','max:255'],
            'cedula'          => ['nullable','string','max:20'],
            'email'           => ['required','email','max:255'],
            'telefono'        => ['required','string','max:30'],
            'especialidad_id' => ['required','exists:especialidades,id'],
            'doctor_id'       => ['required','integer','exists:users,id'],
            'fecha'           => ['required','date_format:Y-m-d','after_or_equal:today'],
            'hora'            => ['required','date_format:H:i'],
            'motivo'          => ['nullable','string','max:500'],
        ]);

        $doctor = User::doctors()->onlyActive()->findOrFail($data['doctor_id']);

        if (! $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->exists()) {
            return response()->json([
                'ok'      => false,
                'message' => 'El doctor seleccionado no pertenece a esa especialidad.',
            ], 422);
        }

        $fecha = Carbon::parse($data['fecha'])->toDateString();
        if (! $this->slotDisponible($doctor->id, $fecha, $data['hora'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'El horario escogido ya no está disponible.',
            ], 422);
        }

        $user = User::where('email', $data['email'])->first();
        $passwordPlano = null;

        if (! $user) {
            $passwordPlano = Str::random(10);

            $user = User::create([
                'name'     => $data['nombre'],
                'email'    => $data['email'],
                'password' => bcrypt($passwordPlano),
                'dni'      => $data['cedula'],
                'telefono' => $data['telefono'],
                'status'   => 'active',
            ]);

            if ($rol = Role::where('name', 'paciente')->first()) {
                $user->roles()->syncWithoutDetaching([$rol->id]);
            }

            Mail::to($user->email)->queue(new CuentaCreadaDesdeChat($user, $passwordPlano));
        }

        $cita = Cita::create([
            'paciente_id'     => $user->id,
            'doctor_id'       => $doctor->id,
            'especialidad_id' => $data['especialidad_id'],
            'fecha'           => $fecha,
            'hora'            => $data['hora'],
            'estado'          => Cita::ESTADO_PENDIENTE,
            'activo'          => true,
        ]);

        return response()->json([
            'ok'   => true,
            'message' => 'Tu cita ha sido agendada correctamente.',
            'cita' => [
                'id'            => $cita->id,
                'fecha'         => $cita->fecha->format('d/m/Y'),
                'hora'          => substr($cita->hora, 0, 5),
                'estado'        => $cita->estado,
                'doctor'        => $doctor->name,
                'especialidad'  => $doctor->especialidades()->where('especialidad_id', $data['especialidad_id'])->value('nombre'),
            ],
            'usuario_creado' => (bool) $passwordPlano,
        ]);
    }

    public function buscarCitas(Request $request)
    {
        $data = $request->validate([
            'cedula' => ['nullable','string','max:20'],
            'email'  => ['nullable','email','max:255'],
        ]);

        if (empty($data['cedula']) && empty($data['email'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Debes proporcionar tu cédula o tu correo.',
                'citas'   => [],
            ], 422);
        }

        $userQuery = User::query();

        if (!empty($data['email'])) {
            $userQuery->where('email', $data['email']);
        } elseif (!empty($data['cedula'])) {
            $userQuery->where('dni', $data['cedula']);
        }

        $user = $userQuery->first();

        if (! $user) {
            return response()->json([
                'ok'      => false,
                'message' => 'No encontramos pacientes con esos datos.',
                'citas'   => [],
            ]);
        }

        $hoy = Carbon::today();

        $citas = Cita::with(['doctor:id,name', 'especialidad:id,nombre'])
            ->where('paciente_id', $user->id)
            ->whereDate('fecha', '>=', $hoy)
            ->where('activo', true)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        if ($citas->isEmpty()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No tienes citas próximas registradas.',
                'citas'   => [],
            ]);
        }

        $respuesta = $citas->map(function ($cita) {
            return [
                'id'           => $cita->id,
                'doctor_id'    => $cita->doctor_id,
                'especialidad_id' => $cita->especialidad_id,
                'fecha'        => $cita->fecha instanceof Carbon ? $cita->fecha->format('d/m/Y') : Carbon::parse($cita->fecha)->format('d/m/Y'),
                'hora'         => substr($cita->hora, 0, 5),
                'estado'       => $cita->estado,
                'doctor'       => optional($cita->doctor)->name,
                'especialidad' => optional($cita->especialidad)->nombre,
            ];
        });

        return response()->json([
            'ok'       => true,
            'message'  => 'Citas encontradas.',
            'paciente' => $user->name,
            'citas'    => $respuesta,
        ]);
    }

    public function cancelar(Request $request)
    {
        $data = $request->validate([
            'cita_id' => ['required','integer','exists:citas_medicas,id'],
        ]);

        $cita = Cita::findOrFail($data['cita_id']);

        if (Carbon::parse($cita->fecha)->isPast()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No es posible cancelar citas pasadas.',
            ], 422);
        }

        $cita->estado = Cita::ESTADO_CANCELADA;
        $cita->activo = false;
        $cita->save();

        return response()->json([
            'ok'      => true,
            'message' => 'Tu cita fue cancelada correctamente.',
        ]);
    }

    public function reagendar(Request $request)
    {
        $data = $request->validate([
            'cita_id' => ['required','integer','exists:citas_medicas,id'],
            'fecha'   => ['required','date_format:Y-m-d','after_or_equal:today'],
            'hora'    => ['required','date_format:H:i'],
        ]);

        $cita = Cita::findOrFail($data['cita_id']);

        if (Carbon::parse($cita->fecha)->isPast()) {
            return response()->json([
                'ok'      => false,
                'message' => 'No se pueden reprogramar citas pasadas.',
            ], 422);
        }

        $nuevaFecha = Carbon::parse($data['fecha'])->toDateString();

        if (! $this->slotDisponible($cita->doctor_id, $nuevaFecha, $data['hora'])) {
            return response()->json([
                'ok'      => false,
                'message' => 'Ese horario ya no está disponible.',
            ], 422);
        }

        $cita->fecha  = $nuevaFecha;
        $cita->hora   = $data['hora'];
        $cita->estado = Cita::ESTADO_PENDIENTE;
        $cita->activo = true;
        $cita->save();

        return response()->json([
            'ok'      => true,
            'message' => 'La cita fue reprogramada con éxito.',
        ]);
    }

    protected function slotDisponible(int $doctorId, string $fecha, string $hora): bool
    {
        $libres = $this->calcularSlotsDisponibles($doctorId, $fecha);
        return in_array($hora, $libres, true);
    }

    protected function calcularSlotsDisponibles(int $doctorId, string $fecha, $bloques = null): array
    {
        $bloques = $bloques ?: Horario::where('doctor_id', $doctorId)
            ->whereDate('fecha', $fecha)
            ->get();

        if ($bloques->isEmpty()) {
            return [];
        }

        $ocupadas = Cita::where('doctor_id', $doctorId)
            ->whereDate('fecha', $fecha)
            ->where('activo', true)
            ->pluck('hora')
            ->map(fn($hora) => substr($hora, 0, 5))
            ->toArray();

        $slots = [];
        foreach ($bloques as $horario) {
            $step = property_exists($horario, 'intervalo_minutos') && $horario->intervalo_minutos
                ? (int) $horario->intervalo_minutos
                : 30;

            $inicio = Carbon::parse("{$fecha} {$horario->hora_inicio}");
            $fin    = Carbon::parse("{$fecha} {$horario->hora_fin}");

            for ($cursor = $inicio->copy(); $cursor->lt($fin); $cursor->addMinutes($step)) {
                $hhmm = $cursor->format('H:i');
                if (! in_array($hhmm, $ocupadas, true)) {
                    $slots[] = $hhmm;
                }
            }
        }

        sort($slots);
        return array_values(array_unique($slots));
    }
}
