<?php

namespace App\Http\Controllers;

use App\Models\Cita;
use App\Models\CitaEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class CitaPrioridadController extends Controller
{
    public function editAdmin(Request $request, Cita $cita)
    {
        return $this->renderForm($request, $cita, 'admin');
    }

    public function editDoctor(Request $request, Cita $cita)
    {
        $this->assertDoctorOwnership($cita);

        return $this->renderForm($request, $cita, 'doctor');
    }

    public function updateAdmin(Request $request, Cita $cita): RedirectResponse
    {
        return $this->updatePriority($request, $cita, 'admin');
    }

    public function updateDoctor(Request $request, Cita $cita): RedirectResponse
    {
        $this->assertDoctorOwnership($cita);

        return $this->updatePriority($request, $cita, 'doctor');
    }

    private function renderForm(Request $request, Cita $cita, string $context)
    {
        $layout = $context === 'admin' ? 'layouts.admin' : 'layouts.doctor';
        $submitRoute = $context === 'admin'
            ? route('admin.citas.prioridad.update', $cita)
            : route('doctor.citas.prioridad.update', $cita);
        $cancelRoute = $context === 'admin'
            ? route('admin.dashboard')
            : route('doctor.citas');

        return view('citas.prioridad-edit', [
            'layout' => $layout,
            'context' => $context,
            'cita' => $cita->load(['paciente:id,name', 'doctor:id,name']),
            'submitRoute' => $submitRoute,
            'cancelRoute' => $cancelRoute,
            'redirectTo' => $request->query('redirect_to', ''),
            'niveles' => Cita::PRIORIDAD_NIVELES,
        ]);
    }

    private function updatePriority(Request $request, Cita $cita, string $context): RedirectResponse
    {
        $data = $request->validate([
            'prioridad_nivel' => ['required', Rule::in(Cita::PRIORIDAD_NIVELES)],
            'ignorar_red_flag' => ['nullable', 'boolean'],
            'prioridad_comentario' => ['nullable', 'string', 'max:500'],
            'redirect_to' => ['nullable', 'string', 'max:2000'],
        ], [
            'prioridad_nivel.required' => 'Seleccione un nivel de prioridad.',
            'prioridad_nivel.in' => 'El nivel de prioridad seleccionado no es valido.',
        ]);

        $nivelAnterior = $cita->prioridad_nivel ?: Cita::PRIORIDAD_BAJA;
        $redFlagAnterior = (bool) $cita->prioridad_red_flag;
        $redFlagTipoAnterior = $cita->prioridad_red_flag_tipo;

        $nivelNuevo = (string) $data['prioridad_nivel'];
        $ignorarRedFlag = $redFlagAnterior && $request->boolean('ignorar_red_flag');
        $comentario = trim((string) ($data['prioridad_comentario'] ?? ''));

        $bajaPrioridad = $this->priorityRank($nivelNuevo) < $this->priorityRank($nivelAnterior);
        if (($bajaPrioridad || $ignorarRedFlag) && $comentario === '') {
            return back()
                ->withErrors([
                    'prioridad_comentario' => 'Debe registrar un comentario al bajar prioridad o ignorar un red flag.',
                ])
                ->withInput();
        }

        $valorAnterior = $this->buildAuditValue(
            $nivelAnterior,
            (string) $cita->prioridad_fuente,
            $redFlagAnterior,
            $redFlagTipoAnterior
        );

        $cita->prioridad_nivel = $nivelNuevo;
        $cita->prioridad_fuente = Cita::FUENTE_PRIORIDAD_MANUAL;
        $cita->prioridad_comentario = $comentario !== '' ? $comentario : null;

        if ($ignorarRedFlag) {
            $cita->prioridad_red_flag = false;
            $cita->prioridad_red_flag_tipo = null;
        }

        $cita->save();

        $valorNuevo = $this->buildAuditValue(
            (string) $cita->prioridad_nivel,
            (string) $cita->prioridad_fuente,
            (bool) $cita->prioridad_red_flag,
            $cita->prioridad_red_flag_tipo
        );

        CitaEvento::create([
            'cita_id' => $cita->id,
            'user_id' => Auth::id(),
            'tipo' => 'prioridad_manual',
            'de_estado' => $nivelAnterior,
            'a_estado' => $nivelNuevo,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo' => $valorNuevo,
            'comentario' => $comentario !== '' ? $comentario : null,
        ]);

        $redirect = $this->resolveRedirect($context, $request->input('redirect_to'));

        return redirect($redirect)->with('success', 'Prioridad actualizada correctamente.');
    }

    private function resolveRedirect(string $context, ?string $redirectTo): string
    {
        $fallback = $context === 'admin'
            ? route('admin.dashboard')
            : route('doctor.citas');

        if (blank($redirectTo)) {
            return $fallback;
        }

        if (str_starts_with($redirectTo, '/')) {
            return $redirectTo;
        }

        return $fallback;
    }

    private function assertDoctorOwnership(Cita $cita): void
    {
        if ((int) $cita->doctor_id !== (int) Auth::id()) {
            abort(403);
        }
    }

    private function priorityRank(string $nivel): int
    {
        return match (strtoupper($nivel)) {
            Cita::PRIORIDAD_ALTA => 3,
            Cita::PRIORIDAD_MEDIA => 2,
            default => 1,
        };
    }

    private function buildAuditValue(string $nivel, string $fuente, bool $redFlag, ?string $redFlagType): string
    {
        $parts = [
            'NIVEL:'.strtoupper($nivel),
            'FUENTE:'.strtoupper($fuente),
        ];

        if ($redFlag) {
            $parts[] = 'RED_FLAG:'.($redFlagType ?: 'SI');
        } else {
            $parts[] = 'RED_FLAG:NO';
        }

        return implode(' | ', $parts);
    }
}
