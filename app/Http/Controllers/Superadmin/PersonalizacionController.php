<?php

namespace App\Http\Controllers\Superadmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LandingWelcomeRequest;
use App\Http\Requests\PersonalizacionContactoRequest;
use App\Http\Requests\PersonalizacionServiciosRequest;
use App\Models\Especialidad;
use App\Models\MediaProcessingBatch;
use App\Services\LandingWelcomeService;
use App\Services\SiteSettingsService;
use App\Services\ProfessionalScheduleService;
use App\Services\ServicesPersonalizationAsyncService;
use App\Services\WelcomePersonalizationAsyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

class PersonalizacionController extends Controller
{
    public function index(): RedirectResponse
    {
        return redirect()->route('superadmin.personalizacion.bienvenida.edit');
    }

    public function edit(LandingWelcomeService $welcome, SiteSettingsService $siteSettings)
    {
        $especialidadesActivas = \App\Models\Especialidad::query()
            ->where('activo', true)
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('superadmin.personalizacion.bienvenida', [
            'settings' => $welcome->all(),
            'slides' => $welcome->slides(),
            'doctors' => $welcome->doctors(),
            'prices' => $welcome->prices(),
            'featuredIds' => $welcome->featuredSpecialtyIds(),
            'especialidadesActivas' => $especialidadesActivas,
            'welcomeSiteSettings' => $siteSettings->getMany($this->welcomeSettingKeys()),
        ]);
    }

    public function update(
        LandingWelcomeRequest $request,
        WelcomePersonalizationAsyncService $asyncService
    ) {
        try {
            if (! $asyncService->hasNewImages($request)) {
                $asyncService->saveDirectly($request);

                $message = 'Personalización guardada correctamente.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'ok'         => true,
                        'batch_uuid' => null,
                        'total'      => 0,
                        'message'    => $message,
                    ], 200);
                }

