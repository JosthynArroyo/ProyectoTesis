<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class LandingWelcomeFeaturedSpecialty extends Model
{
    protected $table = 'landing_welcome_featured_specialties';

    protected $fillable = [
        'especialidad_id',
        'sort_order',
    ];
}
