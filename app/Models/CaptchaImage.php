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
        $path = ltrim((string) $this->image_path, '/\\');

        if (str_starts_with($path, 'captcha_animals/')) {
            return public_path($path);
        }

        return base_path($path);
    }
}
