<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\Horario;
use App\Models\User;
use App\Services\CitaNoShowService;
use App\Services\DashboardAnalyticsService;
use App\Services\ProfileAvatarService;
use App\Support\DateField;
use App\Support\ValidationRules;
use App\Support\WeeklyCalendarData;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard(Request $request, DashboardAnalyticsService $analytics)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $user = Auth::user();
        $dashboard = $analytics->buildDoctorDashboard($user, $request->all());

        return view('doctor.dashboard', [
            'user' => $user,
            'dashboard' => $dashboard,
            ...$this->dashboardSnapshot($user),
        ]);
    }

    public function dashboardData(Request $request, DashboardAnalyticsService $analytics)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $snapshot = $this->dashboardSnapshot($request->user());
        $dashboard = $analytics->buildDoctorDashboard($request->user(), $request->all());

        return response()->json($dashboard + [
            'kpis' => [
                'hoy' => $snapshot['citasHoy'],
                'realizadas' => $snapshot['citasRealizadas'],
                'pendientes' => $snapshot['citasPendientes'],
                'confirmadas_2h' => $snapshot['citasConfirmadas2h'],
                'realizadas_2h' => $snapshot['citasRealizadas2h'],
                'canceladas_2h' => $snapshot['citasCanceladas2h'],
                'pacientes' => $snapshot['totalPacientes'],
            ],
            'citas' => $snapshot['citas']->map(fn ($c) => [
                'paciente' => $c->paciente?->name ?? 'Paciente',
                'estado' => $c->estado,
                'fecha' => $c->fecha,
                'fecha_corta' => optional($c->fecha)->format('d/m/Y'),
                'hora' => $c->hora,
                'hora_corta' => substr((string) $c->hora, 0, 5),
                'prioridad' => $c->prioridad_nivel,
                'red_flag' => (bool) $c->prioridad_red_flag,
            ]),
        ]);
    }

    private function dashboardSnapshot(User $user): array
    {
        $tz = 'America/Guayaquil';
        $hoy = Carbon::now($tz)->toDateString();
        $desde2h = Carbon::now($tz)->subHours(2);

        $base = Cita::query()->where('doctor_id', $user->id);

        $citasHoy = (clone $base)->whereDate('fecha', $hoy)->count();
        $citasRealizadas = (clone $base)->whereDate('fecha', $hoy)->where('estado', 'realizada')->count();
        $citasPendientes = (clone $base)->whereDate('fecha', $hoy)->where('estado', 'pendiente')->count();

        $citasConfirmadas2h = (clone $base)->where('estado', 'confirmada')->where('updated_at', '>=', $desde2h)->count();
        $citasRealizadas2h = (clone $base)->where('estado', 'realizada')->where('updated_at', '>=', $desde2h)->count();
        $citasCanceladas2h = (clone $base)->where('estado', 'cancelada')->where('updated_at', '>=', $desde2h)->count();
        $totalPacientes = (clone $base)->distinct('paciente_id')->count('paciente_id');

        $citas = (clone $base)
            ->with(['paciente:id,name'])
            ->whereDate('fecha', $hoy)
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('hora')
            ->limit(10)
            ->get(['id', 'paciente_id', 'estado', 'fecha', 'hora', 'prioridad_nivel', 'prioridad_red_flag']);

        return compact(
            'citas',
            'citasHoy',
            'citasRealizadas',
            'citasPendientes',
            'citasConfirmadas2h',
            'citasRealizadas2h',
            'citasCanceladas2h',
            'totalPacientes'
        );
    }

    public function editarPerfil()
    {
        $user = Auth::user();

        return view('doctor.perfil', compact('user'));
    }

    public function actualizarPerfil(Request $request, ProfileAvatarService $profileAvatars)
    {
        $user = Auth::user();
        DateField::mergeIntoRequest($request, 'fecha_nacimiento');

        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ValidationRules::emailUnique('users', $user->id),
            'telefono' => ValidationRules::telefono(),
            'tipo_documento' => ['nullable', 'in:cedula,pasaporte'],
            'nacionalidad' => ['required_if:tipo_documento,pasaporte', 'nullable', 'string', function ($attribute, $value, $fail) use ($request) {
                if ($request->input('tipo_documento') === 'pasaporte' && (! $value || ! \App\Support\CountryCatalog::isValidCode($value))) {
                    $fail('La nacionalidad es obligatoria cuando el documento es pasaporte.');
                }
            }],
            'dni' => ValidationRules::cedulaUnique('users', $user->id),
            'direccion' => ['required', 'string', 'max:255'],
            'fecha_nacimiento' => ValidationRules::birthDate(),
            'sexo' => ['required', 'in:Masculino,Femenino,Otro'],
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:2048'],
            'precio_consulta' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
            'moneda' => ['required', 'in:USD'],

            // cambio de contraseña (opcional)
            'current_password' => ['nullable', 'string'],
            'password' => array_merge(
                ValidationRules::passwordOptional(),
                ['different:current_password']
            ),
        ], [
            'password.regex' => 'La contraseña debe incluir letras, números y al menos un carácter especial.',
        ]);

        // avatar
        if ($request->hasFile('avatar')) {
            $user->avatar = $profileAvatars->replace($user, $request->file('avatar'), 'doctors');
        }

        // datos perfil
        $user->fill([
            'name' => $request->name,
            'email' => $request->email,
            'telefono' => $request->telefono,
            'tipo_documento' => $request->input('tipo_documento', 'cedula'),
            'nacionalidad' => $request->input('tipo_documento') === 'pasaporte' ? $request->input('nacionalidad') : null,
            'dni' => $request->dni,
            'direccion' => $request->direccion,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'sexo' => $request->sexo,
            'precio_consulta' => $request->precio_consulta,
            'moneda' => 'USD',
        ]);

        // cambio de contraseña si viene nueva
        $passwordChanged = false;
        if ($request->filled('password')) {
            if (! $request->filled('current_password') || ! Hash::check($request->input('current_password'), $user->password)) {
                return back()
                    ->withErrors(['current_password' => 'La contraseña actual no es correcta.'])
                    ->withInput();
            }
            $user->password = Hash::make($request->input('password'));
            $passwordChanged = true;
        }

        $user->save();

        if ($passwordChanged) {
            // invalidar "remember me" y sesiones previas
            $user->setRememberToken(Str::random(60));
            $user->save();

            // cerrar otras sesiones del usuario usando la NUEVA contraseña
            Auth::logoutOtherDevices($request->input('password'));

            // regenar sesión actual y volver a autenticar
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('doctor.perfil.edit')->with('success', 'Perfil actualizado.');
    }

    public function agenda(Request $request)
    {
        $doctorId = Auth::id();
        $weekStart = WeeklyCalendarData::resolveWeekStart($request->query('week'));
        $weekEnd = $weekStart->copy()->endOfWeek(Carbon::SUNDAY);

        $horarios = Horario::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('fecha', '>=', $weekStart->toDateString())
            ->whereDate('fecha', '<=', $weekEnd->toDateString())
            ->orderBy('fecha')
            ->orderBy('hora_inicio')
            ->get();

        $citas = Cita::query()
            ->with(['paciente:id,name', 'especialidad:id,nombre'])
            ->where('doctor_id', $doctorId)
            ->whereDate('fecha', '>=', $weekStart->toDateString())
            ->whereDate('fecha', '<=', $weekEnd->toDateString())
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        $calendarEntries = $horarios->map(function (Horario $horario) {
            return [
                'layer' => 'background',
                'date' => $horario->fecha,
                'start' => $horario->hora_inicio,
                'end' => $horario->hora_fin,
                'title' => 'Bloque disponible',
                'subtitle' => 'Atención activa',
                'eyebrow' => substr((string) $horario->hora_inicio, 0, 5).' - '.substr((string) $horario->hora_fin, 0, 5),
                'tone' => 'slate',
            ];
        })->values();

        $calendarEntries = $calendarEntries->concat(
            $citas->map(function (Cita $cita) use ($horarios) {
                $end = WeeklyCalendarData::inferEndTime(
                    $cita->fecha->toDateString(),
                    (string) $cita->hora,
                    $horarios
                );

                $statusLabel = match ($cita->estado) {
                    Cita::ESTADO_PENDIENTE => 'Pendiente',
                    Cita::ESTADO_CONFIRMADA => 'Confirmada',
                    Cita::ESTADO_REALIZADA => 'Realizada',
                    Cita::ESTADO_CANCELADA => 'Cancelada',
                    Cita::ESTADO_NO_SE_PRESENTO => 'No se presentó',
                    default => ucfirst((string) $cita->estado),
                };

                $tone = match ($cita->estado) {
                    Cita::ESTADO_PENDIENTE => 'amber',
                    Cita::ESTADO_CONFIRMADA => 'blue',
                    Cita::ESTADO_REALIZADA => 'emerald',
                    default => 'rose',
                };

                $prioritySuffix = $cita->prioridad_nivel ? ' · '.$cita->prioridad_nivel : '';

                return [
                    'layer' => 'foreground',
                    'date' => $cita->fecha,
                    'start' => $cita->hora,
                    'end' => $end,
                    'title' => $cita->paciente?->name ?? 'Paciente',
                    'subtitle' => $cita->especialidad?->nombre ?? 'Consulta',
                    'eyebrow' => $statusLabel.$prioritySuffix,
                    'meta' => $cita->prioridad_red_flag ? 'Red flag activa' : null,
                    'tone' => $tone,
                    'url' => route('doctor.citas'),
                ];
            })
        );

        $calendar = WeeklyCalendarData::build($weekStart, $calendarEntries, [
            'default_start_minutes' => 7 * 60,
            'default_end_minutes' => 20 * 60,
        ]);

        return view('doctor.agenda', compact('calendar', 'weekStart', 'weekEnd'));
    }
}
