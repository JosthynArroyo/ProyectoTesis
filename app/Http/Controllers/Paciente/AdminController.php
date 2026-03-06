<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Cita;
use App\Models\Pago;
use App\Models\LaboratorioOrden;
use App\Models\LabOrder;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use App\Services\CitaNoShowService;
use App\Services\PagoService;
use App\Services\ImageOptimizer;
use App\Support\ValidationRules;


class AdminController extends Controller
{
    public function dashboard()
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $user = Auth::user();

        $citas = Cita::where('paciente_id', $user->id)
            ->orderBy('fecha', 'asc')
            ->get();

        $totalCitas = $citas->count();
        $totalCitasPendientes = $citas->where('estado', 'pendiente')->count();
        $totalCitasRealizadas = $citas->where('estado', 'realizada')->count();
        $totalCitasCanceladas = $citas->where('estado', 'cancelada')->count();

        $pagosBase = Pago::query()->where('paciente_id', $user->id);
        $totalPagos = (clone $pagosBase)->count();
        $pagosPendientes = (clone $pagosBase)
            ->whereIn('estado', [Pago::ESTADO_PENDIENTE, Pago::ESTADO_EN_VERIFICACION])
            ->count();
        $pagosPagados = (clone $pagosBase)
            ->where('estado', Pago::ESTADO_PAGADO)
            ->count();
        $bloqueoPagosPendientes = app(PagoService::class)->pacienteTieneBloqueo($user->id);

        $citasAgendadas2h = $citas->where('created_at', '>=', now()->subHours(2))->count();
        $citasCompletadas2h = $citas->where('estado', 'realizada')->where('updated_at', '>=', now()->subHours(2))->count();
        $citasCanceladas2h = $citas->where('estado', 'cancelada')->where('updated_at', '>=', now()->subHours(2))->count();

        $labResultados = LaboratorioOrden::with(['cita.especialidad'])
            ->whereHas('cita', function ($q) use ($user) {
                $q->where('paciente_id', $user->id);
            })
            ->where('estado', LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE)
            ->orderByDesc('resultado_publicado_at')
            ->limit(4)
            ->get();

        $labOrdenes = LaboratorioOrden::with(['cita'])
            ->whereHas('cita', function ($q) use ($user) {
                $q->where('paciente_id', $user->id);
            })
            ->orderByDesc('id')
            ->get();

        $labOrdenProgramada = $labOrdenes
            ->filter(fn($orden) => in_array($orden->estado, [
                LaboratorioOrden::ESTADO_ORDEN_CREADA,
                LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
            ], true))
            ->filter(fn($orden) => $orden->cita && $orden->cita->fecha)
            ->sortBy(function ($orden) {
                $hora = $orden->cita->hora ?? '00:00';
                return Carbon::parse($orden->cita->fecha->format('Y-m-d').' '.$hora);
            })
            ->first();

        $labOrdenEnCurso = $labOrdenes->firstWhere('estado', LaboratorioOrden::ESTADO_MUESTRA_TOMADA);

        $labResultadoDestacado = $labOrdenes
            ->where('estado', LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE)
            ->sortByDesc(function ($orden) {
                return $orden->resultado_publicado_at ?? $orden->updated_at ?? $orden->id;
            })
            ->first();

        $labOrdenPrincipal = $labOrdenProgramada
            ?? $labOrdenEnCurso
            ?? $labResultadoDestacado
            ?? $labOrdenes->first();

        $labVentanaAtencion = null;
        if ($labOrdenProgramada && $labOrdenProgramada->cita && $labOrdenProgramada->cita->hora) {
            $inicio = Carbon::parse($labOrdenProgramada->cita->fecha->format('Y-m-d').' '.$labOrdenProgramada->cita->hora);
            $labVentanaAtencion = [
                'inicio' => $inicio->format('H:i'),
                'fin' => $inicio->copy()->addMinutes(30)->format('H:i'),
            ];
        }

        $labEsperaEstimada = null;
        if ($labOrdenProgramada && $labOrdenProgramada->cita && $labOrdenProgramada->cita->fecha && $labOrdenProgramada->cita->fecha->isToday()) {
            $fecha = $labOrdenProgramada->cita->fecha->toDateString();
            $hora = $labOrdenProgramada->cita->hora;

            $esperaQuery = LaboratorioOrden::whereHas('cita', function ($q) use ($fecha, $hora) {
                $q->whereDate('fecha', $fecha);
                if ($hora) {
                    $q->where('hora', '<', $hora);
                }
            })
                ->whereIn('estado', [
                    LaboratorioOrden::ESTADO_ORDEN_CREADA,
                    LaboratorioOrden::ESTADO_CITA_PROGRAMADA,
                ])
                ->where('id', '!=', $labOrdenProgramada->id);

            $labEsperaEstimada = $esperaQuery->count();
        }

        $labOrders = LabOrder::with(['items.test'])
            ->where('patient_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get();

        return view('paciente.dashboard', compact(
            'user',
            'citas',
            'totalCitas',
            'totalCitasPendientes',
            'totalCitasRealizadas',
            'totalCitasCanceladas',
            'totalPagos',
            'pagosPendientes',
            'pagosPagados',
            'bloqueoPagosPendientes',
            'citasAgendadas2h',
            'citasCompletadas2h',
            'citasCanceladas2h',
            'labResultados',
            'labOrdenes',
            'labOrdenProgramada',
            'labOrdenPrincipal',
            'labResultadoDestacado',
            'labVentanaAtencion',
            'labEsperaEstimada',
            'labOrders'
        ));
    }

    public function editarPerfil()
    {
        $user = Auth::user();
        return view('paciente.perfil', compact('user'));
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
            'current_password'  => ['nullable','string'],
            'password'          => array_merge(
                ValidationRules::passwordOptional(),
                ['different:current_password']
            ),
        ], [
            'password.regex' => 'La contraseña debe incluir letras, números y al menos un carácter especial.',
        ]);

        if ($request->hasFile('avatar')) {
            if ($user->avatar) {
                $imageOptimizer->deleteByStoredPath($user->avatar, 'patients');
            }
            $user->avatar = $imageOptimizer->optimizeAndStore($request->file('avatar'), 'patients');
        }

        $user->fill([
            'name'             => $request->name,
            'email'            => $request->email,
            'telefono'         => $request->telefono,
            'dni'              => $request->dni,
            'direccion'        => $request->direccion,
            'fecha_nacimiento' => $request->fecha_nacimiento,
            'sexo'             => $request->sexo,
        ]);

        $passwordChanged = false;
        if ($request->filled('password')) {
            if (!$request->filled('current_password') || !Hash::check($request->input('current_password'), $user->password)) {
                return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.'])->withInput();
            }
            $user->password = Hash::make($request->input('password'));
            $passwordChanged = true;
        }

        $user->save();

        if ($passwordChanged) {
            $user->setRememberToken(Str::random(60));
            $user->save();
            \Auth::logoutOtherDevices($request->input('password'));
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            \Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('paciente.perfil.edit')->with('success', 'Perfil actualizado.');
    }
}
