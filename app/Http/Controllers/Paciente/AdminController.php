<?php

namespace App\Http\Controllers\Paciente;

use App\Http\Controllers\Controller;
use App\Models\Cita;
use App\Models\LaboratorioOrden;
use App\Models\LabOrder;
use App\Models\PedidoLaboratorio;
use App\Models\Pago;
use App\Services\CitaNoShowService;
use App\Services\ProfileAvatarService;
use App\Services\PagoService;
use App\Support\DateField;
use App\Support\ValidationRules;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        app(CitaNoShowService::class)->marcarVencidas();
        $user = Auth::user();

        $citasPorEstado = Cita::query()
            ->where('paciente_id', $user->id)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        $totalCitas = (int) $citasPorEstado->sum();
        $totalCitasPendientes = (int) ($citasPorEstado[Cita::ESTADO_PENDIENTE] ?? 0);
        $totalCitasRealizadas = (int) ($citasPorEstado[Cita::ESTADO_REALIZADA] ?? 0);
        $totalCitasCanceladas = (int) ($citasPorEstado[Cita::ESTADO_CANCELADA] ?? 0);

        $citas = Cita::query()
            ->with(['doctor:id,name', 'especialidad:id,nombre'])
            ->where('paciente_id', $user->id)
            ->orderBy('fecha', 'asc')
            ->orderBy('hora', 'asc')
            ->limit(4)
            ->get(['id', 'doctor_id', 'especialidad_id', 'fecha', 'hora', 'estado']);

        $pagosPorEstado = Pago::query()
            ->where('paciente_id', $user->id)
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');
        $totalPagos = (int) $pagosPorEstado->sum();
        $pagosPendientes = (int) (($pagosPorEstado[Pago::ESTADO_PENDIENTE] ?? 0) + ($pagosPorEstado[Pago::ESTADO_EN_VERIFICACION] ?? 0));
        $pagosPagados = (int) ($pagosPorEstado[Pago::ESTADO_PAGADO] ?? 0);
        $bloqueoPagosPendientes = app(\App\Services\LayoutMetricsService::class)->patientHasPaymentBlock($user);

        $desde2h = now()->subHours(2);
        $recentCitasGroup = Cita::query()
            ->where('paciente_id', $user->id)
            ->where(function ($q) use ($desde2h) {
                $q->where('created_at', '>=', $desde2h)
                    ->orWhere('updated_at', '>=', $desde2h);
            })
            ->get(['estado', 'created_at', 'updated_at']);

        $citasAgendadas2h = $recentCitasGroup->filter(fn ($c) => $c->created_at >= $desde2h)->count();
        $citasCompletadas2h = $recentCitasGroup->filter(fn ($c) => $c->estado === Cita::ESTADO_REALIZADA && $c->updated_at >= $desde2h)->count();
        $citasCanceladas2h = $recentCitasGroup->filter(fn ($c) => $c->estado === Cita::ESTADO_CANCELADA && $c->updated_at >= $desde2h)->count();

        $labResultados = LaboratorioOrden::with(['cita:id,paciente_id,fecha,hora'])
            ->whereHas('cita', function ($q) use ($user) {
                $q->where('paciente_id', $user->id);
            })
            ->where('estado', LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE)
            ->orderByDesc('resultado_publicado_at')
            ->limit(4)
            ->get(['id', 'cita_id', 'tipo_examen', 'estado', 'resultado_path', 'resultado_publicado_at']);

        $allPatientLabOrders = LaboratorioOrden::query()
            ->join('citas_medicas', 'laboratorio_ordenes.cita_id', '=', 'citas_medicas.id')
            ->where('citas_medicas.paciente_id', $user->id)
            ->with(['cita:id,paciente_id,fecha,hora'])
            ->select('laboratorio_ordenes.*')
            ->orderByDesc('laboratorio_ordenes.id')
            ->get();

        $labOrdenProgramada = $allPatientLabOrders
            ->filter(fn ($o) => in_array($o->estado, [LaboratorioOrden::ESTADO_ORDEN_CREADA, LaboratorioOrden::ESTADO_CITA_PROGRAMADA], true) && $o->cita?->fecha)
            ->sortBy(fn ($o) => optional($o->cita->fecha)->toDateString().' '.$o->cita->hora)
            ->first();

        $labOrdenEnCurso = $allPatientLabOrders->firstWhere('estado', LaboratorioOrden::ESTADO_MUESTRA_TOMADA);
        $labResultadoDestacado = $allPatientLabOrders->where('estado', LaboratorioOrden::ESTADO_RESULTADO_DISPONIBLE)->sortByDesc('resultado_publicado_at')->first();
        $labOrdenReciente = $allPatientLabOrders->first();

        $labOrdenPrincipal = $labOrdenProgramada
            ?? $labOrdenEnCurso
            ?? $labResultadoDestacado
            ?? $labOrdenReciente;

        $labOrdenes = collect([
            $labOrdenProgramada,
            $labOrdenEnCurso,
            $labResultadoDestacado,
            $labOrdenReciente,
        ])->filter()->unique('id')->values();

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

        $labOrders = LabOrder::with([
            'items:id,lab_order_id,lab_test_id',
            'items.test:id,nombre',
        ])
            ->where('patient_id', $user->id)
            ->orderByDesc('created_at')
            ->limit(3)
            ->get(['id', 'patient_id', 'source', 'status', 'created_at']);

        $pedidosLaboratorio = PedidoLaboratorio::with([
                'doctor',
                'resultados' => function ($query) {
                    $query->orderByDesc('version');
                },
                'resultados.laboratorio',
            ])
            ->where('paciente_id', $user->id)
            ->latest()
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
            'labOrders',
            'pedidosLaboratorio'
        ));
    }

    public function editarPerfil()
    {
        $user = Auth::user();

        return view('paciente.perfil', compact('user'));
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
            'avatar' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'current_password' => ['nullable', 'string'],
            'password' => array_merge(
                ValidationRules::passwordOptional(),
                ['different:current_password']
            ),
        ], [
            'password.regex' => 'La contraseña debe incluir letras, números y al menos un carácter especial.',
        ]);

        if ($request->hasFile('avatar')) {
            $user->avatar = $profileAvatars->replace($user, $request->file('avatar'), 'patients');
        }

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
        ]);

        $passwordChanged = false;
        if ($request->filled('password')) {
            if (! $request->filled('current_password') || ! Hash::check($request->input('current_password'), $user->password)) {
                return back()->withErrors(['current_password' => 'La contraseña actual no es correcta.'])->withInput();
            }
            $user->password = Hash::make($request->input('password'));
            $passwordChanged = true;
        }

        $user->save();

        if ($passwordChanged) {
            $user->setRememberToken(Str::random(60));
            $user->save();
            Auth::logoutOtherDevices($request->input('password'));
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            Auth::login($user);
            $request->session()->regenerate();
        }

        return redirect()->route('paciente.perfil.edit')->with('success', 'Perfil actualizado.');
    }
}
