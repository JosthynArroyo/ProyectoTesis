<?php

namespace Database\Seeders;

use App\Models\CaptchaImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\File;

class CaptchaImagesSeeder extends Seeder
{
    public function run(): void
    {
        $synchronizer = app(\App\Services\CaptchaImageSynchronizer::class);

        try {
            $total = $synchronizer->synchronize(function (string $msg, string $type) {
                if ($this->command) {
                    if ($type === 'error') {
                        $this->command->error($msg);
                    } elseif ($type === 'warning') {
                        $this->command->warn($msg);
                    } else {
                        $this->command->info($msg);
                    }
                }
            });
        } catch (\Throwable $e) {
            if ($this->command) {
                $this->command->error("Error en CaptchaImagesSeeder: {$e->getMessage()}");
            }
            throw $e;
        }
    }
}