                return back()->with('success', $message);
            }

            $batch = $asyncService->createAndDispatchBatch($request, $request->user());

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'         => true,
                    'batch_uuid' => $batch->uuid,
                    'total'      => $batch->total_items,
                    'message'    => 'Imágenes recibidas. Estamos procesando los cambios.',
                    'status_url' => route('superadmin.personalizacion.bienvenida.batch', ['uuid' => $batch->uuid]),
                ], 202);
            }

            return back()->with('success', 'Imágenes recibidas. Estamos procesando los cambios.');
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok'      => false,
                    'message' => $exception->getMessage() ?: 'No se pudieron guardar los cambios. Intenta nuevamente.',
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $exception->getMessage() ?: 'No se pudieron guardar los cambios. Intenta nuevamente.');
        }
    }

    public function welcomeBatchStatus(string $uuid, Request $request): JsonResponse
    {
        $batch = MediaProcessingBatch::query()->where('uuid', $uuid)->first();

        if (! $batch) {
            return response()->json(['message' => 'Lote no encontrado.'], 404);
        }

        if (! $batch->canBeAccessedBy($request->user())) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $waitingSeconds    = $batch->waiting_seconds;
        $elapsedSeconds    = $batch->elapsed_seconds;
        $processingSeconds = $batch->processing_seconds;

        $pendingTimeoutSeconds = 120;
        $workerAbsent = $batch->isPending() && $waitingSeconds >= $pendingTimeoutSeconds;

        $message = match (true) {
            $batch->isCompleted()  => 'Personalización guardada correctamente.',
            $batch->isFailed()     => $batch->error_message ?: 'No pudimos procesar todas las imágenes. Tus imágenes anteriores se conservaron. Intenta nuevamente.',
            $batch->isProcessing() => "Procesando imágenes {$batch->processed_items} de {$batch->total_items}…",
            $workerAbsent          => 'Las imágenes fueron recibidas, pero el procesamiento aún no ha comenzado. Verifica que el servicio de procesamiento esté disponible.',
            default                => 'Preparando imágenes…',
        };

        return response()->json([
            'status'             => $batch->status,
            'total'              => $batch->total_items,
            'processed'          => $batch->processed_items,
            'percentage'         => $batch->percentage,
            'message'            => $message,
            'elapsed_seconds'    => $elapsedSeconds,
            'waiting_seconds'    => $waitingSeconds,
            'processing_seconds' => $processingSeconds,
            'worker_absent'      => $workerAbsent,
        ]);
    }

    public function serviciosEdit(SiteSettingsService $settings)
    {
        $especialidades = Especialidad::query()
            ->orderBy('orden')
            ->orderBy('nombre')
            ->get();

        return view('superadmin.personalizacion.servicios', [
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
        ServicesPersonalizationAsyncService $asyncService
    ) {
        try {
            if (! $asyncService->hasNewImages($request)) {
                $asyncService->saveDirectly($request, $settings);

                $message = 'Personalización guardada correctamente.';

                if ($request->expectsJson()) {
                    return response()->json([
                        'ok'         => true,
                        'batch_uuid' => null,
                        'total'      => 0,
                        'message'    => $message,
                    ], 200);
                }

                return back()->with('success', $message);
            }

            $batch = $asyncService->createAndDispatchBatch($request, $request->user(), $settings);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => true,
                    'batch_uuid' => $batch->uuid,
                    'total' => $batch->total_items,
                    'message' => 'Imágenes recibidas. Estamos procesando los cambios.',
                    'status_url' => route('superadmin.personalizacion.servicios.batch', ['uuid' => $batch->uuid]),
                ], 202);
            }

            return back()
                ->with('success', 'Imágenes recibidas. Estamos procesando los cambios.');
        } catch (Throwable $exception) {
            report($exception);

            if ($request->expectsJson()) {
                return response()->json([
                    'ok' => false,
                    'message' => $exception->getMessage() ?: 'No se pudieron guardar los cambios de imagen. Intenta nuevamente.',
                ], 422);
            }

            return back()
                ->withInput()
                ->with('error', $exception->getMessage() ?: 'No se pudieron guardar los cambios de imagen. Intenta nuevamente.');
        }
    }

    public function serviciosBatchStatus(string $uuid, Request $request): JsonResponse
    {
        $batch = MediaProcessingBatch::query()->where('uuid', $uuid)->first();

        if (! $batch) {
            return response()->json(['message' => 'Lote no encontrado.'], 404);
        }

        if (! $batch->canBeAccessedBy($request->user())) {
            return response()->json(['message' => 'No autorizado.'], 403);
        }

        $elapsedSeconds    = $batch->elapsed_seconds;
        $waitingSeconds    = $batch->waiting_seconds;
        $processingSeconds = $batch->processing_seconds;

        $pendingTimeoutSeconds = 120;
        $workerAbsent = $batch->isPending() && $waitingSeconds >= $pendingTimeoutSeconds;

        $message = match (true) {
            $batch->isCompleted()  => 'Personalización guardada correctamente.',
            $batch->isFailed()     => $batch->error_message ?: 'No pudimos procesar todas las imágenes. Tus imágenes anteriores se conservaron. Intenta nuevamente.',
            $batch->isProcessing() => "Procesando imágenes {$batch->processed_items} de {$batch->total_items}…",
            $workerAbsent          => 'Las imágenes fueron recibidas, pero el procesamiento aún no ha comenzado. Verifica que el servicio de procesamiento esté disponible.',
            default                => 'Preparando imágenes…',
        };

        return response()->json([
            'status'             => $batch->status,
            'total'              => $batch->total_items,
            'processed'          => $batch->processed_items,
            'percentage'         => $batch->percentage,
            'message'            => $message,
            'elapsed_seconds'    => $elapsedSeconds,
            'waiting_seconds'    => $waitingSeconds,
            'processing_seconds' => $processingSeconds,
            'worker_absent'      => $workerAbsent,
        ]);
    }

    public function contactoEdit(SiteSettingsService $settings)
    {
        return view('superadmin.personalizacion.contacto', [
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
            $payload["clinic_hours.{$day}.closing"] = $hours['closing'] ?? '18:00';
        }

        $formattedSchedule = $scheduleService->getFormattedClinicSchedule($newHours);
        $payload['contact.hours'] = $formattedSchedule['summary'];

        DB::transaction(function () use ($settings, $payload) {
            $meta = [];
            foreach (array_keys($payload) as $key) {
                $meta[$key] = ['section' => 'contact', 'type' => 'text'];
            }
            $settings->setMany($payload, $meta);
        });

        $settings->forgetCache();

        // Audit log for clinic hours configuration changes
        \Illuminate\Support\Facades\Log::info("Auditoria: Horario de atencion de la clinica actualizado por superadmin ID {$request->user()->id}", [
            'user_id' => $request->user()->id,
            'clinic_hours' => $newHours,
            'conflicts' => $conflicts,
        ]);

        // Clear cache only after successful transaction
        $settings->forgetCache();

        return back()->with('success', 'Seccion de contacto actualizada correctamente.');
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
