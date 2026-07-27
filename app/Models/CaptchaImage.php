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

        return \Illuminate\Support\Facades\Storage::disk('local')->path($path);
    }
}
