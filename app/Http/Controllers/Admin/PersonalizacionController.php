<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LandingWelcomeRequest;
use App\Http\Requests\PersonalizacionContactoRequest;
use App\Http\Requests\PersonalizacionServiciosRequest;
use App\Models\Especialidad;
use App\Services\LandingWelcomeManager;
use App\Services\LandingWelcomeService;
use App\Services\SiteSettingsService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class PersonalizacionController extends Controller
{
    public function edit(LandingWelcomeService $welcome)
    {
        $especialidadesActivas = \App\Models\Especialidad::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('admin.personalizacion.bienvenida', [
            'settings' => $welcome->all(),
            'stats' => $welcome->stats(),
            'slides' => $welcome->slides(),
            'cards' => $welcome->infoCards(),
            'doctors' => $welcome->doctors(),
            'prices' => $welcome->prices(),
            'featuredIds' => $welcome->featuredSpecialtyIds(),
            'especialidadesActivas' => $especialidadesActivas,
        ]);
    }

    public function update(LandingWelcomeRequest $request, LandingWelcomeManager $manager)
    {
        $manager->update($request);

        return back()->with('success', 'Bienvenida actualizada correctamente.');
    }

    public function serviciosEdit()
    {
        $especialidades = Especialidad::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('admin.personalizacion.servicios', compact('especialidades'));
    }

    public function serviciosUpdate(PersonalizacionServiciosRequest $request)
    {
        $data = $request->validated();

        foreach (($data['especialidades'] ?? []) as $id => $payload) {
            $especialidad = Especialidad::query()->find($id);
            if (! $especialidad) {
                continue;
            }

            $icono = $payload['icono'] ?? null;
            $icono = is_string($icono) ? trim($icono) : $icono;
            $icono = $icono === '' ? null : $icono;

            $especialidad->nombre = $payload['nombre'];
            $especialidad->descripcion = $payload['descripcion'] ?? null;
            $especialidad->icono = $icono;
            $especialidad->activo = ! empty($payload['activo']);
            $especialidad->orden = (int) ($payload['orden'] ?? 0);
            $especialidad->save();
        }

        foreach (($data['nuevas'] ?? []) as $payload) {
            $icono = $payload['icono'] ?? null;
            $icono = is_string($icono) ? trim($icono) : $icono;
            $icono = $icono === '' ? null : $icono;

            Especialidad::query()->create([
                'nombre' => $payload['nombre'],
                'descripcion' => $payload['descripcion'] ?? null,
                'icono' => $icono,
                'activo' => ! empty($payload['activo']),
                'orden' => (int) ($payload['orden'] ?? 0),
            ]);
        }

        return back()->with('success', 'Servicios actualizados correctamente.');
    }

    public function contactoEdit(SiteSettingsService $settings)
    {
        return view('admin.personalizacion.contacto', [
            'settings' => $settings->getMany($this->contactSettingKeys()),
        ]);
    }

    public function contactoUpdate(PersonalizacionContactoRequest $request, SiteSettingsService $settings)
    {
        $data = $request->validated();

        $payload = [
            'contact.info_badge' => $data['contact_info_badge'],
            'contact.title' => $data['contact_title'],
            'contact.subtitle' => $data['contact_subtitle'],
            'contact.address_label' => $data['contact_address_label'],
            'contact.address' => $data['contact_address'],
            'contact.phone_label' => $data['contact_phone_label'],
            'contact.phone' => $data['contact_phone'],
            'contact.hours_label' => $data['contact_hours_label'],
            'contact.hours' => $data['contact_hours'],
            'contact.map_title' => $data['contact_map_title'],
            'contact.map_embed' => $data['contact_map_embed'],
            'contact.form_section_badge' => $data['contact_form_section_badge'],
            'contact.form_title' => $data['contact_form_title'],
            'contact.form_badge' => $data['contact_form_badge'],
            'contact.form_submit_text' => $data['contact_form_submit_text'],
            'contact.form_name_label' => $data['contact_form_name_label'],
            'contact.form_email_label' => $data['contact_form_email_label'],
            'contact.form_phone_label' => $data['contact_form_phone_label'],
            'contact.form_subject_label' => $data['contact_form_subject_label'],
            'contact.form_subject_placeholder' => $data['contact_form_subject_placeholder'],
            'contact.form_message_label' => $data['contact_form_message_label'],
            'contact.form_message_placeholder' => $data['contact_form_message_placeholder'],
            'contact.form_message_help' => $data['contact_form_message_help'],
        ];

        $meta = [];
        foreach (array_keys($payload) as $key) {
            $meta[$key] = ['section' => 'contact', 'type' => 'text'];
        }

        $settings->setMany($payload, $meta);

        return back()->with('success', 'Seccion de contacto actualizada correctamente.');
    }

    public function requestAccess(Request $request): JsonResponse|RedirectResponse
    {
        $request->validate([
            'redirect_to' => ['nullable', 'string'],
        ]);

        $user = $request->user();
        $service = app(\App\Services\FeatureAccessService::class);
        $message = 'Solicitud enviada al superadmin.';
        $status = 'pending_created';

        if ($service->hasAccess($user, 'personalizacion')) {
            $message = 'Ya tienes acceso habilitado a Personalizacion.';
            $status = 'already_active';
        } elseif ($service->pendingRequest($user, 'personalizacion')) {
            $message = 'Tu solicitud ya fue enviada y esta pendiente de aprobacion.';
            $status = 'already_pending';
        } else {
            \App\Models\FeatureAccessRequest::create([
                'user_id' => $user->id,
                'feature' => 'personalizacion',
                'status' => 'pending',
            ]);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'status' => $status,
                'message' => $message,
            ]);
        }

        return redirect($this->resolveRequestAccessRedirect($request))
            ->with('success', $message);
    }

    private function resolveRequestAccessRedirect(Request $request): string
    {
        $redirectTo = trim((string) $request->input('redirect_to', ''));

        if ($redirectTo !== '') {
            if (str_starts_with($redirectTo, '/')) {
                return url($redirectTo);
            }

            $appBase = rtrim(url('/'), '/');
            if (str_starts_with($redirectTo, $appBase)) {
                return $redirectTo;
            }
        }

        return route('admin.dashboard');
    }

    private function contactSettingKeys(): array
    {
        return [
            'contact.info_badge',
            'contact.title',
            'contact.subtitle',
            'contact.address_label',
            'contact.address',
            'contact.phone_label',
            'contact.phone',
            'contact.hours_label',
            'contact.hours',
            'contact.map_title',
            'contact.map_embed',
            'contact.form_section_badge',
            'contact.form_title',
            'contact.form_badge',
            'contact.form_submit_text',
            'contact.form_name_label',
            'contact.form_email_label',
            'contact.form_phone_label',
            'contact.form_subject_label',
            'contact.form_subject_placeholder',
            'contact.form_message_label',
            'contact.form_message_placeholder',
            'contact.form_message_help',
        ];
    }

}
