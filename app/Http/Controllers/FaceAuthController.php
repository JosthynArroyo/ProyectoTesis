<?php

namespace App\Http\Controllers;

use App\Models\FaceProfile;
use App\Services\FaceRecognitionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $payload = $request->validate([
            'descriptor' => 'required|array|min:128',
        ]);

        $request->user()->faceProfile()->updateOrCreate([], [
            'descriptor' => array_map('floatval', $payload['descriptor']),
            'threshold'  => config('services.face.threshold', 0.42),
        ]);

        return back()->with('status', 'Perfil facial guardado.');
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
            if (! $profile->user || ! $profile->user->isActive()) {
                continue;
            }

            $distance = $recognizer->compare(array_map('floatval', $data['descriptor']), $profile);

            if (! $recognizer->isMatch($distance, $profile)) {
                continue;
            }

            if (! $bestMatch || $distance < $bestMatch['distance']) {
                $bestMatch = ['profile' => $profile, 'distance' => $distance];
            }
        }

        if (! $bestMatch) {
            throw ValidationException::withMessages([
                'descriptor' => 'No se reconoció el rostro registrado.',
            ]);
        }

        $bestMatch['profile']->update([
            'failed_attempts'  => 0,
            'last_verified_at' => now(),
        ]);

        Auth::login($bestMatch['profile']->user, true);

        return response()->json(['redirect' => route('home')]);
    }
}
