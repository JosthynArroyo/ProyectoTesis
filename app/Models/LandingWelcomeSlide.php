<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingWelcomeSlide extends Model
{
    protected $table = 'landing_welcome_slides';

    protected $fillable = [
        'image_path',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
