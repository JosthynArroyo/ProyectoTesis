<?php

namespace App\Http\Controllers;

use App\Models\FaceProfile;
use App\Services\FaceRecognitionService;
use App\Services\MaintenanceAccessService;
use App\Services\SiteSettingsService;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;

class FaceAuthController extends Controller
{
    private const MAINTENANCE_FACE_MESSAGE = 'El sistema está en mantenimiento. Intenta nuevamente más tarde.';

    public function __construct()
    {
        $this->middleware('auth')->only(['showEnrollment', 'storeEnrollment']);
        $this->middleware('guest')->only(['verifyLogin']);
    }

    public function showEnrollment(Request $request)
    {
        $this->abortIfMaintenanceActive($request);

        return view('auth.face-enroll');
    }

    public function storeEnrollment(Request $request)
    {
        $this->abortIfMaintenanceActive($request);

        $validator = Validator::make($request->all(), [
            'descriptors' => 'sometimes|array|min:1',
            'descriptors.*' => 'array|min:128',
            'descriptor' => 'sometimes|array|min:128',
        ]);

        if ($validator->fails()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No se pudo completar la captura.',
                ], 422);
            }

            return redirect($this->redirectUrlFor($request->user(), 'error'));
        }

        $payload = $validator->validated();
        $descriptors = $payload['descriptors'] ?? [];

        if (! $descriptors && isset($payload['descriptor'])) {
            $descriptors = [$payload['descriptor']];
        }

        if (! $descriptors) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'No se pudo completar la captura.',
                ], 422);
            }

            return redirect($this->redirectUrlFor($request->user(), 'error'));
        }

        $profile = $request->user()->faceProfile;
        if ($profile && $profile->last_enrolled_at && $profile->last_enrolled_at->gt(now()->subSeconds(10))) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => 'Intento reciente. Intenta mas tarde.',
                ], 429);
            }

            return redirect($this->redirectUrlFor($request->user(), 'error'));
        }

        $normalized = [];
        foreach (array_slice($descriptors, 0, 6) as $descriptor) {
            if (! is_array($descriptor)) {
                continue;
            }
            $normalized[] = array_map('floatval', $descriptor);
        }

        $average = $this->averageDescriptor($normalized);
        if (! $average && $normalized) {
            $average = $normalized[0];
        }

        $payloadToSave = [
            'descriptor' => $average,
            'threshold' => config('services.face.threshold', 0.55),
        ];

        if (Schema::hasColumn('face_profiles', 'descriptors')) {
            $payloadToSave['descriptors'] = $normalized;
        }
        if (Schema::hasColumn('face_profiles', 'last_enrolled_at')) {
            $payloadToSave['last_enrolled_at'] = now();
        }
        if (Schema::hasColumn('face_profiles', 'last_enroll_ip')) {
            $payloadToSave['last_enroll_ip'] = $request->ip();
        }
        if (Schema::hasColumn('face_profiles', 'last_enroll_user_agent')) {
            $payloadToSave['last_enroll_user_agent'] = substr((string) $request->userAgent(), 0, 255);
        }

        $request->user()->faceProfile()->updateOrCreate([], $payloadToSave);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Rostro registrado.',
            ]);
        }

        return redirect($this->redirectUrlFor($request->user(), 'ok'));
    }

    public function verifyLogin(Request $request, FaceRecognitionService $recognizer)
    {
        $this->abortIfMaintenanceActive($request);

        $validator = Validator::make($request->all(), [
            'descriptor' => 'required|array|min:128',
        ]);

        if ($validator->fails()) {
            throw new HttpResponseException(response()->json([
                'message' => 'No se pudo leer un rostro válido. Intenta nuevamente.',
                'code' => 'invalid_face_descriptor',
                'state' => 'not_recognized',
                'errors' => $validator->errors(),
            ], 422));
        }

        $data = $validator->validated();

        $profiles = FaceProfile::with('user')
            ->get()
            ->filter(fn (FaceProfile $profile) => $this->profileCanLogin($profile))
            ->values();
        if ($profiles->isEmpty()) {
            throw $this->faceLoginException(
                'face_not_registered',
                'not_registered',
                'No hay ningún rostro registrado para usar este método. Inicia sesión con correo y contraseña y registra tu rostro desde tu perfil.'
            );
        }

        $bestMatch = null;

        foreach ($profiles as $profile) {
            $distance = $recognizer->compare(array_map('floatval', $data['descriptor']), $profile);

            if (! $recognizer->isMatch($distance, $profile)) {
                continue;
            }

            if (! $bestMatch || $distance < $bestMatch['distance']) {
                $bestMatch = ['profile' => $profile, 'distance' => $distance];
            }
        }

        if (! $bestMatch) {
            throw $this->faceLoginException(
                'face_not_recognized',
                'not_recognized',
                'No encontramos coincidencia con un rostro registrado. Intenta nuevamente o ingresa con correo y contraseña.'
            );
        }

        $bestMatch['profile']->update([
            'failed_attempts' => 0,
            'last_verified_at' => now(),
        ]);

        $user = $bestMatch['profile']->user;

        Auth::login($user, true);

        return response()->json([
            'code' => 'face_recognized',
            'state' => 'recognized',
            'message' => 'Rostro reconocido.',
            'redirect' => route('home'),
            'name' => $user->name,
        ]);
    }

    private function profileCanLogin(FaceProfile $profile): bool
    {
        if (! $profile->user || ! $profile->user->isActive()) {
            return false;
        }

        $hasDescriptor = is_array($profile->descriptor) && count($profile->descriptor) > 0;
        $hasSamples = is_array($profile->descriptors) && count($profile->descriptors) > 0;

        return $hasDescriptor || $hasSamples;
    }

    private function faceLoginException(string $code, string $state, string $message): HttpResponseException
    {
        return new HttpResponseException(response()->json([
            'message' => $message,
            'code' => $code,
            'state' => $state,
            'errors' => [
                'descriptor' => [$message],
            ],
        ], 422));
    }

    private function averageDescriptor(array $samples): array
    {
        if (! $samples) {
            return [];
        }

        $length = count($samples[0] ?? []);
        if ($length === 0) {
            return [];
        }

        $sum = array_fill(0, $length, 0.0);
        $count = 0;

        foreach ($samples as $sample) {
            if (! is_array($sample) || count($sample) !== $length) {
                continue;
            }
            for ($i = 0; $i < $length; $i++) {
                $sum[$i] += $sample[$i] ?? 0.0;
            }
            $count += 1;
        }

        if ($count === 0) {
            return [];
        }

        return array_map(static fn ($value) => $value / $count, $sum);
    }

    private function redirectUrlFor($user, string $result): string
    {
        $base = route('home');
        if ($user->hasRole('administrador')) {
            $base = route('admin.perfil.edit');
        } elseif ($user->hasRole('doctor')) {
            $base = route('doctor.perfil.edit');
        } elseif ($user->hasRole('paciente')) {
            $base = route('paciente.perfil.edit');
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.'face='.$result.'#perfil-face';
    }

    private function abortIfMaintenanceActive(Request $request): void
    {
        $settings = app(SiteSettingsService::class);

        if (! $settings->getBool('maintenance.enabled', false)) {
            return;
        }

        if (app(MaintenanceAccessService::class)->userOrIpCanBypass($request, $request->user())) {
            return;
        }

        $message = self::MAINTENANCE_FACE_MESSAGE;

        if ($request->expectsJson()) {
            throw new HttpResponseException(response()->json([
                'message' => $message,
            ], 503));
        }

        throw new ServiceUnavailableHttpException(null, $message);
    }
}
