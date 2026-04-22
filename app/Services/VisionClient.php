<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class VisionClient
{
    public function classify(string $imagePath): array
    {
        if (! is_file($imagePath)) {
            throw new RuntimeException("Image not found: {$imagePath}");
        }

        $baseUrl = rtrim((string) config('captcha.vision_url'), '/');
        $url = $baseUrl.'/classify';

        $stream = fopen($imagePath, 'r');
        if ($stream === false) {
            throw new RuntimeException("Unable to read image: {$imagePath}");
        }

        try {
            $response = Http::timeout(20)
                ->acceptJson()
                ->attach('image', $stream, basename($imagePath))
                ->post($url);
        } finally {
            fclose($stream);
        }

        if (! $response->successful()) {
            throw new RuntimeException('Vision API request failed with status '.$response->status());
        }

        $payload = $response->json();
        $label = is_string($payload['label'] ?? null) ? $payload['label'] : '';
        $confidence = (float) ($payload['confidence'] ?? 0.0);

        if ($label === '') {
            throw new RuntimeException('Vision API returned an invalid response payload.');
        }

        return [
            'label' => $label,
            'confidence' => $confidence,
        ];
    }
}
