<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingWelcomeDoctor extends Model
{
    protected $table = 'landing_welcome_doctors';

    protected $fillable = [
        'name',
        'specialty',
        'photo_path',
        'experience_label',
        'featured_label',
        'attendance_label',
        'availability_label',
        'cta_text',
        'pill_text',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
