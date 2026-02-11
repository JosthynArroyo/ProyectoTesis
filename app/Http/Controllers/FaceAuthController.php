<?php

namespace App\Http\Controllers;

use App\Models\FaceProfile;
use App\Services\FaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class FaceAuthController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth')->only(['showEnrollment', 'storeEnrollment']);
        $this->middleware('guest')->only(['verifyLogin']);
    }

    public function showEnrollment()
    {
        return view('auth.face-enroll');
    }

    public function storeEnrollment(Request $request)
    {
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

        if (!$descriptors && isset($payload['descriptor'])) {
            $descriptors = [$payload['descriptor']];
        }

        if (!$descriptors) {
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
            if (!is_array($descriptor)) {
                continue;
            }
            $normalized[] = array_map('floatval', $descriptor);
        }

        $average = $this->averageDescriptor($normalized);
        if (!$average && $normalized) {
            $average = $normalized[0];
        }

        $payloadToSave = [
            'descriptor' => $average,
            'threshold'  => config('services.face.threshold', 0.55),
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
        $data = $request->validate([
            'descriptor' => 'required|array|min:128',
        ]);

        $profiles = FaceProfile::with('user')->get();
        if ($profiles->isEmpty()) {
            throw ValidationException::withMessages([
                'descriptor' => 'No hay perfiles faciales registrados.',
            ]);
        }

        $bestMatch = null;

        foreach ($profiles as $profile) {
            if (!$profile->user || !$profile->user->isActive()) {
                continue;
            }

            $distance = $recognizer->compare(array_map('floatval', $data['descriptor']), $profile);

            if (!$recognizer->isMatch($distance, $profile)) {
                continue;
            }

            if (!$bestMatch || $distance < $bestMatch['distance']) {
                $bestMatch = ['profile' => $profile, 'distance' => $distance];
            }
        }

        if (!$bestMatch) {
            throw ValidationException::withMessages([
                'descriptor' => 'No se reconoció el rostro registrado.',
            ]);
        }

        $bestMatch['profile']->update([
            'failed_attempts'  => 0,
            'last_verified_at' => now(),
        ]);

        $user = $bestMatch['profile']->user;

        Auth::login($user, true);

        return response()->json([
            'redirect' => route('home'),
            'name'     => $user->name,
        ]);
    }

    private function averageDescriptor(array $samples): array
    {
        if (!$samples) {
            return [];
        }

        $length = count($samples[0] ?? []);
        if ($length === 0) {
            return [];
        }

        $sum = array_fill(0, $length, 0.0);
        $count = 0;

        foreach ($samples as $sample) {
            if (!is_array($sample) || count($sample) !== $length) {
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
        return $base . $separator . 'face=' . $result . '#perfil-face';
    }
}
