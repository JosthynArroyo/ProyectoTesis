<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LandingWelcomeRequest;
use App\Http\Requests\PersonalizacionContactoRequest;
use App\Http\Requests\PersonalizacionServiciosRequest;
use App\Models\Especialidad;
use App\Services\ImageOptimizer;
use App\Services\LandingWelcomeManager;
use App\Services\LandingWelcomeService;
use App\Services\SiteSettingsService;
use App\Services\ProfessionalScheduleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PersonalizacionController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('admin.personalizacion.bienvenida.edit');
    }

    public function edit(LandingWelcomeService $welcome, SiteSettingsService $siteSettings)
    {
        $especialidadesActivas = \App\Models\Especialidad::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('admin.personalizacion.bienvenida', [
            'settings' => $welcome->all(),
            'slides' => $welcome->slides(),
            'doctors' => $welcome->doctors(),
            'prices' => $welcome->prices(),
            'featuredIds' => $welcome->featuredSpecialtyIds(),
            'especialidadesActivas' => $especialidadesActivas,
            'welcomeSiteSettings' => $siteSettings->getMany($this->welcomeSettingKeys()),
        ]);
    }

    public function update(LandingWelcomeRequest $request, LandingWelcomeManager $manager)
    {
        $manager->update($request);

        return back()->with('success', 'Bienvenida actualizada correctamente.');
    }

    public function serviciosEdit(SiteSettingsService $settings)
    {
        $especialidades = Especialidad::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('admin.personalizacion.servicios', [
            'especialidades' => $especialidades,
            'serviceSettings' => $settings->getMany(array_merge(
                $this->serviceSettingKeys(),
                $this->serviceImageSettingKeys($especialidades->pluck('id')->all()),
            )),
        ]);
    }

    public function serviciosUpdate(
        PersonalizacionServiciosRequest $request,
        SiteSettingsService $settings,
        ImageOptimizer $imageOptimizer
    )
    {
        $data = $request->validated();
        $meta = [
            'services.title' => ['section' => 'services', 'type' => 'text'],
            'services.subtitle' => ['section' => 'services', 'type' => 'text'],
            'services.cta_text' => ['section' => 'services', 'type' => 'text'],
            'services.hero_image' => ['section' => 'services', 'type' => 'image'],
        ];

        $heroImagePath = $data['services_hero_image_path'] ?? $settings->get('services.hero_image');
        $heroImageFile = $request->file('services_hero_image');
        if ($heroImageFile) {
            $previousHeroImage = $heroImagePath;
            $heroImagePath = $imageOptimizer->optimizeAndStore($heroImageFile, 'services', baseName: 'services-hero');
            if ($previousHeroImage && $previousHeroImage !== $heroImagePath) {
                $imageOptimizer->deleteByStoredPath($previousHeroImage, 'services');
            }
        }

        $settingsPayload = [
            'services.title' => $data['services_title'] ?? null,
            'services.subtitle' => $data['services_subtitle'] ?? null,
            'services.cta_text' => $data['services_cta_text'] ?? null,
            'services.hero_image' => $heroImagePath,
        ];

        foreach (($data['especialidades'] ?? []) as $id => $payload) {
            $especialidad = Especialidad::query()->find($id);
            if (! $especialidad) {
                continue;
            }

            $icono = $payload['icono'] ?? null;
            $icono = is_string($icono) ? trim($icono) : $icono;
            $icono = $icono === '' ? null : $icono;

            $nombre = isset($payload['nombre']) ? trim((string) $payload['nombre']) : '';

            if ($nombre !== '') {
                $especialidad->nombre = $nombre;
            }

            $especialidad->descripcion = $payload['descripcion'] ?? null;
            $especialidad->icono = $icono;
            $especialidad->activo = array_key_exists('activo', $payload)
                ? ! empty($payload['activo'])
                : $especialidad->activo;
            $especialidad->orden = array_key_exists('orden', $payload) && $payload['orden'] !== null && $payload['orden'] !== ''
                ? (int) $payload['orden']
                : $especialidad->orden;
            $especialidad->save();

            $imageKey = $this->serviceImageKey((int) $especialidad->id);
            $imageMeta = ['section' => 'services', 'type' => 'image'];
            $currentImagePath = $payload['image_path'] ?? $settings->get($imageKey);
            $imageFile = $request->file("especialidades.$id.image");

            if ($imageFile) {
                $previousImagePath = $currentImagePath;
                $currentImagePath = $imageOptimizer->optimizeAndStore(
                    $imageFile,
                    'services',
                    baseName: 'service-'.$especialidad->id,
                );

                if ($previousImagePath && $previousImagePath !== $currentImagePath) {
                    $imageOptimizer->deleteByStoredPath($previousImagePath, 'services');
                }
            }

            $settingsPayload[$imageKey] = $currentImagePath;
            $meta[$imageKey] = $imageMeta;
        }

        foreach (($data['nuevas'] ?? []) as $newIndex => $payload) {
            $nombre = isset($payload['nombre']) ? trim((string) $payload['nombre']) : '';
            $descripcion = isset($payload['descripcion']) ? trim((string) $payload['descripcion']) : '';
            $icono = $payload['icono'] ?? null;
            $icono = is_string($icono) ? trim($icono) : $icono;
            $icono = $icono === '' ? null : $icono;

            if ($nombre === '' && $descripcion === '' && $icono === null) {
                continue;
            }

            if ($nombre === '') {
                continue;
            }

            $especialidad = Especialidad::query()->create([
                'nombre' => $nombre,
                'descripcion' => $descripcion !== '' ? $descripcion : null,
                'icono' => $icono,
                'activo' => ! empty($payload['activo']),
                'orden' => (int) ($payload['orden'] ?? 0),
            ]);

            $imageKey = $this->serviceImageKey((int) $especialidad->id);
            $currentImagePath = $payload['image_path'] ?? null;
            $imageFile = $request->file("nuevas.$newIndex.image");

            if ($imageFile) {
                $currentImagePath = $imageOptimizer->optimizeAndStore(
                    $imageFile,
                    'services',
                    baseName: 'service-'.$especialidad->id,
                );
            }

            $settingsPayload[$imageKey] = $currentImagePath;
            $meta[$imageKey] = ['section' => 'services', 'type' => 'image'];
        }

        $settings->setMany($settingsPayload, $meta);

        return back()->with('success', 'Servicios actualizados correctamente.');
    }

    public function contactoEdit(SiteSettingsService $settings)
    {
        return view('admin.personalizacion.contacto', [
            'settings' => $settings->getMany($this->contactSettingKeys()),
        ]);
    }

    public function contactoUpdate(
        PersonalizacionContactoRequest $request,
        SiteSettingsService $settings,
        ProfessionalScheduleService $scheduleService
    ) {
        $data = $request->validated();

        $newHours = $data['clinic_hours'] ?? [];
        $conflicts = $scheduleService->detectConflictsForClinicHoursChange($newHours);

        $hasConflicts = ($conflicts['schedules_count'] > 0 || $conflicts['citas_count'] > 0);
        if ($hasConflicts && !$request->boolean('confirmar_conflictos')) {
            return back()->withInput()->with('clinic_hours_conflicts', $conflicts);
        }

        $payload = [
            'contact.info_badge' => $data['contact_info_badge'] ?? null,
            'contact.title' => $data['contact_title'] ?? null,
            'contact.subtitle' => $data['contact_subtitle'] ?? null,
            'contact.address_label' => $data['contact_address_label'] ?? null,
            'contact.address' => $data['contact_address'] ?? null,
            'contact.phone_label' => $data['contact_phone_label'] ?? null,
            'contact.phone' => $data['contact_phone'] ?? null,
            'contact.email' => $data['contact_email'] ?? null,
            'contact.hours_label' => $data['contact_hours_label'] ?? null,
            'contact.hours' => $data['contact_hours'] ?? null,
            'contact.map_title' => $data['contact_map_title'] ?? null,
            'contact.map_embed' => $data['contact_map_embed'] ?? null,
            'contact.form_section_badge' => $data['contact_form_section_badge'] ?? null,
            'contact.form_title' => $data['contact_form_title'] ?? null,
            'contact.form_badge' => $data['contact_form_badge'] ?? null,
            'contact.form_submit_text' => $data['contact_form_submit_text'] ?? null,
            'contact.form_name_label' => $data['contact_form_name_label'] ?? null,
            'contact.form_name_placeholder' => $data['contact_form_name_placeholder'] ?? null,
            'contact.form_email_label' => $data['contact_form_email_label'] ?? null,
            'contact.form_email_placeholder' => $data['contact_form_email_placeholder'] ?? null,
            'contact.form_phone_label' => $data['contact_form_phone_label'] ?? null,
            'contact.form_phone_placeholder' => $data['contact_form_phone_placeholder'] ?? null,
            'contact.form_subject_label' => $data['contact_form_subject_label'] ?? null,
            'contact.form_subject_placeholder' => $data['contact_form_subject_placeholder'] ?? null,
            'contact.form_message_label' => $data['contact_form_message_label'] ?? null,
            'contact.form_message_placeholder' => $data['contact_form_message_placeholder'] ?? null,
            'contact.form_message_help' => $data['contact_form_message_help'] ?? null,
        ];

        foreach ($newHours as $day => $hours) {
            $payload["clinic_hours.{$day}.status"] = $hours['status'];
            $payload["clinic_hours.{$day}.opening"] = $hours['opening'] ?? '08:00';
            $payload["clinic_hours.{$day}.closing"] = $hours['closing'] ?? ($day == 6 ? '13:00' : '18:00');
        }

        DB::transaction(function () use ($settings, $payload) {
            $meta = [];
            foreach (array_keys($payload) as $key) {
                $meta[$key] = ['section' => 'contact', 'type' => 'text'];
            }
            $settings->setMany($payload, $meta);
        });

        // Clear cache only after successful transaction
        $settings->forgetCache();

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
        $keys = [
            'contact.info_badge',
            'contact.title',
            'contact.subtitle',
            'contact.address_label',
            'contact.address',
            'contact.phone_label',
            'contact.phone',
            'contact.email',
            'contact.hours_label',
            'contact.hours',
            'contact.map_title',
            'contact.map_embed',
            'contact.form_section_badge',
            'contact.form_title',
            'contact.form_badge',
            'contact.form_submit_text',
            'contact.form_name_label',
            'contact.form_name_placeholder',
            'contact.form_email_label',
            'contact.form_email_placeholder',
            'contact.form_phone_label',
            'contact.form_phone_placeholder',
            'contact.form_subject_label',
            'contact.form_subject_placeholder',
            'contact.form_message_label',
            'contact.form_message_placeholder',
            'contact.form_message_help',
        ];

        for ($i = 1; $i <= 7; $i++) {
            $keys[] = "clinic_hours.{$i}.status";
            $keys[] = "clinic_hours.{$i}.opening";
            $keys[] = "clinic_hours.{$i}.closing";
        }

        return $keys;
    }

    private function serviceSettingKeys(): array
    {
        return [
            'services.title',
            'services.subtitle',
            'services.cta_text',
            'services.hero_image',
        ];
    }

    private function serviceImageKey(int $id): string
    {
        return "services.specialty_image.$id";
    }

    private function serviceImageSettingKeys(array $ids): array
    {
        return array_map(fn ($id) => $this->serviceImageKey((int) $id), $ids);
    }

    private function welcomeSettingKeys(): array
    {
        return array_values(array_unique(array_merge($this->contactSettingKeys(), $this->serviceSettingKeys(), [
            'branding.name',
            'branding.logo',
            'branding.favicon',
            'branding.navbar_text',
            'branding.institutional_name',
            'branding.institutional_badge',
            'branding.accent',
            'branding.accent_strong',
            'branding.accent_soft',
            'branding.footer_text',
            'header.show_home',
            'header.show_services',
            'header.show_contact',
            'header.navigation_order',
            'header.sticky_enabled',
            'footer.institutional_text',
            'footer.show_home_link',
            'footer.show_services_link',
            'footer.show_contact_link',
            'footer.show_assistant_link',
            'footer.show_privacy_link',
            'footer.show_terms_link',
            'footer.home_label',
            'footer.services_label',
            'footer.contact_label',
            'footer.assistant_label',
            'footer.privacy_label',
            'footer.terms_label',
            'legal.privacy_title',
            'legal.privacy_updated_at',
            'legal.privacy_body',
            'legal.terms_title',
            'legal.terms_updated_at',
            'legal.terms_body',
            'visual.soft_primary',
            'visual.soft_secondary',
            'visual.gradient_start',
            'visual.gradient_end',
            'visual.badge_soft',
        ])));
    }
}
