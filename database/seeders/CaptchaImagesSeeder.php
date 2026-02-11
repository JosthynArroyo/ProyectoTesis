<?php

namespace Database\Seeders;

use App\Models\CaptchaImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CaptchaImagesSeeder extends Seeder
{
    public function run(): void
    {
        $classes = config('captcha.classes', [
            'giraffe',
            'horse',
            'koala',
            'kangaroo',
            'rhinoceros',
            'dolphin',
            'blue_whale',
            'zebra',
        ]);

        if (!is_array($classes) || $classes === []) {
            $this->command?->warn('No hay clases configuradas para CAPTCHA.');
            return;
        }

        $rows = [];
        $now = now();

        foreach ($classes as $classKey) {
            if (!is_string($classKey) || $classKey === '') {
                continue;
            }

            $targetDir = public_path("captcha_animals/{$classKey}");
            File::ensureDirectoryExists($targetDir);

            $this->copyFromDatasetIfEmpty($classKey, $targetDir);

            foreach (File::files($targetDir) as $file) {
                $ext = strtolower($file->getExtension());
                if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                    continue;
                }

                $rows[] = [
                    'class_key' => $classKey,
                    'dataset_split' => 'public',
                    'image_path' => "captcha_animals/{$classKey}/{$file->getFilename()}",
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        if ($rows === []) {
            $this->command?->warn('No se encontraron imagenes en public/captcha_animals.');
            return;
        }

        CaptchaImage::query()->upsert(
            $rows,
            ['image_path'],
            ['class_key', 'dataset_split', 'updated_at']
        );

        $this->command?->info('CAPTCHA: ' . count($rows) . ' imagenes registradas.');
    }

    private function copyFromDatasetIfEmpty(string $classKey, string $targetDir): void
    {
        $hasImages = collect(File::files($targetDir))
            ->contains(fn ($file) => in_array(strtolower($file->getExtension()), ['jpg', 'jpeg', 'png', 'webp'], true));

        if ($hasImages) {
            return;
        }

        $sourceDir = base_path("ai/dataset/val/{$classKey}");
        if (!File::isDirectory($sourceDir)) {
            return;
        }

        foreach (File::files($sourceDir) as $file) {
            $ext = strtolower($file->getExtension());
            if (!in_array($ext, ['jpg', 'jpeg', 'png', 'webp'], true)) {
                continue;
            }

            $destination = $targetDir . DIRECTORY_SEPARATOR . $file->getFilename();
            if (!File::exists($destination)) {
                File::copy($file->getPathname(), $destination);
            }
        }
    }
}
