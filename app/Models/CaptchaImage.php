<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaptchaImage extends Model
{
    protected $fillable = [
        'class_key',
        'dataset_split',
        'image_path',
    ];

    public function fullPath(): string
    {
        $path = str_replace('\\', '/', ltrim((string) $this->image_path, '/\\'));

        if (! str_starts_with($path, 'ai/dataset/val/')) {
            throw new \RuntimeException('La imagen CAPTCHA no pertenece al dataset canonico ai/dataset/val.');
        }

        return base_path(str_replace('/', DIRECTORY_SEPARATOR, $path));
    }
}
