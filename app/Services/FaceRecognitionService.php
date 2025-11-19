<?php

namespace App\Services;

use App\Models\FaceProfile;

class FaceRecognitionService
{
    public function compare(array $incoming, FaceProfile $profile): float
    {
        $stored = $profile->descriptor;
        $sum = 0.0;
        $max = min(count($incoming), count($stored));

        for ($i = 0; $i < $max; $i++) {
            $diff = ($incoming[$i] ?? 0) - ($stored[$i] ?? 0);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    public function isMatch(float $distance, FaceProfile $profile): bool
    {
        $threshold = $profile->threshold ?? config('services.face.threshold', 0.42);
        return $distance <= $threshold;
    }
}
