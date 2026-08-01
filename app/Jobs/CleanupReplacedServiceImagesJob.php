<?php

namespace App\Jobs;

use App\Services\ImageOptimizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class CleanupReplacedServiceImagesJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 120;

    public int $tries = 3;

    public array $backoff = [10, 30, 60];

    /**
     * @param array<int, string> $pathsToDelete
     */
    public function __construct(
        public array $pathsToDelete,
        public string $folder = 'services'
    ) {
        $this->onConnection(config('queue.media_connection', env('MEDIA_QUEUE_CONNECTION', 'database')));
        $this->onQueue(env('MEDIA_QUEUE', 'media'));
    }

    public function handle(ImageOptimizer $imageOptimizer): void
    {
        if (empty($this->pathsToDelete)) {
            return;
        }

        try {
            $imageOptimizer->deleteManyByStoredPaths($this->pathsToDelete, $this->folder);
            Log::info('Limpieza de imágenes anteriores completada exitosamente.', [
                'count' => count($this->pathsToDelete),
                'folder' => $this->folder,
            ]);
        } catch (Throwable $exception) {
            Log::warning('Fallo parcial en limpieza de imágenes anteriores:', [
                'error' => $exception->getMessage(),
                'paths' => $this->pathsToDelete,
            ]);
        }
    }
}
