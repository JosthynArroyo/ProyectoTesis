<?php

namespace App\Http\Controllers\Doctor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use App\Models\Cita;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function dashboard()
    {
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

        $citas = (clone $base)
            ->with(['paciente:id,name'])
            ->whereDate('fecha', '>=', $hoy)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->limit(10)
            ->get(['id','paciente_id','estado','fecha','hora']);

        return view('doctor.dashboard', compact(
            'user',
            'citas',
            'citasHoy',
            'citasRealizadas',
            'citasPendientes',
            'citasConfirmadas2h',
            'citasRealizadas2h',
            'citasCanceladas2h'
        ));
    }

    public function dashboardData(Request $request)
    {
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

        $citas = (clone $base)
            ->with(['paciente:id,name'])
            ->whereDate('fecha', '>=', $hoy)
            ->orderBy('fecha')
            ->orderBy('hora')
            ->limit(10)
            ->get(['id','paciente_id','estado','fecha','hora']);

        return response()->json([
            'kpis' => [
                'hoy'            => $citasHoy,
                'realizadas'     => $citasRealizadas,
                'pendientes'     => $citasPendientes,
                'confirmadas_2h' => $citasConfirmadas2h,
                'realizadas_2h'  => $citasRealizadas2h,
                'canceladas_2h'  => $citasCanceladas2h,
            ],
            'citas' => $citas->map(fn($c)=>[
                'paciente' => $c->paciente->name ?? 'Paciente',
                'estado'   => $c->estado,
                'fecha'    => $c->fecha,
                'hora'     => $c->hora,
            ]),
        ]);
    }

    public function citasIndex(Request $request)
    {
        $doctorId = Auth::id();

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

        if (!in_array($cita->estado, ['pendiente','confirmada'])) {
            return back()->with('error', 'Solo se pueden marcar como realizadas las citas pendientes o confirmadas.');
        }

        $cita->estado = 'realizada';
        $cita->save();

        return back()->with('success', 'Cita marcada como realizada.');
    }

    public function editarPerfil()
    {
        $user = Auth::user();
        return view('doctor.perfil', compact('user'));
    }

    public function actualizarPerfil(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'name'              => ['required','string','max:255'],
            'email'             => ['required','email','max:255', Rule::unique('users','email')->ignore($user->id)],
            'telefono'          => ['nullable','string','max:50'],
            'dni'               => ['required','digits:10', Rule::unique('users','dni')->ignore($user->id)],
            'direccion'         => ['nullable','string','max:255'],
            'fecha_nacimiento'  => ['required','date','before:today'],
            'sexo'              => ['nullable','in:Masculino,Femenino,Otro'],
            'avatar'            => ['nullable','image','mimes:jpg,jpeg,png,webp','max:2048'],
            'precio_consulta'   => ['nullable','numeric','min:0','max:99999999.99'],
            'moneda'            => ['nullable','in:USD'],
        ], [
            'name.required'                 => 'El nombre es obligatorio.',
            'email.required'                => 'El correo es obligatorio.',
            'email.email'                   => 'Formato de correo inválido.',
            'email.unique'                  => 'Este correo ya está registrado.',
            'dni.required'                  => 'El número de cédula es obligatorio.',
            'dni.digits'                    => 'El número de cédula debe tener exactamente 10 dígitos.',
            'dni.unique'                    => 'Este número de cédula ya está registrado.',
            'fecha_nacimiento.required'     => 'La fecha de nacimiento es obligatoria.',
            'fecha_nacimiento.before'       => 'La fecha de nacimiento debe ser anterior a hoy.',
            'avatar.image'                  => 'El archivo debe ser una imagen.',
            'avatar.mimes'                  => 'Formatos permitidos: JPG, JPEG, PNG o WEBP.',
            'avatar.max'                    => 'La imagen no debe exceder 2 MB.',
            'sexo.in'                       => 'Seleccione un sexo válido.',
            'precio_consulta.numeric'       => 'El precio debe ser numérico.',
            'precio_consulta.min'           => 'El precio no puede ser negativo.',
            'moneda.in'                     => 'Moneda inválida (fijo: USD).',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                Storage::disk('public')->delete($user->avatar);
            }
            $path = $request->file('avatar')->store('avatars', 'public');
            $user->avatar = $path;
        }

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
        ])->save();

        return redirect()->route('doctor.perfil.edit')->with('success', 'Perfil actualizado.');
    }

    public function agenda()
    {
        return view('doctor.agenda', ['current' => 'agenda']);
    }
}
