<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CaptchaChallenge extends Model
{
    protected $fillable = [
        'target_key',
        'option_image_ids',
        'attempts',
        'max_attempts',
        'expires_at',
        'verified_at',
    ];

    protected $casts = [
        'option_image_ids' => 'array',
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
    ];
}
