<?php

namespace App\Services;

use App\Models\FaceProfile;

class FaceRecognitionService
{
    public function compare(array $incoming, FaceProfile $profile): float
    {
        $samples = $profile->descriptors ?? [];
        if (is_array($samples) && count($samples)) {
            $best = null;
            foreach ($samples as $stored) {
                if (! is_array($stored) || ! count($stored)) {
                    continue;
                }
                $distance = $this->distance($incoming, $stored);
                if ($best === null || $distance < $best) {
                    $best = $distance;
                }
            }
            if ($best !== null) {
                return $best;
            }
        }

        $stored = $profile->descriptor ?? [];

        return $this->distance($incoming, $stored);
    }

    public function isMatch(float $distance, FaceProfile $profile): bool
    {
        $profileThreshold = (float) ($profile->threshold ?? 0);
        $configThreshold = (float) config('services.face.threshold', 0.42);
        $threshold = max($profileThreshold, $configThreshold);

        return $distance <= $threshold;
    }

    private function distance(array $incoming, array $stored): float
    {
        $sum = 0.0;
        $max = min(count($incoming), count($stored));

        for ($i = 0; $i < $max; $i++) {
            $diff = ($incoming[$i] ?? 0) - ($stored[$i] ?? 0);
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }
}
