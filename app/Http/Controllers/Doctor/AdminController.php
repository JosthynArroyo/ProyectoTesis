<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Cita;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Support\ValidationRules;
use App\Services\CitaNoShowService;
use App\Services\ImageOptimizer;
use App\Events\CitaAtendida;

class AdminController extends Controller
{
    public function dashboard()
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $user = Auth::user();
        $tz = 'America/Guayaquil';
        $hoy = Carbon::now($tz)->toDateString();
        $desde2h = Carbon::now($z = $tz)->subHours(2);

        $base = Cita::query()->where('doctor_id', $user->id);

        $citasHoy        = (clone $base)->whereDate('fecha', $hoy)->count();
        $citasRealizadas = (clone $base)->whereDate('fecha', $hoy)->where('estado','realizada')->count();
        $citasPendientes = (clone $base)->whereDate('fecha', $hoy)->where('estado','pendiente')->count();

        $citasConfirmadas2h = (clone $base)->where('estado','confirmada')->where('updated_at','>=',$desde2h)->count();
        $citasRealizadas2h  = (clone $base)->where('estado','realizada')->where('updated_at','>=',$desde2h)->count();
        $citasCanceladas2h  = (clone $base)->where('estado','cancelada')->where('updated_at','>=',$desde2h)->count();
        $totalPacientes     = (clone $base)->distinct('paciente_id')->count('paciente_id');

        $citas = (clone $base)
            ->with(['paciente:id,name'])
            ->whereDate('fecha', $hoy)
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('hora')
            ->limit(10)
            ->get(['id','paciente_id','estado','fecha','hora','prioridad_nivel','prioridad_red_flag']);

        return view('doctor.dashboard', compact(
            'user',
            'citas',
            'citasHoy',
            'citasRealizadas',
            'citasPendientes',
            'citasConfirmadas2h',
            'citasRealizadas2h',
            'citasCanceladas2h',
            'totalPacientes'
        ));
    }

    public function dashboardData(Request $request)
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $user = $request->user();
        $tz = 'America/Guayaquil';
        $hoy = Carbon::now($tz)->toDateString();
        $desde2h = Carbon::now($tz)->subHours(2);

        $base = Cita::query()->where('doctor_id', $user->id);

        $citasHoy        = (clone $base)->whereDate('fecha', $hoy)->count();
        $citasRealizadas = (clone $base)->whereDate('fecha', $hoy)->where('estado','realizada')->count();
        $citasPendientes = (clone $base)->whereDate('fecha', $hoy)->where('estado','pendiente')->count();

        $citasConfirmadas2h = (clone $base)->where('estado','confirmada')->where('updated_at','>=',$desde2h)->count();
        $citasRealizadas2h  = (clone $base)->where('estado','realizada')->where('updated_at','>=',$desde2h)->count();
        $citasCanceladas2h  = (clone $base)->where('estado','cancelada')->where('updated_at','>=',$desde2h)->count();
        $totalPacientes     = (clone $base)->distinct('paciente_id')->count('paciente_id');

        $citas = (clone $base)
            ->with(['paciente:id,name'])
            ->whereDate('fecha', $hoy)
            ->orderByRaw(Cita::prioridadOrderSql())
            ->orderBy('hora')
            ->limit(10)
            ->get(['id','paciente_id','estado','fecha','hora','prioridad_nivel','prioridad_red_flag']);

        return response()->json([
            'kpis' => [
                'hoy'            => $citasHoy,
                'realizadas'     => $citasRealizadas,
                'pendientes'     => $citasPendientes,
                'confirmadas_2h' => $citasConfirmadas2h,
                'realizadas_2h'  => $citasRealizadas2h,
                'canceladas_2h'  => $citasCanceladas2h,
                'pacientes'      => $totalPacientes,
            ],
            'citas' => $citas->map(fn($c)=>[
                'paciente' => $c->paciente?->name ?? 'Paciente',
                'estado'   => $c->estado,
                'fecha'    => $c->fecha,
                'hora'     => $c->hora,
                'prioridad' => $c->prioridad_nivel,
                'red_flag' => (bool) $c->prioridad_red_flag,
            ]),
        ]);
    }

    public function citasIndex(Request $request)
    {
        $doctorId = Auth::id();

        app(CitaNoShowService::class)->marcarVencidas();
        $citas = Cita::with(['paciente:id,name', 'especialidad:id,nombre'])
            ->where('doctor_id', $doctorId)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->get();

        return view('doctor.citas', compact('citas'));
    }

    private function findOwnedCitaOrFail(int $id): Cita
    {
        return Cita::where('id', $id)
            ->where('doctor_id', Auth::id())
            ->firstOrFail();
    }

    public function aceptar(int $id)
    {
        $cita = $this->findOwnedCitaOrFail($id);

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
        }

        if ($cita->estado !== 'pendiente') {
            return back()->with('error', 'Solo se pueden aceptar citas en estado pendiente.');
        }

        $cita->estado = 'confirmada';
        $cita->save();

        return back()->with('success', 'Cita aceptada correctamente.');
    }

    public function rechazar(int $id)
    {
        $cita = $this->findOwnedCitaOrFail($id);

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
        }

        if ($cita->estado !== 'pendiente') {
            return back()->with('error', 'Solo se pueden rechazar citas en estado pendiente.');
        }

        $cita->estado = 'cancelada';
        $cita->save();

        return back()->with('success', 'Cita rechazada.');
    }

    public function realizada(int $id)
    {
        $cita = $this->findOwnedCitaOrFail($id);

        if (app(CitaNoShowService::class)->marcarSiVencio($cita)) {
            return back()->with('error', 'La cita ya vencio y se marco como no se presento.');
        }

        if (!in_array($cita->estado, ['pendiente','confirmada'])) {
            return back()->with('error', 'Solo se pueden marcar como realizadas las citas pendientes o confirmadas.');
        }

        $cita->estado = 'realizada';
        $cita->save();
        event(new CitaAtendida($cita));

        return back()->with('success', 'Cita marcada como realizada.');
    }

    public function editarPerfil()
    {
        $user = Auth::user();
        return view('doctor.perfil', compact('user'));
    }

    public function actualizarPerfil(Request $request, ImageOptimizer $imageOptimizer)
    {
        $user = Auth::user();

        $request->validate([
            'name'              => ['required','string','max:255'],
            'email'             => ValidationRules::emailUnique('users', $user->id),
            'telefono'          => ValidationRules::telefono(),
            'dni'               => ValidationRules::cedulaUnique('users', $user->id),
            'direccion'         => ['required','string','max:255'],
            'fecha_nacimiento'  => ['required','date','before:today'],
            'sexo'              => ['required','in:Masculino,Femenino,Otro'],
            'avatar'            => ['nullable','image','mimes:jpg,jpeg,png,webp,svg','max:2048'],
            'precio_consulta'   => ['required','numeric','min:0','max:99999999.99'],
            'moneda'            => ['required','in:USD'],

            // cambio de contraseña (opcional)
            'current_password'  => ['nullable','string'],
            'password'          => array_merge(
                ValidationRules::passwordOptional(),
                ['different:current_password']
            ),
        ], [
            'password.regex' => 'La contraseña debe incluir letras, números y al menos un carácter especial.',
        ]);

        // avatar
        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                $imageOptimizer->deleteByStoredPath($user->avatar, 'doctors');
            }
            $user->avatar = $imageOptimizer->optimizeAndStore($request->file('avatar'), 'doctors');
        }

        // datos perfil
        $user->fill([
            'name'             => $request->name,
            'email'            => $request->email,
            'telefono'         => $request->telefono,
            'dni'              => $request->dni,
            'direccion'        => $request->direccion,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'sexo'             => $request->sexo,
            'precio_consulta'  => $request->precio_consulta,
            'moneda'           => 'USD',
        ]);

        // cambio de contraseña si viene nueva
        $passwordChanged = false;
        if ($request->filled('password')) {
            if (!$request->filled('current_password') || !Hash::check($request->input('current_password'), $user->password)) {
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

    public function agenda()
    {
        // Solo retorna la vista de agenda semanal del doctor; los datos se cargan vía JS.
        return view('doctor.agenda');
    }
}
